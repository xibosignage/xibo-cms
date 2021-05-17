<?php
/**
 * Copyright (C) 2021 Xibo Signage Ltd
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

/**
* Class ReportForm
* @package Xibo\Entity
*
*/
class ReportForm
{
    /**
     * On demand report form template
     * @var string
     */
    public $template;

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
     * @param string $template
     * @param string $reportName
     * @param string $reportCategory
     * @param array $defaults
     * @param string|null $reportAddBtnTitle
     */
    public function __construct(
        string $template,
        string $reportName,
        string $reportCategory,
        array $defaults = [],
        string $reportAddBtnTitle = 'Schedule'
    ) {
        $this->template = $template;
        $this->reportName = $reportName;
        $this->reportCategory = $reportCategory;
        $this->defaults = $defaults;
        $this->reportAddBtnTitle = $reportAddBtnTitle;

        return $this;
    }
}
