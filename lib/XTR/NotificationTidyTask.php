<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */


namespace Xibo\XTR;

use Carbon\Carbon;
use Xibo\Service\LibraryAsset;
use Xibo\Service\LibraryStorageRegistryInterface;
use Xibo\Storage\LibraryFileJournal;

/**
 * Class NotificationTidyTask
 * @package Xibo\XTR
 */
class NotificationTidyTask implements TaskInterface
{
    use TaskTrait;

    /** @var LibraryStorageRegistryInterface */
    private $libraryStorageRegistry;

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->libraryStorageRegistry = $container->get('libraryStorageRegistry');
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
            'releaseDt' => Carbon::now()->subDays($maxAgeDays)->format('U')
        ];

        // With readOnly, a notification goes once no recipient has it unread, including one with no recipients.
        $hasNoUnreadRecipientFilter = ' AND `notificationId` NOT IN
            (SELECT `notificationId` FROM `lknotificationuser` WHERE `read` = 0) ';

        // Delete all notifications older than now minus X days
        $sql = '
        DELETE FROM `lknotificationdg` 
           WHERE `notificationId` IN (SELECT DISTINCT `notificationId` FROM `notification` ' . $where . ')
        ';

        if ($readOnly == 1) {
            $sql .= $hasNoUnreadRecipientFilter;
        }

        $this->store->update($sql, $params);

        // Delete notification groups
        $sql = '
        DELETE FROM `lknotificationgroup` 
           WHERE `notificationId` IN (SELECT DISTINCT `notificationId` FROM `notification` ' . $where . ')
        ';

        if ($readOnly == 1) {
            $sql .= $hasNoUnreadRecipientFilter;
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

        // With readOnly, notifications still linked to a user are kept.
        $hasNoRecipientFilter = ($readOnly == 1)
            ? ' AND `notificationId` NOT IN (SELECT `notificationId` FROM `lknotificationuser`) '
            : '';

        // Collect the attached files before the rows go
        $filenames = [];
        $sql = 'SELECT filename FROM `notification` ' . $where . $hasNoRecipientFilter;
        foreach ($this->store->select($sql, $params) as $row) {
            if (!empty($row['filename'])) {
                $filenames[] = $row['filename'];
            }
        }

        // Delete from notification
        $this->store->update('DELETE FROM `notification` ' . $where . $hasNoRecipientFilter, $params);

        // Delete the attachments after commit so a failed run keeps them.
        foreach ($filenames as $filename) {
            LibraryFileJournal::deleteFileAfterCommit(
                $this->store,
                $this->libraryStorageRegistry,
                LibraryAsset::ATTACHMENT,
                $filename,
                $this->log->getLoggerInterface()
            );
        }

        $this->runMessage .= __('Done') . PHP_EOL . PHP_EOL;
    }
}
