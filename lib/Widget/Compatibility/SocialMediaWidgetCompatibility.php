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
class SocialMediaWidgetCompatibility implements WidgetCompatibilityInterface
{
    use WidgetCompatibilityTrait;

    /** @inheritdoc
     */
    public function upgradeWidget(Widget $widget, int $fromSchema, int $toSchema): bool
    {
        $this->getLog()->debug('upgradeWidget: '. $widget->getId(). ' from: '. $fromSchema.' to: '.$toSchema);

        $upgraded = false;
        $newTemplateId = null;
        foreach ($widget->widgetOptions as $option) {
            $templateId = $widget->getOptionValue('templateId', '');

            if ($option->option === 'templateId') {
                switch ($templateId) {
                    case 'full-timeline-np':
                        $newTemplateId = 'social_media_static_1';
                        break;

                    case 'full-timeline':
                        $newTemplateId = 'social_media_static_2';
                        break;

                    case 'tweet-only':
                        $newTemplateId = 'social_media_static_3';
                        break;

                    case 'tweet-with-profileimage-left':
                        $newTemplateId = 'social_media_static_4';
                        break;

                    case 'tweet-with-profileimage-right':
                        $newTemplateId = 'social_media_static_5';
                        break;

                    case 'tweet-1':
                        $newTemplateId = 'social_media_static_6';
                        break;

                    case 'tweet-2':
                        $newTemplateId = 'social_media_static_7';
                        break;

                    case 'tweet-4':
                        $newTemplateId = 'social_media_static_8';
                        break;

                    case 'tweet-6NP':
                        $newTemplateId = 'social_media_static_9';
                        break;

                    case 'tweet-6PL':
                        $newTemplateId = 'social_media_static_10';
                        break;

                    case 'tweet-7':
                        $newTemplateId = 'social_media_static_11';
                        break;

                    case 'tweet-8':
                        $newTemplateId = 'social_media_static_12';
                        break;

                    default:
                        break;
                }

                if (!empty($newTemplateId)) {
                    $widget->setOptionValue('templateId', 'attrib', $newTemplateId);
                    $upgraded = true;
                }
            }
        }

        return $upgraded;
    }

    public function saveTemplate(string $template, string $fileName): bool
    {
        return false;
    }
}
