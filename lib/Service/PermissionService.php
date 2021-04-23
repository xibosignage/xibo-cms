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
use Xibo\Support\Exception\InvalidArgumentException;

class PermissionService implements PermissionServiceInterface
{
    /**
     * @var LogServiceInterface
     */
    private $log;
    /**
     * @var DisplayGroupFactory
     */
    private $displayGroupFactory;
    /**
     * @var MediaFactory
     */
    private $mediaFactory;
    /**
     * @var CampaignFactory
     */
    private $campaignFactory;
    /**
     * @var WidgetFactory
     */
    private $widgetFactory;
    /**
     * @var RegionFactory
     */
    private $regionFactory;
    /**
     * @var PlaylistFactory
     */
    private $playlistFactory;
    /**
     * @var DataSetFactory
     */
    private $dataSetFactory;
    /**
     * @var DayPartFactory
     */
    private $dayPartFactory;
    /**
     * @var CommandFactory
     */
    private $commandFactory;
    /**
     * @var FolderFactory
     */
    private $folderFactory;
    /**
     * @var MenuBoardFactory
     */
    private $menuBoardFactory;

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
    ) {
        $this->log = $log;
        $this->displayGroupFactory = $displayGroupFactory;
        $this->mediaFactory = $mediaFactory;
        $this->campaignFactory = $campaignFactory;
        $this->widgetFactory = $widgetFactory;
        $this->regionFactory = $regionFactory;
        $this->playlistFactory = $playlistFactory;
        $this->dataSetFactory = $dataSetFactory;
        $this->dayPartFactory = $dayPartFactory;
        $this->commandFactory = $commandFactory;
        $this->folderFactory = $folderFactory;
        $this->menuBoardFactory = $menuBoardFactory;
    }

    public function getFactory($entity)
    {
        $factory = $this->$entity;

        if (!method_exists($factory, 'getById')) {
            $this->log->error(sprintf('Invalid Entity %s', $entity));
            throw new InvalidArgumentException(__('Permissions form requested with an invalid entity'));
        }

        return $factory;
    }
}