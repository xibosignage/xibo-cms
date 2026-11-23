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
class DatasetWidgetCompatibility implements WidgetCompatibilityInterface
{
    use WidgetCompatibilityTrait;

    /** @inheritdoc
     */
    public function upgradeWidget(Widget $widget, int $fromSchema, int $toSchema): bool
    {
        $this->getLog()->debug('upgradeWidget: ' . $widget->getId() . ' from: ' . $fromSchema . ' to: ' . $toSchema);

        // Did we originally come from a dataset ticker?
        if ($widget->getOriginalValue('type') === 'datasetticker') {
            $newTemplateId = 'dataset_custom_html';
            $widget->changeOption('css', 'styleSheet');
        } else {
            if ($widget->getOptionValue('overrideTemplate', 0) == 0) {
                $newTemplateId = match ($widget->getOptionValue('templateId', '')) {
                    'light-green' => 'dataset_table_2',
                    'simple-round' => 'dataset_table_3',
                    'transparent-blue' => 'dataset_table_4',
                    'orange-grey-striped' => 'dataset_table_5',
                    'split-rows' => 'dataset_table_6',
                    'dark-round' => 'dataset_table_7',
                    'pill-colored' => 'dataset_table_8',
                    default => 'dataset_table_1',
                };
            } else {
                $newTemplateId = 'dataset_table_custom_html';
            }

            // We have changed the format of columns to be an array in v4.
            $columns = $widget->getOptionValue('columns', '');
            if (!empty($columns)) {
                $widget->setOptionValue('columns', 'attrib', '[' . $columns . ']');
            }
        }

        $widget->setOptionValue('templateId', 'attrib', $newTemplateId);

        return true;
    }

    public function saveTemplate(string $template, string $fileName): bool
    {
        return false;
    }
}
