<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
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
        $this->getLog()->debug('upgradeWidget: ' . $widget->getId() . ' from: ' . $fromSchema . ' to: ' . $toSchema);

        $overrideTemplate = $widget->getOptionValue('overrideTemplate', 0);
        if ($overrideTemplate) {
            $widget->type = 'worldclock-digital-custom';
        } else {
            $widget->type = match ($widget->getOptionValue('clockType', 1)) {
                2 => 'worldclock-analogue',
                default => match ($widget->getOptionValue('templateId', '')) {
                    'worldclock1' => 'worldclock-digital-text',
                    'worldclock2' => 'worldclock-digital-date',
                    default => 'worldclock-digital-custom',
                },
            };
        }

        // We need to tranlate the legacy options to the new values
        $widget->changeOption('clockCols', 'numCols');
        $widget->changeOption('clockRows', 'numRows');

        if ($overrideTemplate == 1) {
            $widget->changeOption('mainTemplate', 'template_html');
            $widget->changeOption('styleSheet', 'template_style');
            $widget->changeOption('widgetOriginalWidth', 'widgetDesignWidth');
            $widget->changeOption('widgetOriginalHeight', 'widgetDesignHeight');
        }

        // Always remove template id / clockType from world clock
        $widget->removeOption('templateId');
        $widget->removeOption('clockType');

        return true;
    }

    public function saveTemplate(string $template, string $fileName): bool
    {
        return false;
    }
}
