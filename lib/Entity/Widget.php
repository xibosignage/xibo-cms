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

use Xibo\Exception\NotFoundException;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\WidgetAudioFactory;
use Xibo\Factory\WidgetMediaFactory;
use Xibo\Factory\WidgetOptionFactory;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Widget\ModuleWidget;

/**
 * Class Widget
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class Widget implements \JsonSerializable
{
    public static $DATE_MIN = 0;
    public static $DATE_MAX = 2147483647;

    use EntityTrait;

    /**
     * @SWG\Property(description="The Widget ID")
     * @var int
     */
    public $widgetId;

    /**
     * @SWG\Property(description="The ID of the Playlist this Widget belongs to")
     * @var int
     */
    public $playlistId;

    /**
     * @SWG\Property(description="The ID of the User that owns this Widget")
     * @var int
     */
    public $ownerId;

    /**
     * @SWG\Property(description="The Module Type Code")
     * @var string
     */
    public $type;

    /**
     * @SWG\Property(description="The duration in seconds this widget should be shown")
     * @var int
     */
    public $duration;

    /**
     * @SWG\Property(description="The display order of this widget")
     * @var int
     */
    public $displayOrder;

    /**
     * @SWG\Property(description="Flag indicating if this widget has a duration that should be used")
     * @var int
     */
    public $useDuration;

    /**
     * @SWG\Property(description="Calculated Duration of this widget after taking into account the useDuration flag")
     * @var int
     */
    public $calculatedDuration = 0;

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
     * @SWG\Property(description="Widget From Date")
     * @var int
     */
    public $fromDt;

    /**
     * @SWG\Property(description="Widget To Date")
     * @var int
     */
    public $toDt;

    /**
     * @SWG\Property(description="Transition Type In")
     * @var int
     */
    public $transitionIn;

    /**
     * @SWG\Property(description="Transition Type out")
     * @var int
     */
    public $transitionOut;

    /**
     * @SWG\Property(description="Transition duration in")
     * @var int
     */
    public $transitionDurationIn;

    /**
     * @SWG\Property(description="Transition duration out")
     * @var int
     */
    public $transitionDurationOut;

    /**
     * @SWG\Property(description="An array of Widget Options")
     * @var WidgetOption[]
     */
    public $widgetOptions = [];

    /**
     * @SWG\Property(description="An array of MediaIds this widget is linked to")
     * @var int[]
     */
    public $mediaIds = [];

    /**
     * @SWG\Property(description="An array of Audio MediaIds this widget is linked to")
     * @var WidgetAudio[]
     */
    public $audio = [];

    /**
     * @SWG\Property(description="An array of permissions for this widget")
     * @var Permission[]
     */
    public $permissions = [];

    /**
     * @SWG\Property(description="The Module Object for this Widget")
     * @var ModuleWidget $module
     */
    public $module;

    /**
     * @SWG\Property(description="The name of the Playlist this Widget is on")
     * @var string $playlist
     */
    public $playlist;

    /**
     * Hash Key of Media Assignments
     * @var string
     */
    private $mediaHash = null;

    /**
     * Temporary Id used during import/upgrade/sub-playlist ordering
     * @var string read only string
     */
    public $tempId = null;

    /**
     * Flag to indicate whether the widget is newly added
     * @var bool
     */
    public $isNew = false;

    /** @var int[] Original Module Media Ids */
    private $originalModuleMediaIds = [];

    /** @var array[int] Original Media IDs */
    private $originalMediaIds = [];

    /** @var array[WidgetAudio] Original Widget Audio */
    private $originalAudio = [];

    /**
     * Minimum duration for widgets
     * @var int
     */
    public static $widgetMinDuration = 1;

    //<editor-fold desc="Factories and Dependencies">
    /** @var  DateServiceInterface */
    private $dateService;

    /**
     * @var WidgetOptionFactory
     */
    private $widgetOptionFactory;

    /**
     * @var WidgetMediaFactory
     */
    private $widgetMediaFactory;

    /** @var  WidgetAudioFactory */
    private $widgetAudioFactory;

    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /** @var  DisplayFactory */
    private $displayFactory;

    /** @var  PlaylistFactory */
    private $playlistFactory;
    //</editor-fold>

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param DateServiceInterface $date
     * @param WidgetOptionFactory $widgetOptionFactory
     * @param WidgetMediaFactory $widgetMediaFactory
     * @param WidgetAudioFactory $widgetAudioFactory
     * @param PermissionFactory $permissionFactory
     * @param DisplayFactory $displayFactory
     */
    public function __construct($store, $log, $date, $widgetOptionFactory, $widgetMediaFactory, $widgetAudioFactory, $permissionFactory, $displayFactory)
    {
        $this->setCommonDependencies($store, $log);
        $this->excludeProperty('module');
        $this->dateService = $date;
        $this->widgetOptionFactory = $widgetOptionFactory;
        $this->widgetMediaFactory = $widgetMediaFactory;
        $this->widgetAudioFactory = $widgetAudioFactory;
        $this->permissionFactory = $permissionFactory;
        $this->displayFactory = $displayFactory;
    }

    /**
     * @param PlaylistFactory $playlistFactory
     * @return $this
     */
    public function setChildObjectDepencencies($playlistFactory)
    {
        $this->playlistFactory = $playlistFactory;
        return $this;
    }

    public function __clone()
    {
        $this->hash = null;
        $this->widgetId = null;
        $this->widgetOptions = array_map(function ($object) { return clone $object; }, $this->widgetOptions);
        $this->permissions = [];

        // No need to clone the media, but we should empty the original arrays of ids
        $this->originalMediaIds = [];
        $this->originalAudio = [];
    }

    /**
     * String
     * @return string
     */
    public function __toString()
    {
        return sprintf('Widget. %s on playlist %d in position %d. WidgetId = %d', $this->type, $this->playlistId, $this->displayOrder, $this->widgetId);
    }

    /**
     * Unique Hash
     * @return string
     */
    private function hash()
    {
        return md5($this->widgetId
            . $this->playlistId
            . $this->ownerId
            . $this->type
            . $this->duration
            . $this->displayOrder
            . $this->useDuration
            . $this->calculatedDuration
            . $this->fromDt
            . $this->toDt
            . json_encode($this->widgetOptions)
        );
    }

    /**
     * Hash of all media id's
     * @return string
     */
    private function mediaHash()
    {
        sort($this->mediaIds);
        return md5(implode(',', $this->mediaIds));
    }

    /**
     * Get the Id
     * @return int
     */
    public function getId()
    {
        return $this->widgetId;
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
     * Set the Owner
     * @param int $ownerId
     */
    public function setOwner($ownerId)
    {
        $this->ownerId = $ownerId;
    }

    /**
     * Get Option
     * @param string $option
     * @return WidgetOption
     * @throws NotFoundException
     */
    public function getOption($option)
    {
        foreach ($this->widgetOptions as $widgetOption) {
            /* @var WidgetOption $widgetOption */
            if (strtolower($widgetOption->option) == strtolower($option))
                return $widgetOption;
        }

        throw new NotFoundException('Widget Option not found');
    }

    /**
     * Get Widget Option Value
     * @param string $option
     * @param mixed $default
     * @return mixed
     */
    public function getOptionValue($option, $default)
    {
        try {
            $widgetOption = $this->getOption($option);
            $widgetOption = (($widgetOption->value) === null) ? $default : $widgetOption->value;

            if (is_integer($default))
                $widgetOption = intval($widgetOption);

            return $widgetOption;
        }
        catch (NotFoundException $e) {
            return $default;
        }
    }

    /**
     * Set Widget Option Value
     * @param string $option
     * @param string $type
     * @param mixed $value
     */
    public function setOptionValue($option, $type, $value)
    {
        try {
            $widgetOption = $this->getOption($option);
            $widgetOption->type = $type;
            $widgetOption->value = $value;
        }
        catch (NotFoundException $e) {
            $this->widgetOptions[] = $this->widgetOptionFactory->create($this->widgetId, $type, $option, $value);
        }
    }

    /**
     * Assign File Media
     * @param int $mediaId
     */
    public function assignMedia($mediaId)
    {
        $this->load();

        if (!in_array($mediaId, $this->mediaIds))
            $this->mediaIds[] = $mediaId;
    }

    /**
     * Unassign File Media
     * @param int $mediaId
     */
    public function unassignMedia($mediaId)
    {
        $this->load();

        $this->mediaIds = array_diff($this->mediaIds, [$mediaId]);
    }

    /**
     * Count media
     * @return int count of media
     */
    public function countMedia()
    {
        $this->load();
        return count($this->mediaIds);
    }

    /**
     * @return int
     * @throws NotFoundException
     */
    public function getPrimaryMediaId()
    {
        $primary = $this->getPrimaryMedia();

        if (count($primary) <= 0)
            throw new NotFoundException(__('No file to return'));

        return $primary[0];
    }

    /**
     * Get Primary Media
     * @return int[]
     */
    public function getPrimaryMedia()
    {
        $this->load();

        $this->getLog()->debug('Getting first primary media for Widget: ' . $this->widgetId . ' Media: ' . json_encode($this->mediaIds) . ' audio ' . json_encode($this->getAudioIds()));

        if (count($this->mediaIds) <= 0)
            return [];

        // Remove the audio media from this array
        return array_values(array_diff($this->mediaIds, $this->getAudioIds()));
    }

    /**
     * Clear Media
     *  this must only clear module media, not "primary" media
     */
    public function clearCachedMedia()
    {
        $this->load();
        $this->mediaIds = array_values(array_diff($this->mediaIds, $this->originalModuleMediaIds));
    }

    /**
     * Assign Audio Media
     * @param WidgetAudio $audio
     */
    public function assignAudio($audio)
    {
        $this->load();

        $found = false;
        foreach ($this->audio as $existingAudio) {
            if ($existingAudio->mediaId == $audio->mediaId) {
                $existingAudio->loop = $audio->loop;
                $existingAudio->volume = $audio->volume;
                $found = true;
                break;
            }
        }

        if (!$found)
            $this->audio[] = $audio;

        // Assign the media
        $this->assignMedia($audio->mediaId);
    }

    /**
     * Unassign Audio Media
     * @param int $mediaId
     */
    public function assignAudioById($mediaId)
    {
        $this->load();

        $widgetAudio = $this->widgetAudioFactory->createEmpty();
        $widgetAudio->mediaId = $mediaId;
        $widgetAudio->volume = 100;
        $widgetAudio->loop = 0;

        $this->assignAudio($widgetAudio);
    }

    /**
     * Unassign Audio Media
     * @param WidgetAudio $audio
     */
    public function unassignAudio($audio)
    {
        $this->load();

        $this->audio = array_udiff($this->audio, [$audio], function($a, $b) {
            /**
             * @var WidgetAudio $a
             * @var WidgetAudio $b
             */
            return $a->getId() - $b->getId();
        });

        // Unassign the media
        $this->unassignMedia($audio->mediaId);
    }

    /**
     * Unassign Audio Media
     * @param int $mediaId
     */
    public function unassignAudioById($mediaId)
    {
        $this->load();

        foreach ($this->audio as $audio) {

            if ($audio->mediaId == $mediaId)
                $this->unassignAudio($audio);
        }
    }

    /**
     * Count Audio
     * @return int
     */
    public function countAudio()
    {
        $this->load();

        return count($this->audio);
    }

    /**
     * Get AudioIds
     * @return int[]
     */
    public function getAudioIds()
    {
        $this->load();

        return array_map(function($element) {
            /** @var WidgetAudio $element */
            return $element->mediaId;
        }, $this->audio);
    }

    /**
     * Have the media assignments changed.
     */
    public function hasMediaChanged()
    {
        return ($this->mediaHash != $this->mediaHash());
    }

    /**
     * @return bool true if this widget has an expiry date
     */
    public function hasExpiry()
    {
        return $this->toDt !== self::$DATE_MAX;
    }

    /**
     * @return bool true if this widget has expired
     */
    public function isExpired()
    {
        return ($this->toDt !== self::$DATE_MAX && $this->dateService->parse($this->toDt, 'U') < $this->dateService->parse());
    }

    /**
     * Calculates the duration of this widget according to some rules
     * @param $module ModuleWidget
     * @param bool $import
     * @return $this
     */
    public function calculateDuration($module, $import = false)
    {
        $this->getLog()->debug('Calculating Duration - existing value is ' . $this->calculatedDuration);

        // Does our widget have a durationIsPerItem and a Number of Items?
        $numItems = $this->getOptionValue('numItems', 0);

        // Determine the duration of this widget
        if ($this->type === 'subplaylist') {
            // We use the module to calculate the duration
            $this->calculatedDuration = $module->getSubPlaylistResolvedDuration();

        } else if ($this->getOptionValue('durationIsPerItem', 0) == 1 && $numItems > 1) {
            // If we have paging involved then work out the page count.
            $itemsPerPage = $this->getOptionValue('itemsPerPage', 0);
            if ($itemsPerPage > 0) {
                $numItems = ceil($numItems / $itemsPerPage);
            }

            // For import
            // in the layout.xml file the duration associated with widget that has all the above parameters
            // will already be the calculatedDuration ie $this->duration from xml is duration * (numItems/itemsPerPage)
            // since we preserve the itemsPerPage, durationIsPerItem and numItems on imported layout, we need to ensure we set the duration correctly
            // this will ensure that both, the widget duration and calculatedDuration will be correct on import.
            if ($import) {
                $this->duration = (($this->useDuration == 1) ? $this->duration / $numItems : $module->getModule()->defaultDuration);
            }

            $this->calculatedDuration = (($this->useDuration == 1) ? $this->duration : $module->getModule()->defaultDuration) * $numItems;
        } else if ($this->useDuration == 1) {
            // Widget duration is as specified
            $this->calculatedDuration = $this->duration;

        } else if ($this->type === 'video' || $this->type === 'audio') {
            // The calculated duration is the "real" duration (caters for 0 videos)
            $this->calculatedDuration = $module->getDuration(['real' => true]);

        } else {
            // The module default duration.
            $this->calculatedDuration = $module->getModule()->defaultDuration;
        }

        $this->getLog()->debug('Set to ' . $this->calculatedDuration);

        return $this;
    }

    /**
     * Load the Widget
     */
    public function load()
    {
        if ($this->loaded || $this->widgetId == null || $this->widgetId == 0)
            return;

        // Load permissions
        $this->permissions = $this->permissionFactory->getByObjectId(get_class(), $this->widgetId);

        // Load the widget options
        $this->widgetOptions = $this->widgetOptionFactory->getByWidgetId($this->widgetId);

        // Load any media assignments for this widget
        $this->mediaIds = $this->widgetMediaFactory->getByWidgetId($this->widgetId);
        $this->originalMediaIds = $this->mediaIds;
        $this->originalModuleMediaIds = $this->widgetMediaFactory->getModuleOnlyByWidgetId($this->widgetId);

        // Load any widget audio assignments
        $this->audio = $this->widgetAudioFactory->getByWidgetId($this->widgetId);
        $this->originalAudio = $this->audio;

        $this->hash = $this->hash();
        $this->mediaHash = $this->mediaHash();
        $this->loaded = true;
    }

    /**
     * Save the widget
     * @param array $options
     */
    public function save($options = [])
    {
        // Default options
        $options = array_merge([
            'saveWidgetOptions' => true,
            'saveWidgetAudio' => true,
            'saveWidgetMedia' => true,
            'notify' => true,
            'notifyPlaylists' => true,
            'notifyDisplays' => false,
            'audit' => true,
            'alwaysUpdate' => false
        ], $options);

        $this->getLog()->debug('Saving widgetId ' . $this->getId() . ' with options. ' . json_encode($options, JSON_PRETTY_PRINT));

        // if we are auditing get layout specific campaignId
        if ($options['audit']) {
            $campaignId = 0;
            $layoutId = 0;
            $sql = 'SELECT campaign.campaignId, layout.layoutId FROM playlist INNER JOIN region ON playlist.regionId = region.regionId INNER JOIN layout ON region.layoutId = layout.layoutId INNER JOIN lkcampaignlayout on layout.layoutId = lkcampaignlayout.layoutId INNER JOIN campaign ON campaign.campaignId = lkcampaignlayout.campaignId WHERE campaign.isLayoutSpecific = 1 AND playlist.playlistId = :playlistId ;';
            $params = ['playlistId' => $this->playlistId];
            $results = $this->store->select($sql, $params);
            foreach ($results as $row) {
                $campaignId = $row['campaignId'];
                $layoutId = $row['layoutId'];
            }
        }

        // Add/Edit
        $isNew = false;
        if ($this->widgetId == null || $this->widgetId == 0) {
            $this->add();
            $isNew = true;
        } else if ($this->hash != $this->hash() || $options['alwaysUpdate']) {
            $this->update();
        }

        // Save the widget options
        if ($options['saveWidgetOptions']) {
            foreach ($this->widgetOptions as $widgetOption) {
                /* @var \Xibo\Entity\WidgetOption $widgetOption */

                // Assert the widgetId
                $widgetOption->widgetId = $this->widgetId;
                $widgetOption->save();
            }
        }

        // Save the widget audio
        if ($options['saveWidgetAudio']) {
            foreach ($this->audio as $audio) {
                /* @var \Xibo\Entity\WidgetAudio $audio */

                // Assert the widgetId
                $audio->widgetId = $this->widgetId;
                $audio->save();
            }

            $removedAudio = array_udiff($this->originalAudio, $this->audio, function($a, $b) {
                /**
                 * @var WidgetAudio $a
                 * @var WidgetAudio $b
                 */
                return $a->getId() - $b->getId();
            });

            foreach ($removedAudio as $audio) {
                /* @var \Xibo\Entity\WidgetAudio $audio */

                // Assert the widgetId
                $audio->widgetId = $this->widgetId;
                $audio->delete();
            }
        }

        // Manage the assigned media
        if ($options['saveWidgetMedia'] || $options['saveWidgetAudio']) {
            $this->linkMedia();
            $this->unlinkMedia();
        }

        // Call notify with the notify options passed in
        $this->notify($options);

        if ($options['audit']) {
            if ($isNew) {
                $changedProperties = null;
                if ($campaignId != 0 && $layoutId != 0) {
                    $this->audit($this->widgetId, 'Added', ['widgetId' => $this->widgetId, 'type' => $this->type, 'layoutId' => $layoutId, 'campaignId' => $campaignId]);
                }
            } else {
                $changedProperties = $this->getChangedProperties();
                $changedItems = [];

                foreach ($this->widgetOptions as $widgetOption) {
                    $itemsProperties = $widgetOption->getChangedProperties();

                    // for widget options what we get from getChangedProperities is an array with value as key and changed value as value
                    // we want to override the key in the returned array, so that we get a clear option name that was changed
                    if (array_key_exists('value', $itemsProperties)) {
                        $itemsProperties[$widgetOption->option] = $itemsProperties['value'];
                        unset($itemsProperties['value']);
                    }

                    if (count($itemsProperties) > 0) {
                        $changedItems[] = $itemsProperties;
                    }
                }

                if (count($changedItems) > 0) {
                    $changedProperties['widgetOptions'] = json_encode($changedItems, JSON_PRETTY_PRINT);
                }

                // if we are editing a widget assigned to a regionPlaylist add the layout specific campaignId to the audit log
                if ($campaignId != 0 && $layoutId != 0) {
                    $changedProperties['campaignId'][] = $campaignId;
                    $changedProperties['layoutId'][] = $layoutId;
                }
            }

            $this->audit($this->widgetId, 'Saved', $changedProperties);
        }
    }

    /**
     * @param array $options
     */
    public function delete($options = [])
    {
        $options = array_merge([
            'notify' => true,
            'notifyPlaylists' => true,
            'forceNotifyPlaylists' => true,
            'notifyDisplays' => false
        ], $options);

        // We must ensure everything is loaded before we delete
        $this->load();

        // Delete Permissions
        foreach ($this->permissions as $permission) {
            /* @var Permission $permission */
            $permission->deleteAll();
        }

        // Delete all Options
        foreach ($this->widgetOptions as $widgetOption) {
            /* @var \Xibo\Entity\WidgetOption $widgetOption */

            // Assert the widgetId
            $widgetOption->widgetId = $this->widgetId;
            $widgetOption->delete();
        }

        // Delete the widget audio
        foreach ($this->audio as $audio) {
            /* @var \Xibo\Entity\WidgetAudio $audio */

            // Assert the widgetId
            $audio->widgetId = $this->widgetId;
            $audio->delete();
        }

        // Unlink Media
        $this->mediaIds = [];
        $this->unlinkMedia();

        // Delete this
        $this->getStore()->update('DELETE FROM `widget` WHERE widgetId = :widgetId', array('widgetId' => $this->widgetId));

        // Call notify with the notify options passed in
        $this->notify($options);

        $this->getLog()->debug('Delete Widget Complete');

        // Audit
        $this->audit($this->widgetId, 'Deleted', ['widgetId' => $this->widgetId, 'playlistId' => $this->playlistId]);
    }

    /**
     * Notify
     * @param $options
     */
    private function notify($options)
    {
        // By default we do nothing in here, options have to be explicitly enabled.
        $options = array_merge([
            'notify' => false,
            'notifyPlaylists' => false,
            'forceNotifyPlaylists' => false,
            'notifyDisplays' => false
        ], $options);

        $this->getLog()->debug('Notifying upstream playlist. Notify Layout: ' . $options['notify'] . ' Notify Displays: ' . $options['notifyDisplays']);

        // Should we notify the Playlist
        // we do this if the duration has changed on this widget.
        if ($options['forceNotifyPlaylists']|| ($options['notifyPlaylists'] && (
                $this->hasPropertyChanged('calculatedDuration')
                || $this->hasPropertyChanged('fromDt')
                || $this->hasPropertyChanged('toDt')
            ))) {
            // Notify the Playlist
            $this->getStore()->update('UPDATE `playlist` SET requiresDurationUpdate = 1, `modifiedDT` = :modifiedDt WHERE playlistId = :playlistId', [
                'playlistId' => $this->playlistId,
                'modifiedDt' => $this->dateService->getLocalDate()
            ]);
        }

        // Notify Layout
        // We do this for draft and published versions of the Layout to keep the Layout Status fresh and the modified
        // date updated.
        if ($options['notify']) {
            // Notify the Layout
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

        // Notify any displays (clearing their cache)
        // this is typically done when there has been a dynamic change to the Widget - i.e. the Layout doesn't need
        // to be rebuilt, but the Widget has some change that will be pushed out through getResource
        if ($options['notifyDisplays']) {
            $this->displayFactory->getDisplayNotifyService()->collectNow()->notifyByPlaylistId($this->playlistId);
        }
    }

    private function add()
    {
        $this->getLog()->debug('Adding Widget ' . $this->type . ' to PlaylistId ' . $this->playlistId);

        $this->isNew = true;

        $sql = '
            INSERT INTO `widget` (`playlistId`, `ownerId`, `type`, `duration`, `displayOrder`, `useDuration`, `calculatedDuration`, `fromDt`, `toDt`, `createdDt`, `modifiedDt`)
            VALUES (:playlistId, :ownerId, :type, :duration, :displayOrder, :useDuration, :calculatedDuration, :fromDt, :toDt, :createdDt, :modifiedDt)
        ';

        $this->widgetId = $this->getStore()->insert($sql, array(
            'playlistId' => $this->playlistId,
            'ownerId' => $this->ownerId,
            'type' => $this->type,
            'duration' => $this->duration,
            'displayOrder' => $this->displayOrder,
            'useDuration' => $this->useDuration,
            'calculatedDuration' => $this->calculatedDuration,
            'fromDt' => ($this->fromDt == null) ? self::$DATE_MIN : $this->fromDt,
            'toDt' => ($this->toDt == null) ? self::$DATE_MAX : $this->toDt,
            'createdDt' => ($this->createdDt === null) ? time() : $this->createdDt,
            'modifiedDt' => time()
        ));
    }

    private function update()
    {
        $this->getLog()->debug('Saving Widget ' . $this->type . ' on PlaylistId ' . $this->playlistId . ' WidgetId: ' . $this->widgetId);

        $sql = '
          UPDATE `widget` SET `playlistId` = :playlistId,
            `ownerId` = :ownerId,
            `type` = :type,
            `duration` = :duration,
            `displayOrder` = :displayOrder,
            `useDuration` = :useDuration,
            `calculatedDuration` = :calculatedDuration,
            `fromDt` = :fromDt,
            `toDt` = :toDt, 
            `modifiedDt` = :modifiedDt
           WHERE `widgetId` = :widgetId
        ';

        $params = [
            'playlistId' => $this->playlistId,
            'ownerId' => $this->ownerId,
            'type' => $this->type,
            'duration' => $this->duration,
            'widgetId' => $this->widgetId,
            'displayOrder' => $this->displayOrder,
            'useDuration' => $this->useDuration,
            'calculatedDuration' => $this->calculatedDuration,
            'fromDt' => ($this->fromDt == null) ? self::$DATE_MIN : $this->fromDt,
            'toDt' => ($this->toDt == null) ? self::$DATE_MAX : $this->toDt,
            'modifiedDt' => time()
        ];

        $this->getStore()->update($sql, $params);
    }

    /**
     * Link Media
     */
    private function linkMedia()
    {
        // Calculate the difference between the current assignments and the original.
        $mediaToLink = array_diff($this->mediaIds, $this->originalMediaIds);

        $this->getLog()->debug('Linking %d new media to Widget %d', count($mediaToLink), $this->widgetId);

        // TODO: Make this more efficient by storing the prepared SQL statement
        $sql = 'INSERT INTO `lkwidgetmedia` (widgetId, mediaId) VALUES (:widgetId, :mediaId) ON DUPLICATE KEY UPDATE mediaId = :mediaId2';

        foreach ($mediaToLink as $mediaId) {

            $this->getStore()->insert($sql, array(
                'widgetId' => $this->widgetId,
                'mediaId' => $mediaId,
                'mediaId2' => $mediaId
            ));
        }
    }

    /**
     * Unlink Media
     */
    private function unlinkMedia()
    {
        // Calculate the difference between the current assignments and the original.
        $mediaToUnlink = array_diff($this->originalMediaIds, $this->mediaIds);

        $this->getLog()->debug('Unlinking %d old media from Widget %d', count($mediaToUnlink), $this->widgetId);

        if (count($mediaToUnlink) <= 0)
            return;

        // Unlink any media in the collection
        $params = ['widgetId' => $this->widgetId];

        $sql = 'DELETE FROM `lkwidgetmedia` WHERE widgetId = :widgetId AND mediaId IN (0';

        $i = 0;
        foreach ($mediaToUnlink as $mediaId) {
            $i++;
            $sql .= ',:mediaId' . $i;
            $params['mediaId' . $i] = $mediaId;
        }

        $sql .= ')';

        $this->getStore()->update($sql, $params);
    }
}