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

namespace Xibo\Entity;

/*
* Class ReportForm
* @package Xibo\Entity
*
*/
class ReportForm
{
    /**
     * Report name is the string that is defined in .report file
     * @var string
     */
    public $reportName;

    /**
     * Report category is the string that is defined in .report file
     * @var string|null
     */
    public $reportCategory;

    /**
     * The defaults that is used in report form twig file
     * @var array
     */
    public $defaults;

    /**
     * The string that is displayed when we popover the Schedule button
     * @var string
     */
    public $reportAddBtnTitle;

    /**
     * ReportForm constructor.
     * @param string $reportName
     * @param string $reportCategory
     * @param array $defaults
     * @param string|null $reportAddBtnTitle
     */
    public function __construct(
        string $reportName,
        string $reportCategory,
        array $defaults = [],
        string $reportAddBtnTitle = 'Schedule'
    ) {
        $this->reportName = $reportName;
        $this->reportCategory = $reportCategory;
        $this->defaults = $defaults;
        $this->reportAddBtnTitle = $reportAddBtnTitle;

        return $this;
    }
}
