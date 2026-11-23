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

use Xibo\Entity\Module;
use Xibo\Entity\Widget;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Widget\Provider\WidgetValidatorInterface;
use Xibo\Widget\Provider\WidgetValidatorTrait;

/**
 * Ensure a command has been entered somewhere in the widget
 */
class ShellCommandValidator implements WidgetValidatorInterface
{
    use WidgetValidatorTrait;

    /** @inheritDoc */
    public function validate(Module $module, Widget $widget, string $stage): void
    {
        if ($widget->getOptionValue('globalCommand', '') == ''
            && $widget->getOptionValue('androidCommand', '') == ''
            && $widget->getOptionValue('windowsCommand', '') == ''
            && $widget->getOptionValue('linuxCommand', '') == ''
            && $widget->getOptionValue('commandCode', '') == ''
            && $widget->getOptionValue('webosCommand', '') == ''
            && $widget->getOptionValue('tizenCommand', '') == ''
            && $widget->getOptionValue('chromeosCommand', '') == ''
        ) {
            throw new InvalidArgumentException(__('You must enter a command'), 'command');
        }
    }
}
