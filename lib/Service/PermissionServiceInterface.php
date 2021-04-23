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

namespace Xibo\Service;

use Xibo\Factory\CampaignFactory;
use Xibo\Factory\CommandFactory;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\DayPartFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\FolderFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\MenuBoardFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\RegionFactory;
use Xibo\Factory\WidgetFactory;

interface PermissionServiceInterface
{
    /**
     * PermissionServiceInterface constructor.
     * @param LogServiceInterface $log,
     * @param DisplayGroupFactory $displayGroupFactory
     * @param MediaFactory $mediaFactory
     * @param CampaignFactory $campaignFactory
     * @param WidgetFactory $widgetFactory
     * @param RegionFactory $regionFactory
     * @param PlaylistFactory $playlistFactory
     * @param DataSetFactory $dataSetFactory
     * @param DayPartFactory $dayPartFactory
     * @param CommandFactory $commandFactory
     * @param FolderFactory $folderFactory
     * @param MenuBoardFactory $menuBoardFactory
     */
    public function __construct(
        LogServiceInterface $log,
        DisplayGroupFactory $displayGroupFactory,
        MediaFactory $mediaFactory,
        CampaignFactory $campaignFactory,
        WidgetFactory $widgetFactory,
        RegionFactory $regionFactory,
        PlaylistFactory $playlistFactory,
        DataSetFactory $dataSetFactory,
        DayPartFactory $dayPartFactory,
        CommandFactory $commandFactory,
        FolderFactory $folderFactory,
        MenuBoardFactory $menuBoardFactory
    );

    public function getFactory($entity);
}