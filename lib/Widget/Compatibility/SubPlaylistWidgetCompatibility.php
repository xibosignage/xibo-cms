<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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

namespace Xibo\Widget\Compatibility;

use Xibo\Entity\Widget;
use Xibo\Widget\Provider\WidgetCompatibilityInterface;
use Xibo\Widget\Provider\WidgetCompatibilityTrait;
use Xibo\Widget\SubPlaylistItem;

/**
 * Convert widget from an old schema to a new schema
 */
class SubPlaylistWidgetCompatibility implements WidgetCompatibilityInterface
{
    use WidgetCompatibilityTrait;

    /** @inheritdoc
     */
    public function upgradeWidget(Widget $widget, int $fromSchema, int $toSchema): bool
    {
        $this->getLog()->debug('upgradeWidget: '. $widget->getId(). ' from: '. $fromSchema.' to: '.$toSchema);

        $upgraded = false;
        $playlists = [];
        $playlistIds = [];

        foreach ($widget->widgetOptions as $option) {
            if ($option->option === 'subPlaylists') {
                $playlists = json_decode($widget->getOptionValue('subPlaylists', '[]'), true);
            }

            if ($option->option === 'subPlaylistIds') {
                $playlistIds = json_decode($widget->getOptionValue('subPlaylistIds', '[]'), true);
            }

            if ($option->option === 'subPlaylistOptions') {
                $subPlaylistOptions = json_decode($widget->getOptionValue('subPlaylistOptions', '[]'), true);
            }
        }

        if (count($playlists) <= 0) {
            $i = 0;
            foreach ($playlistIds as $playlistId) {
                $i++;
                $playlists[] = [
                    'rowNo' => $i,
                    'playlistId' => $playlistId,
                    'spotFill' => $subPlaylistOptions[$playlistId]['subPlaylistIdSpotFill'] ?? null,
                    'spotLength' => $subPlaylistOptions[$playlistId]['subPlaylistIdSpotLength'] ?? null,
                    'spots' => $subPlaylistOptions[$playlistId]['subPlaylistIdSpots'] ?? null,
                ];
            }

            $playlistItems = [];
            foreach ($playlists as $playlist) {
                $item = new SubPlaylistItem();
                $item->rowNo = intval($playlist['rowNo']);
                $item->playlistId = $playlist['playlistId'];
                $item->spotFill = $playlist['spotFill'] ?? null;
                $item->spotLength =  $playlist['spotLength'] !== '' ? intval($playlist['spotLength']) : null;
                $item->spots = $playlist['spots'] !== '' ? intval($playlist['spots']) : null;

                $playlistItems[] = $item;
            }

            if (count($playlistItems) > 0) {
                $widget->setOptionValue('subPlaylists', 'attrib', json_encode($playlistItems));
                $upgraded = true;
            }
        }

        return $upgraded;
    }

    public function saveTemplate(string $template, string $fileName): bool
    {
        return false;
    }
}
