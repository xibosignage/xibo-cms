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
class DatasetWidgetCompatibility implements WidgetCompatibilityInterface
{
    use WidgetCompatibilityTrait;

    /** @inheritdoc
     */
    public function upgradeWidget(Widget $widget, int $fromSchema, int $toSchema): bool
    {
        $this->getLog()->debug('upgradeWidget: '. $widget->getId(). ' from: '. $fromSchema.' to: '.$toSchema);

        // Track if we've been upgraded.
        $upgraded = false;

        // Did we originally come from a data set ticker?
        if ($widget->getOriginalValue('type') === 'datasetticker') {
            $newTemplateId = 'dataset_custom_html';
            $widget->changeOption('css', 'styleSheet');
        } else {
            $newTemplateId = null;
            $overrideTemplate = $widget->getOptionValue('overrideTemplate', 0);
            $templateId = $widget->getOptionValue('templateId', '');

            foreach ($widget->widgetOptions as $option) {
                if ($option->option === 'templateId') {
                    if ($overrideTemplate == 0) {
                        switch ($templateId) {
                            case 'empty':
                                $newTemplateId = 'dataset_table_1';
                                break;

                            case 'light-green':
                                $newTemplateId = 'dataset_table_2';
                                break;

                            case 'simple-round':
                                $newTemplateId = 'dataset_table_3';
                                break;

                            case 'transparent-blue':
                                $newTemplateId = 'dataset_table_4';
                                break;

                            case 'orange-grey-striped':
                                $newTemplateId = 'dataset_table_5';
                                break;

                            case 'split-rows':
                                $newTemplateId = 'dataset_table_6';
                                break;

                            case 'dark-round':
                                $newTemplateId = 'dataset_table_7';
                                break;

                            case 'pill-colored':
                                $newTemplateId = 'dataset_table_8';
                                break;

                            default:
                                break;
                        }
                    } else {
                        $newTemplateId = 'dataset_table_custom_html';
                    }
                }
            }

            // We have changed the format of columns to be an array in v4.
            $columns = $widget->getOptionValue('columns', '');
            if (!empty($columns)) {
                $widget->setOptionValue('columns', 'attrib', '[' . $columns . ']');
                $upgraded = true;
            }
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
