<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (ScheduleFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\Schedule;
use Xibo\Exception\NotFoundException;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class ScheduleFactory
 * @package Xibo\Factory
 */
class ScheduleFactory extends BaseFactory
{
    /**
     * @var ConfigServiceInterface
     */
    private $config;

    /**
     * @var DisplayGroupFactory
     */
    private $displayGroupFactory;

    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param ConfigServiceInterface $config
     * @param DisplayGroupFactory $displayGroupFactory
     * @param DisplayFactory $displayFactory
     * @param LayoutFactory $layoutFactory
     * @param MediaFactory $mediaFactory
     */
    public function __construct($store, $log, $sanitizerService, $config, $displayGroupFactory)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
        $this->config = $config;
        $this->displayGroupFactory = $displayGroupFactory;
    }

    /**
     * Create Empty
     * @return Schedule
     */
    public function createEmpty()
    {
        return new Schedule(
            $this->getStore(),
            $this->getLog(),
            $this->config,
            $this->displayGroupFactory
        );
    }

    /**
     * @param int $eventId
     * @return Schedule
     * @throws NotFoundException
     */
    public function getById($eventId)
    {
        $events = $this->query(null, ['disableUserCheck' => 1, 'eventId' => $eventId]);

        if (count($events) <= 0)
            throw new NotFoundException();

        return $events[0];
    }

    /**
     * @param int $displayGroupId
     * @return array[Schedule]
     * @throws NotFoundException
     */
    public function getByDisplayGroupId($displayGroupId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'displayGroupIds' => [$displayGroupId]]);
    }

    /**
     * Get by Campaign ID
     * @param int $campaignId
     * @return array[Schedule]
     * @throws NotFoundException
     */
    public function getByCampaignId($campaignId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'campaignId' => $campaignId]);
    }

    /**
     * Get by OwnerId
     * @param int $ownerId
     * @return array[Schedule]
     * @throws NotFoundException
     */
    public function getByOwnerId($ownerId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'ownerId' => $ownerId]);
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[Schedule]
     */
    public function query($sortOrder = null, $filterBy = null)
    {
        $entries = [];
        $params = [];

        $useDetail = $this->getSanitizer()->getInt('useDetail', $filterBy) == 1;

        $sql = '
        SELECT `schedule`.eventId, `schedule`.eventTypeId, ';

        if ($useDetail) {
            $sql .= '
            `schedule_detail`.fromDt,
            `schedule_detail`.toDt,
            ';
        } else {
            $sql .= '
            `schedule`.fromDt,
            `schedule`.toDt,
            ';
        }

        $sql .= '
            `schedule`.userId,
            `schedule`.displayOrder,
            `schedule`.is_priority AS isPriority,
            `schedule`.recurrence_type AS recurrenceType,
            `schedule`.recurrence_detail AS recurrenceDetail,
            `schedule`.recurrence_range AS recurrenceRange,
            campaign.campaignId,
            campaign.campaign,
            `command`.commandId,
            `command`.command,
            `schedule`.dayPartId
          FROM `schedule`
            LEFT OUTER JOIN `campaign`
            ON campaign.CampaignID = `schedule`.CampaignID
            LEFT OUTER JOIN `command`
            ON `command`.commandId = `schedule`.commandId
        ';

        if ($useDetail) {
            $sql .= '
            INNER JOIN `schedule_detail`
            ON schedule_detail.EventID = `schedule`.EventID
            ';
        }

        $sql .= '
          WHERE 1 = 1
        ';

        if ($this->getSanitizer()->getInt('eventId', $filterBy) !== null) {
            $sql .= ' AND `schedule`.eventId = :eventId ';
            $params['eventId'] = $this->getSanitizer()->getInt('eventId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('campaignId', $filterBy) !== null) {
            $sql .= ' AND `schedule`.campaignId = :campaignId ';
            $params['campaignId'] = $this->getSanitizer()->getInt('campaignId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('ownerId', $filterBy) !== null) {
            $sql .= ' AND `schedule`.userId = :ownerId ';
            $params['ownerId'] = $this->getSanitizer()->getInt('ownerId', $filterBy);
        }

        // Only 1 date
        if (!$useDetail && $this->getSanitizer()->getInt('fromDt', $filterBy) !== null && $this->getSanitizer()->getInt('toDt', $filterBy) === null) {
            $sql .= ' AND schedule.fromDt > :fromDt ';
            $params['fromDt'] = $this->getSanitizer()->getInt('fromDt', $filterBy);
        }

        if (!$useDetail && $this->getSanitizer()->getInt('toDt', $filterBy) !== null && $this->getSanitizer()->getInt('fromDt', $filterBy) === null) {
            $sql .= ' AND IFNULL(schedule.toDt, schedule.fromDt) <= :toDt ';
            $params['toDt'] = $this->getSanitizer()->getInt('toDt', $filterBy);
        }

        if ($useDetail && $this->getSanitizer()->getInt('fromDt', $filterBy) !== null && $this->getSanitizer()->getInt('toDt', $filterBy) === null) {
            $sql .= ' AND schedule_detail.fromDt > :fromDt ';
            $params['fromDt'] = $this->getSanitizer()->getInt('fromDt', $filterBy);
        }

        if ($useDetail && $this->getSanitizer()->getInt('toDt', $filterBy) !== null && $this->getSanitizer()->getInt('fromDt', $filterBy) === null) {
            $sql .= ' AND IFNULL(schedule_detail.toDt, schedule_detail.fromDt) <= :toDt ';
            $params['toDt'] = $this->getSanitizer()->getInt('toDt', $filterBy);
        }
        // End only 1 date

        // Both dates
        if (!$useDetail && $this->getSanitizer()->getInt('fromDt', $filterBy) !== null && $this->getSanitizer()->getInt('toDt', $filterBy) !== null) {
            $sql .= ' AND schedule.fromDt > :fromDt ';
            $sql .= ' AND IFNULL(schedule.toDt, schedule.fromDt) <= :toDt ';
            $params['fromDt'] = $this->getSanitizer()->getInt('fromDt', $filterBy);
            $params['toDt'] = $this->getSanitizer()->getInt('toDt', $filterBy);
        }

        if ($useDetail && $this->getSanitizer()->getInt('fromDt', $filterBy) !== null && $this->getSanitizer()->getInt('toDt', $filterBy) !== null) {
            $sql .= ' AND schedule_detail.fromDt < :toDt ';
            $sql .= ' AND IFNULL(schedule_detail.toDt, schedule_detail.fromDt) >= :fromDt ';
            $params['fromDt'] = $this->getSanitizer()->getInt('fromDt', $filterBy);
            $params['toDt'] = $this->getSanitizer()->getInt('toDt', $filterBy);
        }
        // End both dates

        if ($this->getSanitizer()->getIntArray('displayGroupIds', $filterBy) != null) {
            $sql .= ' AND `schedule`.eventId IN (SELECT `lkscheduledisplaygroup`.eventId FROM `lkscheduledisplaygroup` WHERE displayGroupId IN (' . implode(',', $this->getSanitizer()->getIntArray('displayGroupIds', $filterBy)) . ')) ';
        }

        // Sorting?
        if (is_array($sortOrder))
            $sql .= 'ORDER BY ' . implode(',', $sortOrder);

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row, ['intProperties' => ['isPriority']]);
        }

        return $entries;
    }
}