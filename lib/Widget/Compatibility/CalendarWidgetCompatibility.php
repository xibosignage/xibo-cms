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
