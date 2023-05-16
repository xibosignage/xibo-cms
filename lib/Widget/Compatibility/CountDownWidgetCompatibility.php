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
class CountDownWidgetCompatibility implements WidgetCompatibilityInterface
{
    use WidgetCompatibilityTrait;

    /** @inheritdoc
     */
    public function upgradeWidget(Widget $widget, int $fromSchema, int $toSchema): bool
    {
        $this->getLog()->debug('upgradeWidget: '. $widget->getId(). ' from: '. $fromSchema.' to: '.$toSchema);

        $upgraded = false;
        $widgetType = null;
        $countdownType = $widget->getOptionValue('countdownType', 1);
        $overrideTemplate = $widget->getOptionValue('overrideTemplate', 0);

        foreach ($widget->widgetOptions as $option) {
            if ($option->option === 'countdownType') {
                if( $overrideTemplate == 0) {
                    switch ($countdownType) {
                        case 1:
                            $widgetType = 'countdown-text';
                            break;

                        case 2:
                            $widgetType = 'countdown-clock';
                            break;

                        case 3:
                            $widgetType = 'countdown-table';
                            break;

                        case 4:
                            $widgetType = 'countdown-days';
                            break;

                        default:
                            break;
                    }
                } else {
                    $widgetType = 'countdown-custom';
                }

                if (!empty($widgetType)) {
                    $widget->type = $widgetType;
                    $upgraded = true;
                }
            }
        }

        // If overriden, we need to tranlate the legacy options to the new values
        if ($overrideTemplate == 1) {
            $widget->setOptionValue('widgetDesignWidth', 'attr', $widget->getOptionValue('widgetOriginalWidth', '250'));
            $widget->setOptionValue('widgetDesignHeight', 'attr', $widget->getOptionValue('widgetOriginalHeight', '250'));
            $widget->removeOption('templateId');
        }

        return $upgraded;
    }

    public function saveTemplate(string $template, string $fileName): bool
    {
        return false;
    }
}
