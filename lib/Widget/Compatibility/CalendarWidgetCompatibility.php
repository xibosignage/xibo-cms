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
 * Convert a v3 calendar or calendaradvanced widget to its v4 counterpart.
 */
class CalendarWidgetCompatibility implements WidgetCompatibilityInterface
{
    use WidgetCompatibilityTrait;

    /** @inheritDoc */
    public function upgradeWidget(Widget $widget, int $fromSchema, int $toSchema): bool
    {
        $this->getLog()->debug('upgradeWidget: ' . $widget->getId() . ' from: ' . $fromSchema . ' to: ' . $toSchema);

        // Track if we've been upgraded.
        $upgraded = false;

        // Did we originally come from an agenda (the old calendar widget)
        if ($widget->getOriginalValue('type') === 'calendar') {
            $newTemplateId = 'event_custom_html';

            // New options names.
            $widget->changeOption('template', 'text');
        } else {
            // We are a calendaradvanced
            // Calendar type is either 1=schedule, 2=daily, 3=weekly or 4=monthly.
            $newTemplateId = match ($widget->getOptionValue('calendarType', 1)) {
                2 => 'daily',
                3 => 'weekly',
                4 => 'monthly',
                default => 'schedule',
            };

            // Apply the theme
            $newTemplateId .= '_' . $widget->getOptionValue('templateTheme', 'light');
        }

        if (!empty($newTemplateId)) {
            $widget->setOptionValue('templateId', 'attrib', $newTemplateId);
            $upgraded = true;
        }

        return $upgraded;
    }

    public function saveTemplate(string $template, string $fileName): bool
    {
        return false;
    }
}
