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

            // If template id is "article_with_desc_and_name_separator"
            // set showSideBySide to 1 to replicate behaviour in v3 for marquee
            $effect = $widget->getOptionValue('effect', null);
            if (
                $newTemplateId === 'article_with_desc_and_name_separator' &&
                $effect === 'marqueeLeft' ||
                $effect === 'marqueeRight' ||
                $effect === 'marqueeUp' ||
                $effect === 'marqueeDown'
            ) {
                $widget->setOptionValue('showSideBySide', 'attrib', 1);
            }
        }
        $widget->setOptionValue('templateId', 'attrib', $newTemplateId);

        // If the new templateId is custom, we need to parse the old template for image enclosures
        if ($newTemplateId === 'article_custom_html') {
            $template = $widget->getOptionValue('template', null);
            if (!empty($template)) {
                $modified = false;
                $matches = [];
                preg_match_all('/\[(.*?)\]/', $template, $matches);

                for ($i = 0; $i < count($matches[1]); $i++) {
                    // We have a [Link] or a [xxx|image] tag
                    $match = $matches[1][$i];
                    if ($match === 'Link' || $match === 'Link|image') {
                        // This is a straight-up enclosure (which is the default).
                        $template = str_replace($matches[0][$i], '<img src="[image]" alt="Image" />', $template);
                        $modified = true;
                    } else if (str_contains($match, '|image')) {
                        // [tag|image|attribute]
                        // Set the necessary options depending on how our tag is made up
                        $parts = explode('|', $match);
                        $tag = $parts[0];
                        $attribute = $parts[2] ?? null;

                        $widget->setOptionValue('imageSource', 'attrib', 'custom');
                        $widget->setOptionValue('imageSourceTag', 'attrib', $tag);
                        if (!empty($attribute)) {
                            $widget->setOptionValue('imageSourceAttribute', 'attrib', $attribute);
                        }

                        $template = str_replace($matches[0][$i], '<img src="[image]" alt="Image"/>', $template);
                        $modified = true;
                    }
                }

                if ($modified) {
                    $widget->setOptionValue('template', 'cdata', $template);
                }
            }
        }

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
