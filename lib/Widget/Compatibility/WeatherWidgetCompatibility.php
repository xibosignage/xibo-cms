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
class WeatherWidgetCompatibility implements WidgetCompatibilityInterface
{
    use WidgetCompatibilityTrait;

    /** @inheritdoc
     */
    public function upgradeWidget(Widget $widget, int $fromSchema, int $toSchema): bool
    {
        $upgraded = false;
        $this->getLog()->debug('upgradeWidget: '. $widget->getId(). ' from: '. $fromSchema.' to: '.$toSchema);

        foreach ($widget->widgetOptions as $option) {
            $templateId = $widget->getOptionValue('templateId', '');

            if ($option->option === 'templateId') {
                switch ($templateId) {
                    case 'weather-module0-5day':
                        $widget->setOptionValue('templateId', 'attrib', 'weather_1');
                        $upgraded = true;
                        break;

                    case 'weather-module0-singleday':
                        $widget->setOptionValue('templateId', 'attrib', 'weather_2');
                        $upgraded = true;
                        break;

                    case 'weather-module0-singleday2':
                        $widget->setOptionValue('templateId', 'attrib', 'weather_3');
                        $upgraded = true;
                        break;

                    case 'weather-module1l':
                        $widget->setOptionValue('templateId', 'attrib', 'weather_4');
                        $upgraded = true;
                        break;

                    case 'weather-module1p':
                        $widget->setOptionValue('templateId', 'attrib', 'weather_5');
                        $upgraded = true;
                        break;

                    case 'weather-module2l':
                        $widget->setOptionValue('templateId', 'attrib', 'weather_6');
                        $upgraded = true;
                        break;

                    case 'weather-module2p':
                        $widget->setOptionValue('templateId', 'attrib', 'weather_7');
                        $upgraded = true;
                        break;

                    case 'weather-module3l':
                        $widget->setOptionValue('templateId', 'attrib', 'weather_8');
                        $upgraded = true;
                        break;

                    case 'weather-module3p':
                        $widget->setOptionValue('templateId', 'attrib', 'weather_9');
                        $upgraded = true;
                        break;

                    case 'weather-module4l':
                        $widget->setOptionValue('templateId', 'attrib', 'weather_10');
                        $upgraded = true;
                        break;

                    case 'weather-module4p':
                        $widget->setOptionValue('templateId', 'attrib', 'weather_11');
                        $upgraded = true;
                        break;

                    case 'weather-module5l':
                        $widget->setOptionValue('templateId', 'attrib', 'weather_12');
                        $upgraded = true;
                        break;

                    case 'weather-module6h':
                        $widget->setOptionValue('templateId', 'attrib', 'weather_13');
                        $upgraded = true;
                        break;

                    case 'weather-module6v':
                        $widget->setOptionValue('templateId', 'attrib', 'weather_14');
                        $upgraded = true;
                        break;

                    case 'weather-module-7s':
                        $widget->setOptionValue('templateId', 'attrib', 'weather_15');
                        $upgraded = true;
                        break;

                    case 'weather-module-8s':
                        $widget->setOptionValue('templateId', 'attrib', 'weather_16');
                        $upgraded = true;
                        break;

                    case 'weather-module-9':
                        $widget->setOptionValue('templateId', 'attrib', 'weather_17');
                        $upgraded = true;
                        break;

                    case 'weather-module-10l':
                        $widget->setOptionValue('templateId', 'attrib', 'weather_18');
                        $upgraded = true;
                        break;

                    default:
                        break;
                }
            }
        }

        return $upgraded;
    }

    public function saveTemplate(string $template, string $fileName): bool
    {
        return false;
    }

}
