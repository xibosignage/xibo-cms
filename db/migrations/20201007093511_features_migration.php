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

use Phinx\Migration\AbstractMigration;

/**
 * Class FeaturesMigration
 */
class FeaturesMigration extends AbstractMigration
{
    /**
     * @inheritDoc
     */
    public function change()
    {
        $this->table('group')
            ->addColumn('features', 'text', [
                'null' => true,
                'default' => null,
                'limit' => \Phinx\Db\Adapter\MysqlAdapter::TEXT_LONG
            ])
            ->save();

        $this->table('user')
            ->changeColumn('homePageId', 'string', [
                'null' => true,
                'default' => 'null',
                'limit' => 255
            ])
            ->save();

        $this->execute('UPDATE `user` SET homePageId = (SELECT CONCAT(pages.name, \'.view\') FROM pages WHERE user.homePageId = pages.pageId)');

        // Migrate Page Permissions
        $entityId = $this->fetchRow('SELECT entityId FROM permissionentity WHERE entity LIKE \'%Page%\'')[0];

        // Match old page permissions.
        $pages = $this->query('
            SELECT `group`.groupId, pages.name
              FROM permission
                INNER JOIN `group`
                ON `group`.groupId = `permission`.groupId
                INNER JOIN `pages`
                ON `pages`.pageId = `permission`.objectId
             WHERE entityId = 1
                AND view = 1
            ORDER BY groupId;
        ');

        $groupId = 0;
        $features = [];
        while ($page = $pages->fetch()) {
            // Track the group we're on
            if ($groupId !== $page['groupId']) {
                if ($groupId !== 0) {
                    // Save the group we've been working on.
                    $this->execute('UPDATE `group` SET features = \'' . json_encode($features) . '\' WHERE groupId = ' . $groupId);
                }

                // Clear the decks
                $groupId = $page['groupId'];
                $features = [];
            }

            if (in_array($page['name'], ['index', 'manual', 'clock', 'home'])) {
                // Ignore pages which we're not interested in
                continue;
            } else if ($page['name'] === 'schedulenow') {
                // Schedule Now has its own feature.
                $features[] = 'schedule.now';
            } else {
                // Pluralise some pages.
                $pageName = in_array($page['name'], ['user', 'display']) ? $page['name'] . 's' : $page['name'];

                // Not all features will have a .add/.modify, but this will grant the more permissive option and get
                // reset when a user edits.
                $features[] = $pageName . '.view';
                $features[] = $pageName . '.add';
                $features[] = $pageName . '.modify';
            }
        }

        // Do the last one
        if ($groupId !== 0) {
            // Save the group we've been working on.
            $this->execute('UPDATE `group` SET features = \'' . json_encode($features) . '\' WHERE groupId = ' . $groupId);
        }

        // Delete Page Permissions
        $this->execute('DELETE FROM permission WHERE entityId = ' . $entityId);

        // Delete Page Permission Entity
        $this->execute('DELETE FROM permissionentity WHERE entityId = ' . $entityId);

        // Delete Page Table
        $this->table('pages')->drop()->save();
    }
}
