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
        $this->getLog()->debug('upgradeWidget: '. $widget->getId(). ' from: '. $fromSchema.' to: '.$toSchema);

        $upgraded = false;
        $newTemplateId = null;
        $templateId = $widget->getOptionValue('templateId', '');
        $overrideTemplate = $widget->getOptionValue('overrideTemplate', 0);

        foreach ($widget->widgetOptions as $option) {
            if ($option->option === 'templateId') {
                if ($overrideTemplate == 0) {
                    switch ($templateId) {
                        case 'weather-module0-5day':
                            $newTemplateId = 'weather_1';
                            break;
    
                        case 'weather-module0-singleday':
                            $newTemplateId = 'weather_2';
                            break;
    
                        case 'weather-module0-singleday2':
                            $newTemplateId = 'weather_3';
                            break;
    
                        case 'weather-module1l':
                            $newTemplateId = 'weather_4';
                            break;
    
                        case 'weather-module1p':
                            $newTemplateId = 'weather_5';
                            break;
    
                        case 'weather-module2l':
                            $newTemplateId = 'weather_6';
                            break;
    
                        case 'weather-module2p':
                            $newTemplateId = 'weather_7';
                            break;
    
                        case 'weather-module3l':
                            $newTemplateId = 'weather_8';
                            break;
    
                        case 'weather-module3p':
                            $newTemplateId = 'weather_9';
                            break;
    
                        case 'weather-module4l':
                            $newTemplateId = 'weather_10';
                            break;
    
                        case 'weather-module4p':
                            $newTemplateId = 'weather_11';
                            break;
    
                        case 'weather-module5l':
                            $newTemplateId = 'weather_12';
                            break;
    
                        case 'weather-module6h':
                            $newTemplateId = 'weather_13';
                            break;
    
                        case 'weather-module6v':
                            $newTemplateId = 'weather_14';
                            break;
    
                        case 'weather-module-7s':
                            $newTemplateId = 'weather_15';
                            break;
    
                        case 'weather-module-8s':
                            $newTemplateId = 'weather_16';
                            break;
    
                        case 'weather-module-9':
                            $newTemplateId = 'weather_17';
                            break;
    
                        case 'weather-module-10l':
                            $newTemplateId = 'weather_18';
                            break;
    
                        default:
                            break;
                    }
                } else {
                    $newTemplateId = 'weather_custom_html';
                }
                

                if (!empty($newTemplateId)) {
                    $widget->setOptionValue('templateId', 'attrib', $newTemplateId);
                    $upgraded = true;
                }
            }
        }

        // If overriden, we need to tranlate the legacy options to the new values
        if ($overrideTemplate == 1) {
            $widget->setOptionValue('widgetDesignWidth', 'attr', $widget->getOptionValue('widgetOriginalWidth', '250'));
            $widget->setOptionValue('widgetDesignHeight', 'attr', $widget->getOptionValue('widgetOriginalHeight', '250'));
        }

        return $upgraded;
    }

    public function saveTemplate(string $template, string $fileName): bool
    {
        return false;
    }
}
