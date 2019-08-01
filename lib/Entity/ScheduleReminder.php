<?php

namespace Xibo\Entity;


use Xibo\Exception\XiboException;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ScheduleReminderFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
* Class ScheduleReminder
* @package Xibo\Entity
*
* @SWG\Definition()
*/
class ScheduleReminder implements \JsonSerializable
{
    use EntityTrait;

    public static $TYPE_MINUTE = 1;
    public static $TYPE_HOUR = 2;
    public static $TYPE_DAY = 3;
    public static $TYPE_WEEK = 4;
    public static $TYPE_MONTH = 5;

    public static $OPTION_BEFORE_START = 1;
    public static $OPTION_AFTER_START = 2;
    public static $OPTION_BEFORE_END = 3;
    public static $OPTION_AFTER_END = 4;

    public static $MINUTE = 60;
    public static $HOUR = 3600;
    public static $DAY = 86400;
    public static $WEEK = 604800;
    public static $MONTH = 30 * 86400;

    /**
     * @SWG\Property(description="Schedule Reminder ID")
     * @var int
     */
    public $scheduleReminderId;

    /**
     * @SWG\Property(description="The username of the User that owns this schedule reminder")
     * @var int
     */
    public $eventId;

    /**
     * @SWG\Property(description="Report name")
     * @var int
     */
    public $value;

    /**
     * @SWG\Property(description="Saved report generated on")
     * @var int
     */
    public $type;

    /**
     * @SWG\Property(description="Report schedule name of the schedule reminder")
     * @var int
     */
    public $option;

    /**
     * @SWG\Property(description="Report schedule Id of the schedule reminder")
     * @var int
     */
    public $isEmail;

    /**
     * @SWG\Property(description="Saved report name As")
     * @var int
     */
    public $reminderDt;

    /**
     * @var ConfigServiceInterface
     */
    private $config;

    /**
     * @var ScheduleReminderFactory
     */
    private $scheduleReminderFactory;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param ConfigServiceInterface $config
     * @param ScheduleReminderFactory $scheduleReminderFactory
     */
    public function __construct($store, $log, $config, $scheduleReminderFactory)
    {
        $this->setCommonDependencies($store, $log);

        $this->config = $config;
        $this->scheduleReminderFactory = $scheduleReminderFactory;
    }

    /**
     * Add
     */
    private function add()
    {
        $this->scheduleReminderId = $this->getStore()->insert('
            INSERT INTO `schedulereminder` (`eventId`, `value`, `type`, `option`, `reminderDt`, `isEmail`)
              VALUES (:eventId, :value, :type, :option, :reminderDt, :isEmail)
        ', [
            'eventId' => $this->eventId,
            'value' => $this->value,
            'type' => $this->type,
            'option' => $this->option,
            'reminderDt' => $this->reminderDt,
            'isEmail' => $this->isEmail,
        ]);
    }

    /**
     * Edit
     */
    private function edit()
    {
        $sql = '
          UPDATE `schedulereminder`
            SET `eventId` = :eventId,
                `type` = :type,
                `value` = :value,
                `option` = :option,
                `reminderDt` = :reminderDt,
                `isEmail` = :isEmail
           WHERE scheduleReminderId = :scheduleReminderId
        ';

        $params = [
            'eventId' => $this->eventId,
            'type' => $this->type,
            'value' => $this->value,
            'option' => $this->option,
            'reminderDt' => $this->reminderDt,
            'isEmail' => $this->isEmail,
            'scheduleReminderId' => $this->scheduleReminderId,
        ];

        $this->getStore()->update($sql, $params);
    }


    /**
     * Delete
     * @throws XiboException
     */
    public function delete()
    {
        $this->load();

        $this->getLog()->debug('Delete schedule reminder: '.$this->scheduleReminderId);
        $this->getStore()->update('DELETE FROM `schedulereminder` WHERE `scheduleReminderId` = :scheduleReminderId', [
            'scheduleReminderId' => $this->scheduleReminderId
        ]);
    }

    /**
     * Load
     */
    public function load()
    {
        if ($this->loaded || $this->scheduleReminderId == null)
            return;

        $this->loaded = true;
    }

    /**
     * Get Id
     * @return int
     */
    public function getId()
    {
        return $this->scheduleReminderId;
    }

    /**
     * Get Reminder Date
     * @return int
     */
    public function getReminderDt()
    {
        return $this->reminderDt;
    }

    /**
     * Save
     */
    public function save()
    {
        if ($this->scheduleReminderId == null || $this->scheduleReminderId == 0)
            $this->add();
        else
            $this->edit();
    }
}