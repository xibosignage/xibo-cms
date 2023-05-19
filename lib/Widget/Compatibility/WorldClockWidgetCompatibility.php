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

        $upgraded = false;
        $widgetType = null;
        $clockType = $widget->getOptionValue('clockType', 1);
        $templateId = $widget->getOptionValue('templateId', '');
        $overrideTemplate = $widget->getOptionValue('overrideTemplate', 0);

        foreach ($widget->widgetOptions as $option) {
            if ($option->option === 'clockType') {
                if ($overrideTemplate == 0) {
                    switch ($clockType) {
                        case 1:
                            if ($templateId === 'worldclock1') {
                                $widgetType = 'worldclock-digital-text';
                            } elseif ($templateId === 'worldclock2') {
                                $widgetType = 'worldclock-digital-date';
                            } else {
                                $widgetType = 'worldclock-digital-custom';
                            }
                            break;

                        case 2:
                            $widgetType = 'worldclock-analogue';
                            break;

                        default:
                            break;
                    }
                } else {
                    $widgetType = 'worldclock-digital-custom';
                }

                if (!empty($widgetType)) {
                    $widget->type = $widgetType;
                    $upgraded = true;
                }
            }
        }

        // If overriden, we need to tranlate the legacy options to the new values
        if ($overrideTemplate == 1) {
            $widget->setOptionValue('template_html', 'cdata', $widget->getOptionValue('mainTemplate', ''));
            $widget->setOptionValue('template_style', 'cdata', $widget->getOptionValue('styleSheet', ''));
            $widget->setOptionValue('numCols', 'attrib', $widget->getOptionValue('clockCols', 1));
            $widget->setOptionValue('numRows', 'attrib', $widget->getOptionValue('clockRows', 1));
            $widget->setOptionValue('widgetDesignWidth', 'attrib', $widget->getOptionValue('widgetOriginalWidth', '250'));
            $widget->setOptionValue('widgetDesignHeight', 'attrib', $widget->getOptionValue('widgetOriginalHeight', '250'));
        }

        return $upgraded;
    }

    public function saveTemplate(string $template, string $fileName): bool
    {
        return false;
    }
}
