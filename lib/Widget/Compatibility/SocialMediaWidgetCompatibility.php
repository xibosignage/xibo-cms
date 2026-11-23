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
class SocialMediaWidgetCompatibility implements WidgetCompatibilityInterface
{
    use WidgetCompatibilityTrait;

    /** @inheritdoc
     */
    public function upgradeWidget(Widget $widget, int $fromSchema, int $toSchema): bool
    {
        $this->getLog()->debug('upgradeWidget: ' . $widget->getId() . ' from: ' . $fromSchema . ' to: ' . $toSchema);

        $overrideTemplate = $widget->getOptionValue('overrideTemplate', 0);
        if ($overrideTemplate == 1) {
            $newTemplateId = 'social_media_custom_html';
        } else {
            $newTemplateId = match ($widget->getOptionValue('templateId', '')) {
                'full-timeline-np' => 'social_media_static_1',
                'full-timeline' => 'social_media_static_2',
                'tweet-with-profileimage-left' => 'social_media_static_4',
                'tweet-with-profileimage-right' => 'social_media_static_5',
                'tweet-1' => 'social_media_static_6',
                'tweet-2' => 'social_media_static_7',
                'tweet-4' => 'social_media_static_8',
                'tweet-6NP' => 'social_media_static_9',
                'tweet-6PL' => 'social_media_static_10',
                'tweet-7' => 'social_media_static_11',
                'tweet-8' => 'social_media_static_12',
                default => 'social_media_static_3',
            };
        }
        $widget->setOptionValue('templateId', 'attrib', $newTemplateId);

        // If overriden, we need to tranlate the legacy options to the new values
        if ($overrideTemplate == 1) {
            $widget->changeOption('widgetOriginalWidth', 'widgetDesignWidth');
            $widget->changeOption('widgetOriginalHeight', 'widgetDesignHeight');
            $widget->changeOption('widgetOriginalPadding', 'widgetDesignGap');
        }

        return true;
    }

    public function saveTemplate(string $template, string $fileName): bool
    {
        return false;
    }
}
