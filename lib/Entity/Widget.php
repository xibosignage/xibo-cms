<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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

use Carbon\Carbon;
use Xibo\Event\SubPlaylistDurationEvent;
use Xibo\Event\WidgetDeleteEvent;
use Xibo\Event\WidgetEditEvent;
use Xibo\Factory\ActionFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\WidgetAudioFactory;
use Xibo\Factory\WidgetMediaFactory;
use Xibo\Factory\WidgetOptionFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Service\DisplayNotifyServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Widget\Definition\Property;

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
     * @SWG\Property(description="Widget Schema Version")
     * @var int
     */
    public $schemaVersion;

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
     * @SWG\Property(description="The name of the Playlist this Widget is on")
     * @var string $playlist
     */
    public $playlist;

    /** @var Action[] */
    public $actions = [];

    /**
     * Hash Key of Media Assignments
     * @var string
     */
    private $mediaHash = null;

    /**
     * Temporary media Id used during import/upgrade/sub-playlist ordering
     * @var string read only string
     */
    public $tempId = null;

    /**
     * Temporary widget Id used during import/upgrade/sub-playlist ordering
     * @var string read only string
     */
    public $tempWidgetId = null;

    /**
     * Flag to indicate whether the widget is valid
     * @var bool
     */
    public $isValid = false;

    /**
     * Flag to indicate whether the widget is newly added
     * @var bool
     */
    public $isNew = false;

    public $folderId;
    public $permissionsFolderId;

    /** @var int[] Original Media IDs */
    private $originalMediaIds = [];

    /** @var array[WidgetAudio] Original Widget Audio */
    private $originalAudio = [];

    /** @var \Xibo\Entity\WidgetOption[] Original widget options when this widget was laded */
    private $originalWidgetOptions = [];

    /**
     * Minimum duration for widgets
     * @var int
     */
    public static $widgetMinDuration = 1;

    private $datesToFormat = ['toDt', 'fromDt'];

    //<editor-fold desc="Factories and Dependencies">

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

    /** @var DisplayNotifyServiceInterface */
    private $displayNotifyService;

    /** @var ActionFactory */
    private $actionFactory;
    //</editor-fold>

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     * @param WidgetOptionFactory $widgetOptionFactory
     * @param WidgetMediaFactory $widgetMediaFactory
     * @param WidgetAudioFactory $widgetAudioFactory
     * @param PermissionFactory $permissionFactory
     * @param DisplayNotifyServiceInterface $displayNotifyService
     * @param ActionFactory $actionFactory
     */
    public function __construct(
        $store,
        $log,
        $dispatcher,
        $widgetOptionFactory,
        $widgetMediaFactory,
        $widgetAudioFactory,
        $permissionFactory,
        $displayNotifyService,
        $actionFactory
    ) {
        $this->setCommonDependencies($store, $log, $dispatcher);
        $this->excludeProperty('module');
        $this->widgetOptionFactory = $widgetOptionFactory;
        $this->widgetMediaFactory = $widgetMediaFactory;
        $this->widgetAudioFactory = $widgetAudioFactory;
        $this->permissionFactory = $permissionFactory;
        $this->displayNotifyService = $displayNotifyService;
        $this->actionFactory = $actionFactory;
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

        // Clone actions
        $this->actions = array_map(function ($object) { return clone $object; }, $this->actions);
    }

    /**
     * String
     * @return string
     */
    public function __toString()
    {
        return sprintf('Widget. %s on playlist %d in position %d. WidgetId = %d', $this->type, $this->playlistId, $this->displayOrder, $this->widgetId);
    }

    public function getPermissionFolderId()
    {
        return $this->permissionsFolderId;
    }

    /**
     * Get the Display Notify Service
     * @return DisplayNotifyServiceInterface
     */
    public function getDisplayNotifyService(): DisplayNotifyServiceInterface
    {
        return $this->displayNotifyService->init();
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
            . json_encode($this->actions)
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
     * @param bool $originalValue
     * @return WidgetOption
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function getOption(string $option, bool $originalValue = false): WidgetOption
    {
        $widgetOptions = $originalValue ? $this->originalWidgetOptions : $this->widgetOptions;
        foreach ($widgetOptions as $widgetOption) {
            if (strtolower($widgetOption->option) == strtolower($option)) {
                return $widgetOption;
            }
        }

        throw new NotFoundException(__('Widget Option not found'));
    }

    /**
     * Remove an option
     * @param string $option
     * @return $this
     */
    public function removeOption(string $option): Widget
    {
        try {
            $widgetOption = $this->getOption($option);

            $this->getLog()->debug('removeOption: ' . $option);

            // Unassign
            foreach ($this->widgetOptions as $key => $value) {
                if ($value->option === $option) {
                    unset($this->widgetOptions[$key]);
                }
            }

            // Delete now
            $widgetOption->delete();
        } catch (NotFoundException $exception) {
            // This is good, notihng to do.
        }
        return $this;
    }

    /**
     * Change an option
     * @param string $option
     * @param string $newOption
     * @return $this
     */
    public function changeOption(string $option, string $newOption): Widget
    {
        try {
            $widgetOption = $this->getOption($option);

            $this->getLog()->debug('changeOption: ' . $option);

            // Unassign
            foreach ($this->widgetOptions as $key => $value) {
                if ($value->option === $option) {
                    unset($this->widgetOptions[$key]);
                }
            }

            // Change now
            $widgetOption->delete();
            $this->widgetOptions[] = $this->widgetOptionFactory->create($this->widgetId, $widgetOption->type, $newOption, $widgetOption->value);

        } catch (NotFoundException $exception) {
            // This is good, nothing to do.
        }
        return $this;
    }

    /**
     * Get Widget Option Value
     * @param string $option
     * @param mixed $default
     * @param bool $originalValue
     * @return mixed
     */
    public function getOptionValue(string $option, $default, bool $originalValue = false)
    {
        try {
            $widgetOption = $this->getOption($option, $originalValue);
            $widgetOption = (($widgetOption->value) === null) ? $default : $widgetOption->value;

            if (is_integer($default)) {
                $widgetOption = intval($widgetOption);
            }

            return $widgetOption;
        } catch (NotFoundException $e) {
            return $default;
        }
    }

    /**
     * Set Widget Option Value
     * @param string $option
     * @param string $type
     * @param mixed $value
     */
    public function setOptionValue(string $option, string $type, $value)
    {
        $this->getLog()->debug('setOptionValue: ' . $option . ', ' . $type . '. Value = ' . $value);
        try {
            $widgetOption = $this->getOption($option);
            $widgetOption->type = $type;
            $widgetOption->value = $value;
        } catch (NotFoundException $e) {
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
     * Get the temporary path
     * @return string
     */
    public function getLibraryTempPath(): string
    {
        return $this->widgetMediaFactory->getLibraryTempPath();
    }

    /**
     * Get the path of the primary media
     * @return string
     * @throws NotFoundException
     */
    public function getPrimaryMediaPath(): string
    {
        return $this->widgetMediaFactory->getPathForMediaId($this->getPrimaryMediaId());
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
        return ($this->toDt !== self::$DATE_MAX && Carbon::createFromTimestamp($this->toDt)->format('U') < Carbon::now()->format('U'));
    }

    /**
     * Calculates the duration of this widget according to some rules
     * @param \Xibo\Entity\Module $module
     * @param bool $import
     * @return $this
     */
    public function calculateDuration(
        Module $module,
        bool $import = false
    ): Widget {
        $this->getLog()->debug('calculateDuration: Calculating for ' . $this->type
            . ' - existing value is ' . $this->calculatedDuration
            . ' import is ' . ($import ? 'true' : 'false'));

        // Import
        // ------
        // If we are importing a layout we need to adjust the `duration` **before** we pass to any duration
        // provider, as providers will use the duration set on the widget in their calculations.
        // $this->duration from xml is `duration * (numItems/itemsPerPage)`
        if ($import) {
            $numItems = $this->getOptionValue('numItems', 1);
            if ($this->getOptionValue('durationIsPerItem', 0) == 1 && $numItems > 1) {
                // If we have paging involved then work out the page count.
                $itemsPerPage = $this->getOptionValue('itemsPerPage', 0);
                if ($itemsPerPage > 0) {
                    $numItems = ceil($numItems / $itemsPerPage);
                }

                // This is a change to v3
                //  in v3 we only divide by numItems if useDuration = 0, which I think was wrong.
                $this->duration = ($this->useDuration == 1 ? $this->duration : $module->defaultDuration) / $numItems;
            }
        }

        // Start with either the default module duration, or the duration provided
        if ($this->useDuration == 1) {
            // Widget duration is as specified
            $this->calculatedDuration = $this->duration;
        } else {
            // Use the default duration
            $this->calculatedDuration = $module->defaultDuration;
        }

        // Modify the duration if necessary
        if ($module->type === 'subplaylist') {
            // Sub Playlists are a special case and provide their own duration.
            $this->getLog()->debug('calculateDuration: subplaylist using SubPlaylistDurationEvent');

            $event = new SubPlaylistDurationEvent($this);
            $this->getDispatcher()->dispatch($event, SubPlaylistDurationEvent::$NAME);
            $this->calculatedDuration = $event->getDuration();
        } else {
            // Our module will calculate the duration for us.
            $duration = $module->calculateDuration($this);
            if ($duration !== null) {
                $this->calculatedDuration = $duration;
            } else {
                $this->getLog()->debug('calculateDuration: Duration not set by module');
            }
        }

        $this->getLog()->debug('calculateDuration: set to ' . $this->calculatedDuration);
        return $this;
    }

    /**
     * @return int
     * @throws NotFoundException
     */
    public function getDurationForMedia(): int
    {
        return $this->widgetMediaFactory->getDurationForMediaId($this->getPrimaryMediaId());
    }

    /**
     * Load the Widget
     * @param bool $loadActions
     * @return Widget
     */
    public function load(bool $loadActions = true): Widget
    {
        if ($this->loaded || $this->widgetId == null || $this->widgetId == 0) {
            return $this;
        }

        // Load permissions
        $this->permissions = $this->permissionFactory->getByObjectId(get_class(), $this->widgetId);

        // Load the widget options
        $this->widgetOptions = $this->widgetOptionFactory->getByWidgetId($this->widgetId);
        foreach ($this->widgetOptions as $widgetOption) {
            $this->originalWidgetOptions[] = clone $widgetOption;
        }

        // Load any media assignments for this widget
        $this->mediaIds = $this->widgetMediaFactory->getByWidgetId($this->widgetId);
        $this->originalMediaIds = $this->mediaIds;

        // Load any widget audio assignments
        $this->audio = $this->widgetAudioFactory->getByWidgetId($this->widgetId);
        $this->originalAudio = $this->audio;

        if ($loadActions) {
            $this->actions = $this->actionFactory->getBySourceAndSourceId('widget', $this->widgetId);
        }

        $this->hash = $this->hash();
        $this->mediaHash = $this->mediaHash();
        $this->loaded = true;
        return $this;
    }

    /**
     * Load the Widget with minimal data i.e., options
     */
    public function loadMinimum(): void
    {
        if ($this->loaded || $this->widgetId == null || $this->widgetId == 0) {
            return;
        }

        // Load the widget options
        $this->widgetOptions = $this->widgetOptionFactory->getByWidgetId($this->widgetId);
        foreach ($this->widgetOptions as $widgetOption) {
            $this->originalWidgetOptions[] = clone $widgetOption;
        }

        $this->loaded = true;
    }

    /**
     * @param Property[] $properties
     * @return \Xibo\Entity\Widget
     */
    public function applyProperties(array $properties): Widget
    {
        foreach ($properties as $property) {
            // Do not save null properties.
            if ($property->value === null) {
                $this->removeOption($property->id);
            } else {
                // Apply filters
                $property->applyFilters();

                // Set the property for saving into the widget options
                $this->setOptionValue($property->id, $property->isCData() ? 'cdata' : 'attrib', $property->value);

                // If this property allows library references to be added, we parse them out here and assign
                // the matching media to the widget.
                if ($property->allowLibraryRefs) {
                    // Parse them out and replace for our special syntax.
                    $matches = [];
                    preg_match_all('/\[(.*?)\]/', $property->value, $matches);
                    foreach ($matches[1] as $match) {
                        if (is_numeric($match)) {
                            $this->assignMedia(intval($match));
                        }
                    }
                }

                // Is this a media selector? and if so should we assign the library media
                if ($property->type === 'mediaSelector') {
                    if (!empty($value) && is_numeric($value)) {
                        $this->assignMedia(intval($value));
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Save the widget
     * @param array $options
     * @throws \Xibo\Support\Exception\NotFoundException
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
            'auditWidgetOptions' => true,
            'auditMessage' => 'Saved',
            'alwaysUpdate' => false,
            'import' => false,
            'upgrade' => false,
        ], $options);

        $this->getLog()->debug('Saving widgetId ' . $this->getId() . ' with options. '
            . json_encode($options, JSON_PRETTY_PRINT));

        // if we are auditing get layout specific campaignId
        $campaignId = 0;
        $layoutId = 0;
        if ($options['audit']) {
            $results = $this->store->select('
                SELECT `campaign`.campaignId,
                    `layout`.layoutId
                  FROM `playlist`
                    INNER JOIN `region`
                    ON `playlist`.regionId = `region`.regionId
                    INNER JOIN `layout`
                    ON `region`.layoutId = `layout`.layoutId
                    INNER JOIN `lkcampaignlayout`
                    ON `layout`.layoutId = `lkcampaignlayout`.layoutId
                    INNER JOIN `campaign`
                    ON `campaign`.campaignId = `lkcampaignlayout`.campaignId
                 WHERE `campaign`.isLayoutSpecific = 1
                    AND `playlist`.playlistId = :playlistId
            ', [
                'playlistId' => $this->playlistId
            ]);

            foreach ($results as $row) {
                $campaignId = intval($row['campaignId']);
                $layoutId = intval($row['layoutId']);
            }
        }

        // Add/Edit
        $isNew = $this->widgetId == null || $this->widgetId == 0;
        if ($isNew) {
            $this->add();
        } else {
            // When saving after Widget compatibility upgrade
            // do not trigger this event, as it will throw an error
            // this is due to mismatch between playlist closure table (already populated)
            // and subPlaylists option original values (empty array) - attempt to add the same child will error out.
            if (!$options['upgrade']) {
                $this->getDispatcher()->dispatch(new WidgetEditEvent($this), WidgetEditEvent::$NAME);
            }

            if ($this->hash != $this->hash() || $options['alwaysUpdate']) {
                $this->update();
            }
        }

        // Save the widget options
        if ($options['saveWidgetOptions']) {
            foreach ($this->widgetOptions as $widgetOption) {
                // Assert the widgetId
                $widgetOption->widgetId = $this->widgetId;
                $widgetOption->save();
            }
        }

        // Save the widget audio
        if ($options['saveWidgetAudio']) {
            foreach ($this->audio as $audio) {
                // Assert the widgetId
                $audio->widgetId = $this->widgetId;
                $audio->save();
            }

            $removedAudio = array_udiff($this->originalAudio, $this->audio, function ($a, $b) {
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
                if ($campaignId != 0 && $layoutId != 0) {
                    $this->audit($this->widgetId, 'Added', [
                        'widgetId' => $this->widgetId,
                        'type' => $this->type,
                        'layoutId' => $layoutId,
                        'campaignId' => $campaignId
                    ]);
                }
            } else {
                // For elements, do not try to look up changed properties.
                $changedProperties = $options['auditWidgetOptions'] ? $this->getChangedProperties() : [];
                $changedItems = [];

                if ($options['auditWidgetOptions']) {
                    foreach ($this->widgetOptions as $widgetOption) {
                        $itemsProperties = $widgetOption->getChangedProperties();

                        // for widget options what we get from getChangedProperities is an array with value as key and
                        // changed value as value we want to override the key in the returned array, so that we get a
                        // clear option name that was changed
                        if (array_key_exists('value', $itemsProperties)) {
                            $itemsProperties[$widgetOption->option] = $itemsProperties['value'];
                            unset($itemsProperties['value']);
                        }

                        if (count($itemsProperties) > 0) {
                            $changedItems[] = $itemsProperties;
                        }
                    }
                }

                if (count($changedItems) > 0) {
                    $changedProperties['widgetOptions'] = json_encode($changedItems, JSON_PRETTY_PRINT);
                }

                // if we are editing a widget assigned to a regionPlaylist add the layout specific campaignId to
                // the audit log
                if ($campaignId != 0 && $layoutId != 0) {
                    $changedProperties['campaignId'][] = $campaignId;
                    $changedProperties['layoutId'][] = $layoutId;
                }

                $this->audit($this->widgetId, $options['auditMessage'], $changedProperties);
            }
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

        // Widget Delete Event
        $this->getDispatcher()->dispatch(new WidgetDeleteEvent($this), WidgetDeleteEvent::$NAME);

        // Delete Permissions
        foreach ($this->permissions as $permission) {
            $permission->deleteAll();
        }

        // Delete all Options
        foreach ($this->widgetOptions as $widgetOption) {
            // Assert the widgetId
            $widgetOption->widgetId = $this->widgetId;
            $widgetOption->delete();
        }

        // Delete the widget audio
        foreach ($this->audio as $audio) {
            // Assert the widgetId
            $audio->widgetId = $this->widgetId;
            $audio->delete();
        }

        foreach ($this->actions as $action) {
            $action->delete();
        }

        // Set widgetId to null on any navWidget action that was using this drawer Widget.
        $this->getStore()->update(
            'UPDATE `action` SET `action`.widgetId = NULL
                WHERE widgetId = :widgetId AND `action`.actionType = \'navWidget\' ',
            ['widgetId' => $this->widgetId]
        );
        
        // Unlink Media
        $this->mediaIds = [];
        $this->unlinkMedia();

        // Delete any fallback data
        $this->getStore()->update('DELETE FROM `widgetdata` WHERE `widgetId` = :widgetId', [
            'widgetId' => $this->widgetId,
        ]);

        // Delete this
        $this->getStore()->update('DELETE FROM `widget` WHERE `widgetId` = :widgetId', [
            'widgetId' => $this->widgetId,
        ]);

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
                'modifiedDt' => Carbon::now()->format(DateFormatHelper::getSystemFormat())
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
                'modifiedDt' => Carbon::now()->format(DateFormatHelper::getSystemFormat())
            ]);
        }

        // Notify any displays (clearing their cache)
        // this is typically done when there has been a dynamic change to the Widget - i.e. the Layout doesn't need
        // to be rebuilt, but the Widget has some change that will be pushed out through getResource
        if ($options['notifyDisplays']) {
            $this->getDisplayNotifyService()->collectNow()->notifyByPlaylistId($this->playlistId);
        }
    }

    private function add()
    {
        $this->getLog()->debug('Adding Widget ' . $this->type . ' to PlaylistId ' . $this->playlistId);

        $this->isNew = true;

        $sql = '
            INSERT INTO `widget` (`playlistId`, `ownerId`, `type`, `duration`, `displayOrder`, `useDuration`, `calculatedDuration`, `fromDt`, `toDt`, `createdDt`, `modifiedDt`, `schemaVersion`)
            VALUES (:playlistId, :ownerId, :type, :duration, :displayOrder, :useDuration, :calculatedDuration, :fromDt, :toDt, :createdDt, :modifiedDt, :schemaVersion)
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
            'createdDt' => ($this->createdDt === null) ? Carbon::now()->format('U') : $this->createdDt,
            'modifiedDt' => Carbon::now()->format('U'),
            'schemaVersion' => $this->schemaVersion
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
            `modifiedDt` = :modifiedDt,
            `schemaVersion` = :schemaVersion
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
            'modifiedDt' => Carbon::now()->format('U'),
            'schemaVersion' => $this->schemaVersion
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

        if (count($mediaToUnlink) <= 0) {
            return;
        }

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
