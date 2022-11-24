<?php
/*
 * Copyright (C) 2022 Xibo Signage Ltd
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


namespace Xibo\Tests\Helper;


use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboPlaylist;
use Xibo\OAuth2\Client\Entity\XiboRegion;
use Xibo\OAuth2\Client\Entity\XiboResolution;
use Xibo\OAuth2\Client\Entity\XiboWidget;
use Xibo\OAuth2\Client\Exception\XiboApiException;

/**
 * Trait LayoutHelperTrait
 * @package Helper
 */
trait LayoutHelperTrait
{
    /**
     * @param int|null $status
     * @return XiboLayout
     */
    protected function createLayout($status = null)
    {
        // Create a Layout for us to work with.
        $layout = (new XiboLayout($this->getEntityProvider()))
            ->create(
                Random::generateString(),
                'PHPUnit Created Layout for Automated Integration Testing',
                '',
                $this->getResolutionId('landscape')
            );

        $this->getLogger()->debug('Layout created with name ' . $layout->layout);

        if ($status !== null) {
            // Set the initial status of this Layout to Built
            $this->setLayoutStatus($layout, $status);
        }

        return $layout;
    }

    /**
     * @param XiboLayout $layout
     * @param int $status
     * @return $this
     */
    protected function setLayoutStatus($layout, $status)
    {
        $layout->status = $status;
        $this->getStore()->update('UPDATE `layout` SET `status` = :status WHERE layoutId = :layoutId', ['layoutId' => $layout->layoutId, 'status' => $status]);
        $this->getStore()->commitIfNecessary();
        $this->getStore()->close();
        return $this;
    }

    /**
     * Build the Layout ready for XMDS
     * @param XiboLayout $layout
     * @return $this
     */
    protected function buildLayout($layout)
    {
        // Call the status route
        $this->getEntityProvider()->get('/layout/status/' . $layout->layoutId);

        return $this;
    }

    /**
     * @param XiboLayout $layout
     */
    protected function deleteLayout($layout)
    {
        $layout->delete();
    }

    /**
     * @param XiboLayout $layout
     * @param int $status
     * @return bool
     */
    protected function layoutStatusEquals($layout, $status)
    {
        // Requery the Display
        try {
            $check = (new XiboLayout($this->getEntityProvider()))->getById($layout->layoutId);

            $this->getLogger()->debug('Tested Layout ' . $layout->layout . '. Status returned is ' . $check->status);

            return $check->status === $status;

        } catch (XiboApiException $xiboApiException) {
            $this->getLogger()->error('API exception for ' . $layout->layoutId . ': ' . $xiboApiException->getMessage());
            return false;
        }

    }

    /**
     * @param $type
     * @return int
     */
    protected function getResolutionId($type)
    {
        if ($type === 'landscape') {
            $width = 1920;
            $height = 1080;
        } else if ($type === 'portrait') {
            $width = 1080;
            $height = 1920;
        } else {
            return -10;
        }

        //$this->getLogger()->debug('Querying for ' . $width . ', ' . $height);

        $resolutions = (new XiboResolution($this->getEntityProvider()))->get(['width' => $width, 'height' => $height]);

        if (count($resolutions) <= 0)
            return -10;

        return $resolutions[0]->resolutionId;
    }

    /**
     * @param XiboLayout $layout
     * @return XiboLayout
     */
    protected function checkout($layout)
    {
        $this->getLogger()->debug('Checkout ' . $layout->layoutId);

        $response = $this->getEntityProvider()->put('/layout/checkout/' . $layout->layoutId);

        // Swap the Layout object to use the one returned.
        /** @var XiboLayout $layout */
        $layout = $this->constructLayoutFromResponse($response);

        $this->getLogger()->debug('LayoutId is now: ' . $layout->layoutId);

        return $layout;
    }

    /**
     * @param XiboLayout $layout
     * @return XiboLayout
     */
    protected function publish($layout)
    {
        $this->getLogger()->debug('Publish ' . $layout->layoutId);

        $response = $this->getEntityProvider()->put('/layout/publish/' . $layout->layoutId , [
            'publishNow' => 1
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        // Swap the Layout object to use the one returned.
        /** @var XiboLayout $layout */
        $layout = $this->constructLayoutFromResponse($response);

        $this->getLogger()->debug('LayoutId is now: ' . $layout->layoutId);

        return $layout;
    }

    /**
     * @param XiboLayout $layout
     * @return XiboLayout
     */
    protected function discard($layout)
    {
        $this->getLogger()->debug('Discard ' . $layout->layoutId);

        $response = $this->getEntityProvider()->put('/layout/discard/' . $layout->layoutId);

        // Swap the Layout object to use the one returned.
        /** @var XiboLayout $layout */
        $layout = $this->constructLayoutFromResponse($response);

        $this->getLogger()->debug('LayoutId is now: ' . $layout->layoutId);

        return $layout;
    }

    /**
     * @param $layout
     * @return $this
     */
    protected function addSimpleWidget($layout)
    {
        $this->getEntityProvider()->post('/playlist/widget/clock/' . $layout->regions[0]->regionPlaylist->playlistId, [
            'duration' => 100,
            'useDuration' => 1
        ]);

        return $this;
    }

    /**
     * @param $layout
     * @return $this
     */
    protected function addSimpleTextWidget($layout)
    {
        $this->getEntityProvider()->post('/playlist/widget/text/' . $layout->regions[0]->regionPlaylist->playlistId, [
            'text' => 'PHPUNIT TEST TEXT',
            'duration' => 100,
            'useDuration' => 1
        ]);

        return $this;
    }

    /**
     * @param $response
     * @return \Xibo\OAuth2\Client\Entity\XiboEntity|XiboLayout
     */
    private function constructLayoutFromResponse($response)
    {
        $hydratedRegions = [];
        $hydratedWidgets = [];
        /** @var XiboLayout $layout */
        $layout = new XiboLayout($this->getEntityProvider());
        $layout = $layout->hydrate($response);

        $this->getLogger()->debug('Constructing Layout from Response: ' . $layout->layoutId);

        if (isset($response['regions'])) {
            foreach ($response['regions'] as $item) {
                /** @var XiboRegion $region */
                $region = new XiboRegion($this->getEntityProvider());
                $region->hydrate($item);
                /** @var XiboPlaylist $playlist */
                $playlist = new XiboPlaylist($this->getEntityProvider());
                $playlist->hydrate($item['regionPlaylist']);
                foreach ($playlist->widgets as $widget) {
                    /** @var XiboWidget $widgetObject */
                    $widgetObject = new XiboWidget($this->getEntityProvider());
                    $widgetObject->hydrate($widget);
                    $hydratedWidgets[] = $widgetObject;
                }
                $playlist->widgets = $hydratedWidgets;
                $region->regionPlaylist = $playlist;
                $hydratedRegions[] = $region;
            }
            $layout->regions = $hydratedRegions;
        } else {
            $this->getLogger()->debug('No regions returned with Layout object');
        }

        return $layout;
    }

    /**
     * @param $layout
     * @return XiboLayout
     */
    protected function getDraft($layout)
    {
        $draft = (new XiboLayout($this->getEntityProvider()))->get(['parentId' => $layout->layoutId, 'showDrafts' => 1, 'embed' => 'regions,playlists,widgets']);

        return $draft[0];
    }
}