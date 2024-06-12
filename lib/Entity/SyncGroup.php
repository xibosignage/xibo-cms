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
use Respect\Validation\Validator as v;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Factory\SyncGroupFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * @SWG\Definition()
 */
class SyncGroup implements \JsonSerializable
{
    use EntityTrait;
    /**
     * @SWG\Property(description="The ID of this Entity")
     * @var int
     */
    public $syncGroupId;
    /**
     * @SWG\Property(description="The name of this Entity")
     * @var string
     */
    public $name;
    /**
     * @SWG\Property(description="The datetime this entity was created")
     * @var string
     */
    public $createdDt;
    /**
     * @SWG\Property(description="The datetime this entity was last modified")
     * @var ?string
     */
    public $modifiedDt;
    /**
     * @SWG\Property(description="The ID of the user that last modified this sync group")
     * @var int
     */
    public $modifiedBy;
    /**
     * @SWG\Property(description="The name of the user that last modified this sync group")
     * @var string
     */
    public $modifiedByName;
    /**
     * @SWG\Property(description="The ID of the owner of this sync group")
     * @var int
     */
    public $ownerId;
    /**
     * @SWG\Property(description="The name of the owner of this sync group")
     * @var string
     */
    public $owner;
    /**
     * @SWG\Property(description="The publisher port number")
     * @var int
     */
    public $syncPublisherPort = 9590;
    /**
     * @SWG\Property(description="The delay (in ms) when displaying the changes in content")
     * @var int
     */
    public $syncSwitchDelay = 750;
    /**
     * @SWG\Property(description="The delay (in ms) before unpausing the video on start.")
     * @var int
     */
    public $syncVideoPauseDelay = 100;
    /**
     * @SWG\Property(description="The ID of the lead Display for this sync group")
     * @var int
     */
    public $leadDisplayId;
    /**
     * @SWG\Property(description="The name of the lead Display for this sync group")
     * @var string
     */
    public $leadDisplay;
    /**
     * @SWG\Property(description="The id of the Folder this Sync Group belongs to")
     * @var int
     */
    public $folderId;

    /**
     * @SWG\Property(description="The id of the Folder responsible for providing permissions for this Sync Group")
     * @var int
     */
    public $permissionsFolderId;


    private SyncGroupFactory $syncGroupFactory;
    private DisplayFactory $displayFactory;
    private PermissionFactory $permissionFactory;
    private $permissions = [];
    private ScheduleFactory $scheduleFactory;

    /**
     * @param $store
     * @param $log
     * @param $dispatcher
     * @param SyncGroupFactory $syncGroupFactory
     * @param DisplayFactory $displayFactory
     */
    public function __construct(
        $store,
        $log,
        $dispatcher,
        SyncGroupFactory $syncGroupFactory,
        DisplayFactory $displayFactory,
        PermissionFactory $permissionFactory,
        ScheduleFactory $scheduleFactory
    ) {
        $this->setCommonDependencies($store, $log, $dispatcher);
        $this->setPermissionsClass('Xibo\Entity\SyncGroup');
        $this->syncGroupFactory = $syncGroupFactory;
        $this->displayFactory = $displayFactory;
        $this->permissionFactory = $permissionFactory;
        $this->scheduleFactory = $scheduleFactory;
    }

    /**
     * @return Display[]
     * @throws NotFoundException
     */
    public function getSyncGroupMembers(): array
    {
        return $this->displayFactory->getBySyncGroupId($this->syncGroupId);
    }

