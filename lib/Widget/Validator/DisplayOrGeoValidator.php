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

namespace Xibo\Widget\Validator;

use Respect\Validation\Validator as v;
use Xibo\Entity\Module;
use Xibo\Entity\Widget;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Widget\Provider\WidgetValidatorInterface;
use Xibo\Widget\Provider\WidgetValidatorTrait;

/**
 * Validate that we either use display location or a lat/lng have been set
 */
class DisplayOrGeoValidator implements WidgetValidatorInterface
{
    use WidgetValidatorTrait;

    /** @inheritDoc */
    public function validate(Module $module, Widget $widget, string $stage): void
    {
        $useDisplayLocation = $widget->getOptionValue('useDisplayLocation', null);
        if ($useDisplayLocation === null) {
            foreach ($module->properties as $property) {
                if ($property->id === 'useDisplayLocation') {
                    $useDisplayLocation = $property->default;
                }
            }
        }
        if ($useDisplayLocation === 0) {
            // Validate lat/long
            // only if they have been provided (our default is the CMS lat/long).
            $lat = $widget->getOptionValue('latitude', null);
            if (!empty($lat) && !v::latitude()->validate($lat)) {
                throw new InvalidArgumentException(__('The latitude entered is not valid.'), 'latitude');
            }

            $lng = $widget->getOptionValue('longitude', null);
            if (!empty($lng) && !v::longitude()->validate($lng)) {
                throw new InvalidArgumentException(__('The longitude entered is not valid.'), 'longitude');
            }
        }
    }
}
