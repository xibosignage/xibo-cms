<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2012-15 Daniel Garner
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

use InvalidArgumentException;
use Xibo\Helper\Sanitize;

class ShellCommand extends ModuleWidget
{
    public function validate()
    {
        if ($this->getOption('windowsCommand') == '' && $this->getOption('linuxCommand') == '')
            throw new InvalidArgumentException(__('You must enter a command'));
    }

    /**
     * Add Media
     */
    public function add()
    {
        // Any Options (we need to encode shell commands, as they sit on the options rather than the raw
        $this->setOption('name', Sanitize::getString('name'));
        $this->setDuration(1);
        $this->SetOption('windowsCommand', urlencode(Sanitize::getString('windowsCommand')));
        $this->SetOption('linuxCommand', urlencode(Sanitize::getString('linuxCommand')));

        // Save the widget
        $this->validate();
        $this->saveWidget();
    }

    /**
     * Edit Media
     */
    public function edit()
    {
        // Any Options (we need to encode shell commands, as they sit on the options rather than the raw
        $this->setDuration(1);
        $this->setOption('name', Sanitize::getString('name', $this->getOption('name')));
        $this->SetOption('windowsCommand', urlencode(Sanitize::getString('windowsCommand')));
        $this->SetOption('linuxCommand', urlencode(Sanitize::getString('linuxCommand')));

        // Save the widget
        $this->validate();
        $this->saveWidget();
    }

    public function preview($width, $height, $scaleOverride = 0)
    {
        if ($this->module->previewEnabled == 0)
            return parent::Preview($width, $height);

        $msgWindows = __('Windows Command');
        $msgLinux = __('Linux Command');

        $preview = '';
        $preview .= '<p>' . $msgWindows . ': ' . urldecode($this->GetOption('windowsCommand')) . '</p>';
        $preview .= '<p>' . $msgLinux . ': ' . urldecode($this->GetOption('linuxCommand')) . '</p>';

        return $preview;
    }

    public function hoverPreview()
    {
        return $this->Preview(0, 0);
    }

    public function isValid()
    {
        // Client dependant
        return 2;
    }
}
