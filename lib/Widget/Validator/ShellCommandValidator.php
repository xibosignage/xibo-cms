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
        ) {
            throw new InvalidArgumentException(__('You must enter a command'), 'command');
        }
    }
}
