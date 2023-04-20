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

/**
 * Convert widget from an old schema to a new schema
 */
class WorldClockWidgetCompatibility implements WidgetCompatibilityInterface
{
    use WidgetCompatibilityTrait;

    /** @inheritdoc
     */
    public function upgradeWidget(Widget $widget, int $fromSchema, int $toSchema): bool
    {
        $this->getLog()->debug('upgradeWidget: '. $widget->getId(). ' from: '. $fromSchema.' to: '.$toSchema);

        foreach ($widget->widgetOptions as $option) {
            $clockType = $widget->getOptionValue('clockType', 1);
            $templateId = $widget->getOptionValue('templateId', '');

            if ($option->option === 'clockType') {
                switch ($clockType) {
                    case 1:
                        if ($templateId === 'worldclock1') {
                            $widget->type = 'worldclock-digital-text';
                        } elseif ($templateId === 'worldclock2') {
                            $widget->type = 'worldclock-digital-date';
                        } else {
                            $widget->type = 'worldclock-digital-custom';
                        }
                        break;

                    case 2:
                        $widget->type = 'worldclock-analogue';
                        break;

                    default:
                        break;
                }
                return true;
            }
        }
        return false;
    }

    public function saveTemplate(string $template, string $fileName): bool
    {
        return false;
    }

}
