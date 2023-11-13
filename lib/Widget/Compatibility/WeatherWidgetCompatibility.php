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
        $this->getLog()->debug('upgradeWidget: ' . $widget->getId() . ' from: ' . $fromSchema . ' to: ' . $toSchema);

        $overrideTemplate = $widget->getOptionValue('overrideTemplate', 0);
        if ($overrideTemplate == 1) {
            $newTemplateId = 'weather_custom_html';
        } else {
            $newTemplateId = match ($widget->getOptionValue('templateId', '')) {
                'weather-module0-singleday' => 'weather_2',
                'weather-module0-singleday2' => 'weather_3',
                'weather-module1l' => 'weather_4',
                'weather-module1p' => 'weather_5',
                'weather-module2l' => 'weather_6',
                'weather-module2p' => 'weather_7',
                'weather-module3l' => 'weather_8',
                'weather-module3p' => 'weather_9',
                'weather-module4l' => 'weather_10',
                'weather-module4p' => 'weather_11',
                'weather-module5l' => 'weather_12',
                'weather-module6h' => 'weather_13',
                'weather-module6v' => 'weather_14',
                'weather-module-7s' => 'weather_15',
                'weather-module-8s' => 'weather_16',
                'weather-module-9' => 'weather_17',
                'weather-module-10l' => 'weather_18',
                default => 'weather_1',
            };
        }
        $widget->setOptionValue('templateId', 'attrib', $newTemplateId);

        // If overriden, we need to tranlate the legacy options to the new values
        if ($overrideTemplate == 1) {
            $widget->changeOption('widgetOriginalWidth', 'widgetDesignWidth');
            $widget->changeOption('widgetOriginalHeight', 'widgetDesignHeight');
        }

        // Process the background image properties so that they are removed if empty
        $this->removeOptionIfEquals($widget, 'cloudy-image');
        $this->removeOptionIfEquals($widget, 'day-cloudy-image');
        $this->removeOptionIfEquals($widget, 'day-sunny-image');
        $this->removeOptionIfEquals($widget, 'fog-image');
        $this->removeOptionIfEquals($widget, 'hail-image');
        $this->removeOptionIfEquals($widget, 'night-clear-image');
        $this->removeOptionIfEquals($widget, 'night-partly-cloudy-image');
        $this->removeOptionIfEquals($widget, 'rain-image');
        $this->removeOptionIfEquals($widget, 'snow-image');
        $this->removeOptionIfEquals($widget, 'windy-image');
        return true;
    }

    private function removeOptionIfEquals(Widget $widget, string $option): void
    {
        if ($widget->getOptionValue($option, null) === $option) {
            $widget->removeOption($option);
        }
    }

    public function saveTemplate(string $template, string $fileName): bool
    {
        return false;
    }
}
