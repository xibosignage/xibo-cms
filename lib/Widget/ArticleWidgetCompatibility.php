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

namespace Xibo\Widget;
use Xibo\Entity\Widget;
use Xibo\Widget\Provider\WidgetCompatibilityInterface;
use Xibo\Widget\Provider\WidgetCompatibilityTrait;

/**
 * Convert article old templateId to new templateId
 */
class ArticleWidgetCompatibility implements WidgetCompatibilityInterface
{
    use WidgetCompatibilityTrait;

    /** @inheritdoc
     */
    public function upgradeWidget(Widget $widget, int $fromSchema, int $toSchema): void
    {
        $this->getLog()->debug('upgradeWidget: '. $widget->getId(). ' from: '. $fromSchema.' to: '.$toSchema);

        foreach ($widget->widgetOptions as $option) {
            $templateId = $widget->getOptionValue('templateId', '');

            if ($option->option === 'templateId') {
                switch ($templateId) {
                    case 'media-rss-image-only':
                        $widget->setOptionValue('templateId', 'attrib', 'article_image_only');
                        break;

                    case 'media-rss-with-left-hand-text':
                        $widget->setOptionValue('templateId', 'attrib', 'article_with_left_hand_text');
                        break;

                    case 'media-rss-with-title':
                        $widget->setOptionValue('templateId', 'attrib', 'article_with_title');
                        break;

                    case 'prominent-title-with-desc-and-name-separator':
                        $widget->setOptionValue('templateId', 'attrib', 'article_with_desc_and_name_separator');
                        break;

                    case 'title-only':
                        $widget->setOptionValue('templateId', 'attrib', 'article_title_only');
                        break;

                    default:
                        break;
                }
            }
        }
    }

    public function saveTemplate(string $template, string $fileName): bool
    {
        return false;
    }

}
