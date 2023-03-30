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
 * Convert widget from an old schema to a new schema
 */
class DatasetWidgetCompatibility implements WidgetCompatibilityInterface
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
                    case 'empty':
                        $widget->setOptionValue('templateId', 'attrib', 'dataset_table_1');
                        break;

                    case 'light-green':
                        $widget->setOptionValue('templateId', 'attrib', 'dataset_table_2');
                        break;

                    case 'simple-round':
                        $widget->setOptionValue('templateId', 'attrib', 'dataset_table_3');
                        break;

                    case 'transparent-blue':
                        $widget->setOptionValue('templateId', 'attrib', 'dataset_table_4');
                        break;

                    case 'orange-grey-striped':
                        $widget->setOptionValue('templateId', 'attrib', 'dataset_table_5');
                        break;

                    case 'split-rows':
                        $widget->setOptionValue('templateId', 'attrib', 'dataset_table_6');
                        break;

                    case 'dark-round':
                        $widget->setOptionValue('templateId', 'attrib', 'dataset_table_7');
                        break;

                    case 'pill-colored':
                        $widget->setOptionValue('templateId', 'attrib', 'dataset_table_8');
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
