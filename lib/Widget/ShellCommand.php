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

class ShellCommand extends ModuleWidget
{
    public function validate()
    {
        if ($this->getOption('windowsCommand') == '' && $this->getOption('linuxCommand') == '' && $this->getOption('commandCode') == '')
            throw new InvalidArgumentException(__('You must enter a command'));
    }

    /**
     * Add Media
     */
    public function add()
    {
        // Any Options (we need to encode shell commands, as they sit on the options rather than the raw
        $this->setOption('name', $this->getSanitizer()->getString('name'));
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));

        // Commands
        $windows = $this->getSanitizer()->getString('windowsCommand');
        $linux = $this->getSanitizer()->getString('linuxCommand');

        $this->setOption('launchThroughCmd', $this->getSanitizer()->getCheckbox('launchThroughCmd'));
        $this->setOption('terminateCommand', $this->getSanitizer()->getCheckbox('terminateCommand'));
        $this->setOption('useTaskkill', $this->getSanitizer()->getCheckbox('useTaskkill'));
        $this->setOption('commandCode', $this->getSanitizer()->getString('commandCode'));
        $this->setOption('windowsCommand', urlencode($windows));
        $this->setOption('linuxCommand', urlencode($linux));

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
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));
        $this->setOption('name', $this->getSanitizer()->getString('name'));

        // Commands
        $windows = $this->getSanitizer()->getString('windowsCommand');
        $linux = $this->getSanitizer()->getString('linuxCommand');

        $this->setOption('launchThroughCmd', $this->getSanitizer()->getCheckbox('launchThroughCmd'));
        $this->setOption('terminateCommand', $this->getSanitizer()->getCheckbox('terminateCommand'));
        $this->setOption('useTaskkill', $this->getSanitizer()->getCheckbox('useTaskkill'));
        $this->setOption('commandCode', $this->getSanitizer()->getString('commandCode'));
        $this->setOption('windowsCommand', urlencode($windows));
        $this->setOption('linuxCommand', urlencode($linux));

        // Save the widget
        $this->validate();
        $this->saveWidget();
    }

    public function preview($width, $height, $scaleOverride = 0)
    {
        if ($this->module->previewEnabled == 0)
            return parent::Preview($width, $height);

        $windows = $this->getOption('windowsCommand');
        $linux = $this->getOption('linuxCommand');

        if ($windows == '' && $linux == '') {
            return __('Stored Command: %s', $this->getOption('commandCode'));
        }
        else {

            $preview  = '<p>' . __('Windows Command') . ': ' . urldecode($windows) . '</p>';
            $preview .= '<p>' . __('Linux Command') . ': ' . urldecode($linux) . '</p>';

            return $preview;
        }
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

    /**
     * @param array $data
     * @return array
     */
    public function setTemplateData($data)
    {
        $data['commands'] = $this->commandFactory->query();
        return $data;
    }
}
