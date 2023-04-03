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
    public function upgradeWidget(Widget $widget, int $fromSchema, int $toSchema): void
    {
        $this->getLog()->debug('upgradeWidget: '. $widget->getId(). ' from: '. $fromSchema.' to: '.$toSchema);

        foreach ($widget->widgetOptions as $option) {
            switch ($option->option) {
                case 'background-color':
                    $widget->changeOption($option->option, 'itemBackgroundColor');
                    break;

                case 'title-color':
                    $widget->changeOption($option->option, 'itemTitleColor');
                    break;

                case 'name-color':
                    $widget->changeOption($option->option, 'itemNameColor');
                    break;

                case 'description-color':
                    $widget->changeOption($option->option, 'itemDescriptionColor');
                    break;

                case 'font-size':
                    $widget->changeOption($option->option, 'itemFontSize');
                    break;

                case 'image-fit':
                    $widget->changeOption($option->option, 'itemImageFit');
                    break;

                default:
                    break;
            }
        }
    }

    public function saveTemplate(string $template, string $fileName): bool
    {
        return false;
    }

}
