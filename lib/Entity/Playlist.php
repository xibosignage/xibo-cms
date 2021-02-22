<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace Xibo\Entity;


use Xibo\Exception\DuplicateEntityException;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\TagFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Widget\SubPlaylist;

/**
 * Class Playlist
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class Playlist implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The ID of this Playlist")
     * @var int
     */
    public $playlistId;

    /**
     * @SWG\Property(description="The userId of the User that owns this Playlist")
     * @var int
     */
    public $ownerId;

    /**
     * @SWG\Property(description="The Name of the Playlist")
     * @var string
     */
    public $name;

    /**
     * @SWG\Property(description="The RegionId if this Playlist is specific to a Region")
     * @var int
     */
    public $regionId;

    /**
     * @SWG\Property(description="Flag indicating if this is a dynamic Playlist")
     * @var int
     */
    public $isDynamic;

    /**
     * @SWG\Property(description="Filter Name for a Dynamic Playlist")
     * @var string
     */
    public $filterMediaName;

    /**
     * @SWG\Property(description="Filter Tags for a Dynamic Playlist")
     * @var string
     */
    public $filterMediaTags;

    /**
     * @var string
     * @SWG\Property(
     *  description="The datetime the Layout was created"
     * )
     */
    public $createdDt;

    /**
     * @var string
     * @SWG\Property(
     *  description="The datetime the Layout was last modified"
     * )
     */
    public $modifiedDt;

    /**
     * @var int
     * @SWG\Property(
     *  description="A read-only estimate of this Layout's total duration in seconds. This is equal to the longest region duration and is valid when the layout status is 1 or 2."
     * )
     */
    public $duration = 0;

    /**
     * @var int
     * @SWG\Property(
     *     description="Flag indicating whether this Playlists requires a duration update"
     * )
     */
    public $requiresDurationUpdate;

    /**
     * @var string
     * @SWG\Property(
     *  description="The option to enable the collection of Playlist Proof of Play statistics"
     * )
     */
    public $enableStat;

    /**
     * @SWG\Property(description="An array of Tags")
     * @var Tag[]
     */
    public $tags = [];

    /**
     * @SWG\Property(description="An array of Widgets assigned to this Playlist")
     * @var Widget[]
     */
    public $widgets = [];

    /**
     * @SWG\Property(description="An array of permissions")
     * @var Permission[]
     */
    public $permissions = [];

    /**
     * Temporary Id used during import/upgrade
     * @var string read only string
     */
    public $tempId = null;

    public $tagValues;

    // Read only properties
    public $owner;
    public $groupsWithPermissions;

    private $unassignTags = [];

    //<editor-fold desc="Factories and Dependencies">

    /**
     * @var DateServiceInterface
     */
    private $dateService;

    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /**
     * @var WidgetFactory
     */
    private $widgetFactory;

    /**
     * @var TagFactory
     */
    private $tagFactory;

    /**
     * @var PlaylistFactory
     */
    private $playlistFactory;

    /** @var ModuleFactory */
    private $moduleFactory;

    /**
     * @var ConfigServiceInterface
     */
    private $config;
    //</editor-fold>

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param ConfigServiceInterface $config
     * @param DateServiceInterface $date
     * @param PermissionFactory $permissionFactory
     * @param PlaylistFactory $playlistFactory
     * @param WidgetFactory $widgetFactory
     * @param TagFactory $tagFactory

     */
    public function __construct($store, $log, $config, $date, $permissionFactory, $playlistFactory, $widgetFactory, $tagFactory)
    {
        $this->setCommonDependencies($store, $log);

        $this->config = $config;
        $this->dateService = $date;
        $this->permissionFactory = $permissionFactory;
        $this->playlistFactory = $playlistFactory;
        $this->widgetFactory = $widgetFactory;
        $this->tagFactory = $tagFactory;
    }

    /**
     * @param ModuleFactory $moduleFactory
     * @return $this
     */
    public function setModuleFactory($moduleFactory)
    {
        $this->moduleFactory = $moduleFactory;
        return $this;
    }

    /**
     * Clone this Playlist
     */
    public function __clone()
    {
        $this->hash = null;
        $this->playlistId = null;
        $this->regionId = null;
        $this->permissions = [];

        $this->widgets = array_map(function ($object) { return clone $object; }, $this->widgets);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return sprintf('Playlist %s. Widgets = %d. PlaylistId = %d. RegionId = %d', $this->name, count($this->widgets), $this->playlistId, $this->regionId);
    }

    /**
     * @return string
     */
    private function hash()
    {
        return md5($this->regionId . $this->playlistId . $this->ownerId . $this->name . $this->duration . $this->requiresDurationUpdate);
    }

    /**
     * Get the Id
     * @return int
     */
    public function getId()
    {
        return $this->playlistId;
    }

    /**
     * Get the OwnerId
     * @return int
     */
    public function getOwnerId()
    {
        return $this->ownerId;
    }

    /**
     * Sets the Owner
     * @param int $ownerId
     */
    public function setOwner($ownerId)
    {
        $this->load();
        $this->ownerId = $ownerId;

        foreach ($this->widgets as $widget) {
            /* @var Widget $widget */
            $widget->setOwner($ownerId);
        }
    }

    /**
     * Is this Playlist a Region Playlist (region specific)
     * @return bool
     */
    public function isRegionPlaylist()
    {
        return ($this->regionId != null);
    }

    /**
     * Validate this playlist
     * @throws DuplicateEntityException
     */
    public function validate()
    {
        // check for duplicates,
        // we check for empty playlist name due to layouts existing in the CMS before upgrade to v2
        if ($this->name != '') {
            $duplicates = $this->playlistFactory->query(null, [
                'userId' => $this->ownerId,
                'playlistExact' => $this->name,
                'regionSpecific' => 0,
                'disableUserCheck' => 1,
                'notPlaylistId' => ($this->playlistId == null) ? 0 : $this->playlistId,
            ]);

            if (count($duplicates) > 0) {
                throw new DuplicateEntityException(sprintf(__("You already own a Playlist called '%s'. Please choose another name."), $this->name));
            }
        }
    }

    /**
     * Is this Playlist editable.
     * Are we a standalone playlist OR are we on a draft layout
     * @return bool
     */
    public function isEditable()
    {
        if ($this->isRegionPlaylist()) {
            // Run a lookup to see if we're on a draft layout
            $this->getLog()->debug('Checking whether we are on a Layout which is in the Draft State');

            $exists = $this->getStore()->exists('
                SELECT `layout`.layoutId 
                  FROM `region`
                    INNER JOIN `layout` ON layout.layoutId = region.layoutId 
                 WHERE regionId = :regionId
                  AND parentId IS NOT NULL
            ', [
                'regionId' => $this->regionId
            ]);

            $this->getLog()->debug('We are ' . (($exists) ? 'editable' : 'not editable'));

            return $exists;
        } else {
            $this->getLog()->debug('Non-region Playlist - we\'re always Editable' );
            return true;
        }
    }

    /**
     * Get Widget at Index
     * @param int $index
     * @param Widget[]|null $widgets
     * @return Widget
     * @throws NotFoundException
     */
    public function getWidgetAt($index, $widgets = null)
    {
        if ($widgets === null)
            $widgets = $this->widgets;

        if ($index <= count($widgets)) {
            $zeroBased = $index - 1;
            if (isset($widgets[$zeroBased])) {
                return $widgets[$zeroBased];
            }
        }

        throw new NotFoundException(sprintf(__('Widget not found at index %d'), $index));
    }

    /**
     * Get Widget by Id
     * @param int $widgetId
     * @param Widget[]|null $widgets
     * @return Widget
     * @throws NotFoundException
     */
    public function getWidget($widgetId, $widgets = null)
    {
        if ($widgets === null)
            $widgets = $this->widgets;

        foreach ($widgets as $widget) {
            if ($widget->widgetId == $widgetId) {
                return $widget;
            }
        }

        throw new NotFoundException(sprintf(__('Widget not found with ID %d'), $widgetId));
    }

    /**
     * @param Widget $widget
     * @param int $displayOrder
     */
    public function assignWidget($widget, $displayOrder = null)
    {
        $this->load();

        // Has a display order been provided?
        if ($displayOrder !== null) {
            // We need to shuffle any existing widget down to make space for this one.
            foreach ($this->widgets as $existingWidget) {
                if ($existingWidget->displayOrder < $displayOrder) {
                    // Earlier in the list, so do nothing.
                    continue;
                } else {
                    // This widget is >= the display order and therefore needs to be moved down one position.
                    $existingWidget->displayOrder = $existingWidget->displayOrder + 1;
                }

                // Set the incoming widget to the requested display order.
                $widget->displayOrder = $displayOrder;
            }
        } else {
            // Take the next available one
            $widget->displayOrder = count($this->widgets) + 1;
        }

        $this->widgets[] = $widget;
    }

    /**
     * Delete a Widget
     * @param Widget $widget
     * @param array $options Delete Options
     * @return $this
     * @throws \Xibo\Exception\InvalidArgumentException
     */
    public function deleteWidget($widget, $options = [])
    {
        $this->load();

        if ($widget->playlistId != $this->playlistId) {
            throw new InvalidArgumentException(__('Cannot delete a Widget that isn\'t assigned to me'), 'playlistId');
        }

        // Delete
        $widget->delete($options);

        // Remove the Deleted Widget from our Widgets
        $this->widgets = array_udiff($this->widgets, [$widget], function($a, $b) {
            /* @var \Xibo\Entity\Widget $a */
            /* @var \Xibo\Entity\Widget $b */
            return $a->widgetId - $b->widgetId;
        });

        return $this;
    }

    /**
     * @param Tag[] $tags
     */
    public function replaceTags($tags = [])
    {
        if (!is_array($this->tags) || count($this->tags) <= 0)
            $this->tags = $this->tagFactory->loadByPlaylistId($this->playlistId);

        if ($this->tags != $tags) {
            $this->unassignTags = array_udiff($this->tags, $tags, function ($a, $b) {
                /* @var Tag $a */
                /* @var Tag $b */
                return $a->tagId - $b->tagId;
            });

            $this->getLog()->debug('Tags to be removed: %s', json_encode($this->unassignTags));

            // Replace the arrays
            $this->tags = $tags;

            $this->getLog()->debug('Tags remaining: %s', json_encode($this->tags));
        } else {
            $this->getLog()->debug('Tags were not changed');
        }
    }

    /**
     * Unassign tag
     * @param Tag $tag
     * @return $this
     */
    public function unassignTag($tag)
    {
        $this->load();

        $this->tags = array_udiff($this->tags, [$tag], function($a, $b) {
            /* @var Tag $a */
            /* @var Tag $b */
            return $a->tagId - $b->tagId;
        });

        $this->unassignTags[] = $tag;

        $this->getLog()->debug('Tags after removal %s', json_encode($this->tags));

        return $this;
    }

    /**
     * Load
     * @param array $loadOptions
     * @return $this
     */
    public function load($loadOptions = [])
    {
        if ($this->playlistId == null || $this->loaded)
            return $this;

        // Options
        $options = array_merge([
            'loadPermissions' => true,
            'loadWidgets' => true,
            'loadTags' => true
        ], $loadOptions);

        $this->getLog()->debug('Load Playlist with ' . json_encode($options));

        // Load permissions
        if ($options['loadPermissions'])
            $this->permissions = $this->permissionFactory->getByObjectId(get_class(), $this->playlistId);

        // Load all tags
        if ($options['loadTags'])
            $this->tags = $this->tagFactory->loadByPlaylistId($this->playlistId);

        // Load the widgets
        if ($options['loadWidgets']) {
            foreach ($this->widgetFactory->getByPlaylistId($this->playlistId) as $widget) {
                /* @var Widget $widget */
                $widget->load();
                $this->widgets[] = $widget;
            }
        }

        $this->hash = $this->hash();
        $this->loaded = true;

        return $this;
    }

    /**
     * Save
     * @param array $options
     * @throws \Xibo\Exception\DuplicateEntityException
     * @throws \Xibo\Exception\InvalidArgumentException
     */
    public function save($options = [])
    {
        // Default options
        $options = array_merge([
            'saveTags' => true,
            'saveWidgets' => true,
            'notify' => true,
            'validate' => true,
            'auditPlaylist' => true
        ], $options);

        if ($options['validate']) {
            $this->validate();
        }

        // if we are auditing and editing a regionPlaylist then get layout specific campaignId
        $campaignId = 0;
        $layoutId = 0;

        if ($options['auditPlaylist'] && $this->regionId != null) {
            $sql = 'SELECT campaign.campaignId, layout.layoutId FROM region INNER JOIN layout ON region.layoutId = layout.layoutId INNER JOIN lkcampaignlayout on layout.layoutId = lkcampaignlayout.layoutId INNER JOIN campaign ON campaign.campaignId = lkcampaignlayout.campaignId WHERE campaign.isLayoutSpecific = 1 AND region.regionId = :regionId ;';
            $params = ['regionId' => $this->regionId];
            $results = $this->store->select($sql, $params);
            foreach ($results as $row) {
                $campaignId = $row['campaignId'];
                $layoutId = $row['layoutId'];
            }
        }

        if ($this->playlistId == null || $this->playlistId == 0) {
            $this->add();
        } else if ($this->hash != $this->hash()) {
            $this->update();
        } else {
            // Nothing changed wrt the Playlist itself.
            $options['auditPlaylist'] = false;
        }

        // Save the widgets?
        if ($options['saveWidgets']) {
            // Sort the widgets by their display order
            usort($this->widgets, function ($a, $b) {
                /**
                 * @var Widget $a
                 * @var Widget $b
                 */
                return $a->displayOrder - $b->displayOrder;
            });

            // Assert the Playlist on all widgets and apply a display order
            // this keeps the widgets in numerical order on each playlist
            $i = 0;
            foreach ($this->widgets as $widget) {
                /* @var Widget $widget */
                $i++;

                // Assert the playlistId
                $widget->playlistId = $this->playlistId;
                // Assert the displayOrder
                $widget->displayOrder = $i;
                $widget->save($options);
            }
        }

        // Save the tags?
        if ($options['saveTags']) {
            $this->getLog()->debug('Saving tags on ' . $this);

            // Save the tags
            if (is_array($this->tags)) {
                foreach ($this->tags as $tag) {
                    /* @var Tag $tag */

                    $this->getLog()->debug('Assigning tag ' . $tag->tag);

                    $tag->assignPlaylist($this->playlistId);
                    $tag->save();
                }
            }

            // Remove unwanted ones
            if (is_array($this->unassignTags)) {
                foreach ($this->unassignTags as $tag) {
                    /* @var Tag $tag */
                    $this->getLog()->debug('Unassigning tag ' . $tag->tag);

                    $tag->unassignPlaylist($this->playlistId);
                    $tag->save();
                }
            }
        }

        // Audit
        if ($options['auditPlaylist']) {
            $change = $this->getChangedProperties();

            // if we are editing a regionPlaylist then add the layout specific campaignId to the audit log.
            if ($this->regionId != null) {
                $change['campaignId'][] = $campaignId;
                $change['layoutId'][] = $layoutId;
            }

            $this->audit($this->playlistId, 'Saved', $change);
        }
    }

    /**
     * Delete
     * @param array $options
     * @throws InvalidArgumentException
     */
    public function delete($options = [])
    {
        $options = array_merge([
            'regionDelete' => false
        ], $options);

        // We must ensure everything is loaded before we delete
        if (!$this->loaded) {
            $this->load();
        }

        if (!$options['regionDelete'] && $this->regionId != 0)
            throw new InvalidArgumentException(__('This Playlist belongs to a Region, please delete the Region instead.'), 'regionId');

        // Notify we're going to delete
        // we do this here, because once we've deleted we lose the references for the storage query
        $this->notifyLayouts();

        // Delete me from any other Playlists using me as a sub-playlist
        foreach ($this->playlistFactory->query(null, ['childId' => $this->playlistId, 'depth' => 1]) as $parent) {
            // $parent is a playlist to which we belong.
            // find out widget and delete it
            $this->getLog()->debug('This playlist is a sub-playlist in ' . $parent->name . ' we will need to remove it');
            $parent->load();
            foreach ($parent->widgets as $widget) {
                if ($widget->type === 'subplaylist') {
                    // we get an array with all subplaylists assigned to the parent
                    $subPlaylistIds = json_decode($widget->getOptionValue('subPlaylistIds', '[]'));
                    foreach ($subPlaylistIds as $subplaylist) {
                        // find the matching playlistId to the playlistId we want to delete
                        if ($subplaylist == $this->playlistId) {
                            // if there is only one element in the subPlaylistIds array then remove the widget
                            if (count($subPlaylistIds) === 1) {
                                $widget->delete(['notify' => false]);
                            } else {
                                // if the subPlaylistIds has more than one element, we want to just unassign our playlistId from it and save the widget,
                                // we don't want to remove the whole widget in this case
                                $updatedSubplaylistIds = array_diff($subPlaylistIds, [$this->playlistId]);
                                $widget->setOptionValue('subPlaylistIds', 'attrib', json_encode($updatedSubplaylistIds));
                                $widget->save();
                            }
                        }
                    }
                }
            }
        }

        // We want to remove all link records from the closure table using the parentId
        $this->getStore()->update('DELETE FROM `lkplaylistplaylist` WHERE parentId = :playlistId', ['playlistId' => $this->playlistId]);

        // Delete my closure table records
        $this->getStore()->update('DELETE FROM `lkplaylistplaylist` WHERE childId = :playlistId', ['playlistId' => $this->playlistId]);

        // Unassign tags
        foreach ($this->tags as $tag) {
            /* @var Tag $tag */
            $tag->unassignPlaylist($this->playlistId);
            $tag->save();
        }

        // Delete Permissions
        foreach ($this->permissions as $permission) {
            /* @var Permission $permission */
            $permission->deleteAll();
        }

        // Delete widgets
        foreach ($this->widgets as $widget) {
            /* @var Widget $widget */
            // Assert the playlistId
            $widget->playlistId = $this->playlistId;
            $widget->delete();
        }

        // Delete this playlist
        $this->getStore()->update('DELETE FROM `playlist` WHERE playlistId = :playlistId', array('playlistId' => $this->playlistId));

        // Audit
        $this->audit($this->playlistId, 'Deleted', ['playlistId' => $this->playlistId, 'regionId' => $this->regionId]);
    }

    /**
     * Add
     */
    private function add()
    {
        $this->getLog()->debug('Adding Playlist ' . $this->name);

        $time = date('Y-m-d H:i:s');

        $sql = '
        INSERT INTO `playlist` (`name`, `ownerId`, `regionId`, `isDynamic`, `filterMediaName`, `filterMediaTags`, `createdDt`, `modifiedDt`, `requiresDurationUpdate`, `enableStat`) 
          VALUES (:name, :ownerId, :regionId, :isDynamic, :filterMediaName, :filterMediaTags, :createdDt, :modifiedDt, :requiresDurationUpdate, :enableStat)
        ';
        $this->playlistId = $this->getStore()->insert($sql, array(
            'name' => $this->name,
            'ownerId' => $this->ownerId,
            'regionId' => $this->regionId == 0 ? null : $this->regionId,
            'isDynamic' => $this->isDynamic,
            'filterMediaName' => $this->filterMediaName,
            'filterMediaTags' => $this->filterMediaTags,
            'createdDt' => $time,
            'modifiedDt' => $time,
            'requiresDurationUpdate' => ($this->requiresDurationUpdate === null) ? 0 : $this->requiresDurationUpdate,
            'enableStat' => $this->enableStat
        ));

        // Insert my self link
        $this->getStore()->insert('INSERT INTO `lkplaylistplaylist` (`parentId`, `childId`, `depth`) VALUES (:parentId, :childId, 0)', [
            'parentId' => $this->playlistId,
            'childId' => $this->playlistId
        ]);
    }

    /**
     * Update
     */
    private function update()
    {
        $this->getLog()->debug('Updating Playlist ' . $this->name . '. Id = ' . $this->playlistId);

        $sql = '
            UPDATE `playlist` SET 
                `name` = :name, 
                `ownerId` = :ownerId,
                `regionId` = :regionId, 
                `modifiedDt` = :modifiedDt, 
                `duration` = :duration,
                `isDynamic` = :isDynamic,
                `filterMediaName` = :filterMediaName,
                `filterMediaTags` = :filterMediaTags,
                `requiresDurationUpdate` = :requiresDurationUpdate,
                `enableStat` = :enableStat
             WHERE `playlistId` = :playlistId
        ';

        $this->getStore()->update($sql, array(
            'playlistId' => $this->playlistId,
            'name' => $this->name,
            'ownerId' => $this->ownerId,
            'regionId' => $this->regionId == 0 ? null : $this->regionId,
            'duration' => $this->duration,
            'isDynamic' => $this->isDynamic,
            'filterMediaName' => $this->filterMediaName,
            'filterMediaTags' => $this->filterMediaTags,
            'modifiedDt' => date('Y-m-d H:i:s'),
            'requiresDurationUpdate' => $this->requiresDurationUpdate,
            'enableStat' => $this->enableStat
        ));
    }

    /**
     * Notify all Layouts of a change to this playlist
     *  This only sets the Layout Status to require a build and to update the layout modified date
     *  once the build is triggered, either from the UI or maintenance it will assess the layout
     *  and call save() if required.
     *  Layout->save() will ultimately notify the interested display groups.
     */
    public function notifyLayouts()
    {
        // Notify the Playlist
        $this->getStore()->update('UPDATE `playlist` SET requiresDurationUpdate = 1, `modifiedDT` = :modifiedDt WHERE playlistId = :playlistId', [
            'playlistId' => $this->playlistId,
            'modifiedDt' => $this->dateService->getLocalDate()
        ]);

        $this->getStore()->update('
            UPDATE `layout` SET `status` = 3, `modifiedDT` = :modifiedDt WHERE layoutId IN (
              SELECT `region`.layoutId
                FROM `lkplaylistplaylist`
                  INNER JOIN `playlist`
                  ON `playlist`.playlistId = `lkplaylistplaylist`.parentId
                  INNER JOIN `region`
                  ON `region`.regionId = `playlist`.regionId 
               WHERE `lkplaylistplaylist`.childId = :playlistId
            )
        ', [
            'playlistId' => $this->playlistId,
            'modifiedDt' => $this->dateService->getLocalDate()
        ]);
    }

    /**
     * Expand this Playlists widgets according to any sub-playlists that are present
     * @param int $parentWidgetId this tracks the top level widgetId
     * @param bool $expandSubplaylists
     * @return Widget[]
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function expandWidgets($parentWidgetId = 0, $expandSubplaylists = true)
    {
        $this->load();

        $widgets = [];

        // Start with our own Widgets
        foreach ($this->widgets as $widget) {

            // some basic checking on whether this widets date/time are conductive to it being added to the
            // list. This is really an "expires" check, because we will rely on the player otherwise
            if ($widget->isExpired())
                continue;

            // Persist the parentWidgetId in a temporary variable
            // if we have a parentWidgetId of 0, then we are top-level and we should use our widgetId
            $widget->tempId = $parentWidgetId == 0 ? $widget->widgetId : $parentWidgetId;

            // If we're a standard widget, add right away
            if ($widget->type !== 'subplaylist') {
                $widgets[] = $widget;
            } else {
                if ($expandSubplaylists === true) {
                    /** @var SubPlaylist $module */
                    $module = $this->moduleFactory->createWithWidget($widget);
                    $module->isValid();

                    $widgets = array_merge($widgets, $module->getSubPlaylistResolvedWidgets($widget->tempId));
                }
            }
        }

        return $widgets;
    }

    /**
     * Update Playlist Duration
     *  this is called by the system maintenance task to keep all Playlists durations updated
     *  we should edit this playlist duration (noting the delta) and then find all Playlists of which this is
     *  a sub-playlist and update their durations also (cascade upward)
     * @return $this
     * @throws NotFoundException
     * @throws \Xibo\Exception\DuplicateEntityException
     */
    public function updateDuration()
    {
        // Update this Playlists Duration - get a SUM of all widget durations
        $this->load([
            'loadPermissions' => false,
            'loadWidgets' => true,
            'loadTags' => false
        ]);

        $duration = 0;
        $removedWidget = false;

        // What is the next time we need to update this Playlist (0 is never)
        $nextUpdate = 0;

        foreach ($this->widgets as $widget) {
            // Is this widget expired?
            if ($widget->isExpired()) {

                // Remove this widget.
                if ($widget->getOptionValue('deleteOnExpiry', 0) == 1) {
                    // Don't notify at all because we're going to do that when we finish updating our duration.
                    $widget->delete([
                        'notify' => false,
                        'notifyPlaylists' => false,
                        'forceNotifyPlaylists' => false,
                        'notifyDisplays' => false
                    ]);

                    $removedWidget = true;
                }

                // Do not assess it
                continue;
            }

            // If we're a standard widget, add right away
            if ($widget->type !== 'subplaylist') {
                $duration += $widget->calculatedDuration;

                // Does this expire?
                // Log this as the new next update
                if ($widget->hasExpiry() && ($nextUpdate == 0 || $nextUpdate > $widget->toDt)) {
                    $nextUpdate = $widget->toDt;
                }
            } else {
                // Add the sub playlist duration
                /** @var SubPlaylist $module */
                $module = $this->moduleFactory->createWithWidget($widget);
                $duration += $module->getSubPlaylistResolvedDuration();
            }
        }

        // Set our "requires duration"
        $delta = $duration - $this->duration;

        $this->getLog()->debug('Delta duration after updateDuration ' . $delta);

        $this->duration = $duration;
        $this->requiresDurationUpdate = $nextUpdate;

        $this->save(['saveTags' => false, 'saveWidgets' => false]);

        if ($removedWidget) {
            $this->notifyLayouts();
        }

        if ($delta !== 0) {
            // Use the closure table to update all parent playlists (including this one).
            $this->getStore()->update('
                UPDATE `playlist` SET duration = duration + :delta WHERE playlistId IN (
                    SELECT DISTINCT parentId
                      FROM `lkplaylistplaylist`
                     WHERE childId = :playlistId
                      AND parentId <> :playlistId
                )
            ', [
                'delta' => $delta,
                'playlistId' => $this->playlistId
            ]);
        }

        return $this;
    }

    /**
     * Clone the closure table for a new PlaylistId
     *  usually this is used on Draft creation
     * @param int $newParentId
     */
    public function cloneClosureTable($newParentId)
    {
        $this->getStore()->update('
            INSERT INTO `lkplaylistplaylist` (parentId, childId, depth)
                SELECT :newParentId, childId, depth 
                  FROM lkplaylistplaylist
                 WHERE parentId = :parentId AND depth > 0
        ', [
            'newParentId' => $newParentId,
            'parentId' => $this->playlistId
        ]);
    }

    /**
     * Recursive function, that goes through all widgets on nested Playlists.
     *
     * generates nestedPlaylistDefinitions with Playlist ID as the key - later saved as nestedPlaylist.json on export
     * generates playlistMappings which contains all relations between playlists (parent/child) - later saved as playlistMappings.json on export
     * Adds dataSets data to $dataSets parameter - later saved as dataSet.json on export
     *
     * playlistMappings, nestedPLaylistDefinitions, dataSets and dataSetIds are passed by reference.
     *
     *
     * @param $widgets array An array of widgets assigned to the Playlist
     * @param $parentId int Playlist Id of the Playlist that is a parent to our current Playlist
     * @param $playlistMappings array An array of Playlists with ParentId and PlaylistId as keys
     * @param $count
     * @param $nestedPlaylistDefinitions array An array of Playlists including widdgets with playlistId as the key
     * @param $dataSetIds array Array of dataSetIds
     * @param $dataSets array Array of dataSets with dataSets from widgets on the layout level and nested Playlists
     * @param $dataSetFactory
     * @param $includeData bool Flag indicating whether we should include DataSet data in the export
     * @return mixed
     * @throws NotFoundException
     */
    public function generatePlaylistMapping($widgets, $parentId, &$playlistMappings, &$count, &$nestedPlaylistDefinitions, &$dataSetIds, &$dataSets, $dataSetFactory, $includeData)
    {
            foreach ($widgets as $playlistWidget) {

                if ($playlistWidget->type == 'subplaylist') {

                    $nestedPlaylistIds = json_decode($playlistWidget->getOptionValue('subPlaylistIds', []), true);
                    foreach ($nestedPlaylistIds as $nestedPlaylistId) {
                        $nestedPlaylist = $this->playlistFactory->getById($nestedPlaylistId);
                        $nestedPlaylist->load();
                        $this->getLog()->debug('playlist mappings parent id ' . $parentId);
                        $nestedPlaylistDefinitions[$nestedPlaylist->playlistId] = $nestedPlaylist;

                        $playlistMappings[$parentId][$nestedPlaylist->playlistId] = [
                            'parentId' => $parentId,
                            'playlist' => $nestedPlaylist->name,
                            'playlistId' => $nestedPlaylist->playlistId
                        ];

                        $count++;

                        // this is a recursive function, we need to go through all levels of nested Playlists.
                        $this->generatePlaylistMapping($nestedPlaylist->widgets, $nestedPlaylist->playlistId, $playlistMappings, $count, $nestedPlaylistDefinitions,$dataSetIds, $dataSets, $dataSetFactory, $includeData);
                    }
                }

                // if we have any widgets that use DataSets we want the dataSetId and data added
                if ($playlistWidget->type == 'datasetview' || $playlistWidget->type == 'datasetticker' || $playlistWidget->type == 'chart') {
                    $dataSetId = $playlistWidget->getOptionValue('dataSetId', 0);

                    if ($dataSetId != 0) {

                        if (in_array($dataSetId, $dataSetIds))
                            continue;

                        // Export the structure for this dataSet
                        $dataSet = $dataSetFactory->getById($dataSetId, 0);
                        $dataSet->load();

                        // Are we also looking to export the data?
                        if ($includeData) {
                            $dataSet->data = $dataSet->getData([], ['includeFormulaColumns' => false]);
                        }

                        $dataSetIds[] = $dataSet->dataSetId;
                        $dataSets[] = $dataSet;
                    }
                }
            }

            return $playlistMappings;
    }
}