<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
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

use Phinx\Migration\AbstractMigration;

/**
 * Add search indexes to Menu Board tables
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class AddFulltextIndexToMenuBoardTablesMigration extends AbstractMigration
{
    public function change(): void
    {
        $this->execute(
            'ALTER TABLE `menu_board` ADD FULLTEXT `idx_menu_board_search` (`name`) WITH PARSER ngram'
        );
        $this->execute(
            'ALTER TABLE `menu_category` ADD FULLTEXT `idx_menu_category_search` (`name`) WITH PARSER ngram'
        );
        $this->execute(
            'ALTER TABLE `menu_product` ADD FULLTEXT `idx_menu_product_search` (`name`) WITH PARSER ngram'
        );
    }
}
