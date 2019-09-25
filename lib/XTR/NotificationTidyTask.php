<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (NotificationTidyTask.php)
 */


namespace Xibo\XTR;

/**
 * Class NotificationTidyTask
 * @package Xibo\XTR
 */
class NotificationTidyTask implements TaskInterface
{
    use TaskTrait;

    /** @inheritdoc */
    public function setFactories($container)
    {
        // No factories required
        return $this;
    }

    /** @inheritdoc */
    public function run()
    {
        // Delete notifications older than X days
        $maxAgeDays = intval($this->getOption('maxAgeDays', 7));
        $systemOnly = intval($this->getOption('systemOnly', 1));
        $readOnly = intval($this->getOption('readOnly', 0));

        $this->runMessage = '# ' . __('Notification Tidy') . PHP_EOL . PHP_EOL;

        $this->log->info('Deleting notifications older than ' . $maxAgeDays
            . ' days. System Only: ' . $systemOnly
            . '. Read Only' . $readOnly
        );

        // Where clause
        $where = ' WHERE `releaseDt` < :releaseDt ';
        if ($systemOnly == 1) {
            $where .= ' AND `isSystem` = 1 ';
        }

        // Params for all deletes
        $params = [
            'releaseDt' => $this->date->parse()->subDays($maxAgeDays)->format('U')
        ];

        // Delete all notifications older than now minus X days
        $sql = '
        DELETE FROM `lknotificationdg` 
           WHERE `notificationId` IN (SELECT DISTINCT `notificationId` FROM `notification` ' . $where . ')
        ';

        if ($readOnly == 1) {
            $sql .= ' AND `notificationId` IN (SELECT `notificationId` FROM `lknotificationuser` WHERE read <> 0) ';
        }

        $this->store->update($sql, $params);

        // Delete notification groups
        $sql = '
        DELETE FROM `lknotificationgroup` 
           WHERE `notificationId` IN (SELECT DISTINCT `notificationId` FROM `notification` ' . $where . ')
        ';

        if ($readOnly == 1) {
            $sql .= ' AND `notificationId` IN (SELECT `notificationId` FROM `lknotificationuser` WHERE read <> 0) ';
        }

        $this->store->update($sql, $params);

        // Delete from notification user
        $sql = '
        DELETE FROM `lknotificationuser` 
           WHERE `notificationId` IN (SELECT DISTINCT `notificationId` FROM `notification` ' . $where . ')
        ';

        if ($readOnly == 1) {
            $sql .= ' AND `read` <> 0 ';
        }

        $this->store->update($sql, $params);

        // Remove the attached file
        $sql = 'SELECT filename  FROM `notification` ' . $where;

        foreach ($this->store->select($sql, $params) as $row) {
            $filename = $row['filename'];

            /*Delete the attachment*/
            if (!empty($filename)) {
                // Library location
                $attachmentLocation = $this->config->getSetting('LIBRARY_LOCATION'). 'attachment/';
                if (file_exists($attachmentLocation . $filename)) {
                    unlink($attachmentLocation . $filename);
                }
            }
        }

        // Delete from notification
        $sql = 'DELETE FROM `notification` ' . $where;

        if ($readOnly == 1) {
            $sql .= ' AND `notificationId` NOT IN (SELECT `notificationId` FROM `lknotificationuser`) ';
        }

        $this->store->update($sql, $params);

        $this->runMessage .= __('Done') . PHP_EOL . PHP_EOL;
    }
}