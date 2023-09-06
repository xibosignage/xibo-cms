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
 * Convert RSS old kebab-case properties to camelCase
 */
class RssWidgetCompatibility implements WidgetCompatibilityInterface
{
    use WidgetCompatibilityTrait;

    /** @inheritdoc
     */
    public function upgradeWidget(Widget $widget, int $fromSchema, int $toSchema): bool
    {
        $this->getLog()->debug('upgradeWidget: ' . $widget->getId() . ' from: ' . $fromSchema . ' to: ' . $toSchema);

        // Decode URL (always make sure we save URLs decoded)
        $widget->setOptionValue('uri', 'attrib', urldecode($widget->getOptionValue('uri', '')));

        // Swap to new template names.
        $overrideTemplate = $widget->getOptionValue('overrideTemplate', 0);

        if ($overrideTemplate) {
            $newTemplateId = 'article_custom_html';
        } else {
            $newTemplateId = match ($widget->getOptionValue('templateId', '')) {
                'media-rss-image-only' => 'article_image_only',
                'media-rss-with-left-hand-text' => 'article_with_left_hand_text',
                'media-rss-with-title' => 'article_with_title',
                'prominent-title-with-desc-and-name-separator' => 'article_with_desc_and_name_separator',
                default => 'article_title_only',
            };
        }
        $widget->setOptionValue('templateId', 'attrib', $newTemplateId);

        // Change some other options if they have been set.
        foreach ($widget->widgetOptions as $option) {
            $widgetChangeOption = null;
            switch ($option->option) {
                case 'background-color':
                    $widgetChangeOption = 'itemBackgroundColor';
                    break;

                case 'title-color':
                    $widgetChangeOption = 'itemTitleColor';
                    break;

                case 'name-color':
                    $widgetChangeOption = 'itemNameColor';
                    break;

                case 'description-color':
                    $widgetChangeOption = 'itemDescriptionColor';
                    break;

                case 'font-size':
                    $widgetChangeOption = 'itemFontSize';
                    break;

                case 'image-fit':
                    $widgetChangeOption = 'itemImageFit';
                    break;

                default:
                    break;
            }

            if (!empty($widgetChangeOption)) {
                $widget->changeOption($option->option, $widgetChangeOption);
            }
        }

        return true;
    }

    public function saveTemplate(string $template, string $fileName): bool
    {
        return false;
    }
}
