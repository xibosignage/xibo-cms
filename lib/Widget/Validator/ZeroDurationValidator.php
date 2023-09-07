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

use Xibo\Entity\Module;
use Xibo\Entity\Widget;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Widget\Provider\WidgetValidatorInterface;
use Xibo\Widget\Provider\WidgetValidatorTrait;

/**
 * Validate that we have a duration greater than 0
 */
class ZeroDurationValidator implements WidgetValidatorInterface
{
    use WidgetValidatorTrait;

    /**
     * @inheritDoc
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function validate(Module $module, Widget $widget, string $stage): void
    {
        // Videos can have 0 durations (but not if useDuration is selected)
        if ($widget->useDuration === 1 && $widget->duration <= 0) {
            throw new InvalidArgumentException(
                sprintf(__('Duration needs to be above 0 for %s'), $module->name),
                'duration'
            );
        }
    }
}