    /**
     * @return array
     */
    public function getGroupMembersForForm(): array
    {
        return $this->getStore()->select('SELECT `display`.displayId, `display`.display, `display`.syncGroupId, `syncgroup`.leadDisplayId, `displaygroup`.displayGroupId
        FROM `display` 
            INNER JOIN `syncgroup` ON `syncgroup`.syncGroupId = `display`.syncGroupId
            INNER JOIN `lkdisplaydg` ON lkdisplaydg.displayid = display.displayId
            INNER JOIN `displaygroup` ON displaygroup.displaygroupid = lkdisplaydg.displaygroupid AND `displaygroup`.isDisplaySpecific = 1
        WHERE `display`.syncGroupId = :syncGroupId
        ORDER BY IF(`syncgroup`.leadDisplayId = `display`.displayId, 0, 1), displayId', [
            'syncGroupId' => $this->syncGroupId
        ]);
    }

    public function getGroupMembersForEditForm($eventId): array
    {
        return $this->getStore()->select('SELECT `display`.displayId, `display`.display, `display`.syncGroupId, `syncgroup`.leadDisplayId, `schedule_sync`.layoutId, `displaygroup`.displayGroupId
        FROM `display`
            INNER JOIN `syncgroup` ON `syncgroup`.syncGroupId = `display`.syncGroupId
            INNER JOIN `schedule_sync` ON `schedule_sync`.displayId = `display`.displayId
            INNER JOIN `lkdisplaydg` ON lkdisplaydg.displayid = display.displayId
            INNER JOIN `displaygroup` ON displaygroup.displaygroupid = lkdisplaydg.displaygroupid AND `displaygroup`.isDisplaySpecific = 1
        WHERE `display`.syncGroupId = :syncGroupId AND `schedule_sync`.eventId = :eventId
        ORDER BY IF(`syncgroup`.leadDisplayId = `display`.displayId, 0, 1), displayId', [
            'syncGroupId' => $this->syncGroupId,
            'eventId' => $eventId
        ]);
    }

    public function getLayoutIdForDisplay(int $eventId, int $displayId)
    {
        $layout = $this->getStore()->select('SELECT `schedule_sync`.layoutId 
            FROM `display` 
                INNER JOIN `schedule_sync` ON `schedule_sync`.displayId = `display`.displayId 
            WHERE `display`.syncGroupId = :syncGroupId AND `schedule_sync`.eventId = :eventId AND `schedule_sync`.displayId = :displayId', [
            'eventId' => $eventId,
            'displayId' => $displayId,
            'syncGroupId' => $this->syncGroupId
        ]);

        if (count($layout) <= 0) {
            return null;
        }

        return $layout[0]['layoutId'];
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->syncGroupId;
    }

    /**
     * @return int
     */
    public function getPermissionFolderId(): int
    {
        return $this->permissionsFolderId;
    }

    /**
     * @return int
     */
    public function getOwnerId(): int
    {
        return $this->ownerId;
    }

    /**
     * Set the owner of this group
     * @param $userId
     */
    public function setOwner($userId): void
    {
        $this->ownerId = $userId;
    }

    /**
     * Load the contents for this display group
     * @param array $options
     * @throws NotFoundException
     */
    public function load($options = [])
    {
        $options = array_merge([], $options);

        if ($this->loaded || $this->syncGroupId == null || $this->syncGroupId == 0) {
            return;
        }

        $this->permissions = $this->permissionFactory->getByObjectId(get_class($this), $this->syncGroupId);

        // We are loaded
        $this->loaded = true;
    }

    /**
     * @param $options
     * @return void
     * @throws InvalidArgumentException
     */
    public function save($options = []): void
    {
        $options = array_merge([
            'validate' => true,
        ], $options);

        if ($options['validate']) {
            $this->validate();
        }

        if (!isset($this->syncGroupId)) {
            $this->add();
        } else {
            $this->edit();
        }
    }

    /**
     * @return void
     * @throws InvalidArgumentException
     */
    public function validate(): void
    {
        if (!v::stringType()->notEmpty()->validate($this->name)) {
            throw new InvalidArgumentException(__('Name cannot be empty'), 'name');
        }

        if ($this->syncPublisherPort <= 0 || $this->syncPublisherPort === null) {
            throw new InvalidArgumentException(__('Sync Publisher Port cannot be empty'), 'syncPublisherPort');
        }

        if (!isset($this->leadDisplayId) && isset($this->syncGroupId)) {
            throw new InvalidArgumentException(__('Please select lead Display for this sync group'), 'leadDisplayId');
        }

        if ($this->syncSwitchDelay < 0) {
            throw new InvalidArgumentException(__('Switch Delay value cannot be negative'), 'syncSwitchDelay');
        }

        if ($this->syncVideoPauseDelay < 0) {
            throw new InvalidArgumentException(__('Video Pause Delay value cannot be negative'), 'syncVideoPauseDelay');
        }
    }

    public function validateForSchedule(SanitizerInterface $sanitizer)
    {
        foreach ($this->getSyncGroupMembers() as $display) {
            if (empty($sanitizer->getInt('layoutId_' . $display->displayId))) {
                $this->getLog()->error('Sync Event : Missing Layout for DisplayID ' . $display->displayId);
                throw new InvalidArgumentException(
                    __('Please make sure to select a Layout for all Displays in this Sync Group.')
                );
            }
        }
    }

    private function add(): void
    {
        $time = Carbon::now()->format(DateFormatHelper::getSystemFormat());

        $this->syncGroupId = $this->getStore()->insert('
          INSERT INTO syncgroup (`name`, `createdDt`, `modifiedDt`, `ownerId`, `modifiedBy`, `syncPublisherPort`, `syncSwitchDelay`, `syncVideoPauseDelay`, `folderId`, `permissionsFolderId`)
            VALUES (:name, :createdDt, :modifiedDt, :ownerId, :modifiedBy, :syncPublisherPort, :syncSwitchDelay, :syncVideoPauseDelay, :folderId, :permissionsFolderId)
        ', [
            'name' => $this->name,
            'createdDt' => $time,
            'modifiedDt' => null,
            'modifiedBy' => $this->modifiedBy,
            'ownerId' => $this->ownerId,
            'syncPublisherPort' => $this->syncPublisherPort,
            'syncSwitchDelay' => $this->syncSwitchDelay,
            'syncVideoPauseDelay' => $this->syncVideoPauseDelay,
            'folderId' => $this->folderId,
            'permissionsFolderId' => $this->permissionsFolderId
        ]);
    }

    private function edit(): void
    {
        $this->getLog()->debug(sprintf('Updating Sync Group. %s, %d', $this->name, $this->syncGroupId));
        $time = Carbon::now()->format(DateFormatHelper::getSystemFormat());

        $this->getStore()->update('
          UPDATE syncgroup
            SET `name` = :name,
              `modifiedDt` = :modifiedDt,
              `ownerId` = :ownerId,
              `modifiedBy` = :modifiedBy,
              `syncPublisherPort` = :syncPublisherPort,
              `syncSwitchDelay` = :syncSwitchDelay,
              `syncVideoPauseDelay` = :syncVideoPauseDelay,
              `leadDisplayId` = :leadDisplayId,
              `folderId` = :folderId,
              `permissionsFolderId` = :permissionsFolderId
           WHERE syncGroupId = :syncGroupId
          ', [
            'name' => $this->name,
            'modifiedDt' => $time,
            'ownerId' => $this->ownerId,
            'modifiedBy' => $this->modifiedBy,
            'syncPublisherPort' => $this->syncPublisherPort,
            'syncSwitchDelay' => $this->syncSwitchDelay,
            'syncVideoPauseDelay' => $this->syncVideoPauseDelay,
            'leadDisplayId' => $this->leadDisplayId == 0 ? null : $this->leadDisplayId,
            'folderId' => $this->folderId,
            'permissionsFolderId' => $this->permissionsFolderId,
            'syncGroupId' => $this->syncGroupId,
        ]);
    }

    /**
     * @return void
     * @throws NotFoundException
     */
    public function delete(): void
    {
        // unlink Displays from this syncGroup
        foreach ($this->getSyncGroupMembers() as $display) {
            $this->getStore()->update('UPDATE `display` SET `display`.syncGroupId = NULL WHERE `display`.displayId = :displayId', [
                'displayId' => $display->displayId
            ]);
        }

        // go through events using this syncGroupId and remove them
        // this will also remove links in schedule_sync table
        foreach ($this->scheduleFactory->getBySyncGroupId($this->syncGroupId) as $event) {
            $event->delete();
        }

        $this->getStore()->update('DELETE FROM `syncgroup` WHERE `syncgroup`.syncGroupId = :syncGroupId', [
            'syncGroupId' => $this->syncGroupId
        ]);
    }

    /**
     * @param array $displayIds
     * @return void
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function setMembers(array $displayIds): void
    {
        foreach ($displayIds as $displayId) {
            $display = $this->displayFactory->getById($displayId);

            if (empty($display->syncGroupId)) {
                $this->getStore()->update('UPDATE `display` SET `display`.syncGroupId = :syncGroupId WHERE `display`.displayId = :displayId', [
                    'syncGroupId' => $this->syncGroupId,
                    'displayId' => $display->displayId
                ]);

                $display->notify();
            } else if (!empty($display->syncGroupId) && $display->syncGroupId !== $this->syncGroupId) {
                throw new InvalidArgumentException(
                    sprintf(
                        __('Display %s already belongs to a different sync group ID %d'),
                        $display->display,
                        $display->syncGroupId
                    )
                );
            }
        }
    }

    /**
     * @param array $displayIds
     * @return void
     * @throws NotFoundException
     */
    public function unSetMembers(array $displayIds): void
    {
        foreach ($displayIds as $displayId) {
            $display = $this->displayFactory->getById($displayId);

            if ($display->syncGroupId === $this->syncGroupId) {
                $this->getStore()->update('UPDATE `display` SET `display`.syncGroupId = NULL WHERE `display`.displayId = :displayId', [
                    'displayId' => $display->displayId
                ]);

                $this->getStore()->update(' DELETE FROM `schedule_sync` WHERE `schedule_sync`.displayId = :displayId
                    AND `schedule_sync`.eventId IN (SELECT eventId FROM schedule WHERE schedule.syncGroupId = :syncGroupId)', [
                    'displayId' => $display->displayId,
                    'syncGroupId' => $this->syncGroupId
                ]);
            }

            $display->notify();
        }
    }
}