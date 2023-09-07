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

use Illuminate\Support\Str;
use Xibo\Entity\Module;
use Xibo\Entity\Widget;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Widget\Provider\WidgetValidatorInterface;
use Xibo\Widget\Provider\WidgetValidatorTrait;

/**
 * Validate that we have a duration greater than 0
 */
class RemoteUrlsZeroDurationValidator implements WidgetValidatorInterface
{
    use WidgetValidatorTrait;

    /**
     * @inheritDoc
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function validate(Module $module, Widget $widget, string $stage): void
    {
        $url = urldecode($widget->getOptionValue('uri', ''));
        if ($widget->useDuration === 1
            && $widget->duration <= 0
            && !Str::startsWith($url, 'file://')
            && Str::contains($url, '://')
        ) {
            // This is not a locally stored file, and so we should have a duration
            throw new InvalidArgumentException(
                __('The duration needs to be greater than 0 for remote URLs'),
                'duration'
            );
        } else if ($widget->useDuration === 1 && $widget->duration <= 0) {
            // Locally stored file, still needs a positive duration.
            throw new InvalidArgumentException(
                __('The duration needs to be above 0 for a locally stored file '),
                'duration'
            );
        }
    }
}
