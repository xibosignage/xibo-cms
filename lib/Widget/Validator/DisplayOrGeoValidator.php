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
