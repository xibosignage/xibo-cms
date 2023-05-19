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
        $this->getLog()->debug('upgradeWidget: '. $widget->getId(). ' from: '. $fromSchema.' to: '.$toSchema);

        $upgraded = false;
        $newTemplateId = null;
        $overrideTemplate = $widget->getOptionValue('overrideTemplate', 0);

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
                case 'templateId':
                    if ($overrideTemplate == 0) {
                        $templateId = $widget->getOptionValue('templateId', '');
                        switch ($templateId) {
                            case 'media-rss-image-only':
                                $newTemplateId = 'article_image_only';
                                break;

                            case 'media-rss-with-left-hand-text':
                                $newTemplateId = 'article_with_left_hand_text';
                                break;

                            case 'media-rss-with-title':
                                $newTemplateId = 'article_with_title';
                                break;

                            case 'prominent-title-with-desc-and-name-separator':
                                $newTemplateId = 'article_with_desc_and_name_separator';
                                break;

                            case 'title-only':
                                $newTemplateId = 'article_title_only';
                                break;

                            default:
                                break;
                        }
                    } else {
                        $newTemplateId = 'article_custom_html';
                    }

                    if (!empty($newTemplateId)) {
                        $widget->setOptionValue('templateId', 'attrib', $newTemplateId);
                        $upgraded = true;
                    }
                    break;

                default:
                    break;
            }

            if (!empty($widgetChangeOption)) {
                $widget->changeOption($option->option, $widgetChangeOption);
                $upgraded = true;
            }
        }

        if ($overrideTemplate == 1) {
            // Decode URL
            $widget->setOptionValue('uri', 'attrib', urldecode($widget->getOptionValue('uri', '')));
        }

        return $upgraded;
    }

    public function saveTemplate(string $template, string $fileName): bool
    {
        return false;
    }
}
