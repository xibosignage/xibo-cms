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
