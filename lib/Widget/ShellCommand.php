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

use Xibo\Exception\InvalidArgumentException;

class ShellCommand extends ModuleWidget
{

    /**
     * Javascript functions for the layout designer
     */
    public function layoutDesignerJavaScript()
    {
        return 'shellcommand-designer-javascript';
    }

    /**
     * Edit Widget
     *
     * @SWG\Put(
     *  path="/playlist/widget/{widgetId}?shellCommand",
     *  operationId="WidgetShellCommandEdit",
     *  tags={"widget"},
     *  summary="Edit a Shell Command Widget",
     *  description="Edit a Shell Command Widget. This call will replace existing Widget object, all not supplied parameters will be set to default.",
     *  @SWG\Parameter(
     *      name="widgetId",
     *      in="path",
     *      description="The WidgetId to Edit",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="Optional Widget Name",
     *      type="string",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="duration",
     *      in="formData",
     *      description="The Widget Duration",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="useDuration",
     *      in="formData",
     *      description="(0, 1) Select 1 only if you will provide duration parameter as well",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="enableStat",
     *      in="formData",
     *      description="The option (On, Off, Inherit) to enable the collection of Widget Proof of Play statistics",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="windowsCommand",
     *      in="formData",
     *      description="Enter a Windows command line compatible command",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="linuxCommand",
     *      in="formData",
     *      description="Enter a Android / Linux command line compatible command",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="launchThroughCmd",
     *      in="formData",
     *      description="flag (0,1) Windows only, Should the player launch this command through the windows command line (cmd.exe)? This is useful for batch files, if you try to terminate this command only the command line will be terminated",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="terminateCommand",
     *      in="formData",
     *      description="flag (0,1) Should the player forcefully terminate the command after the duration specified, 0 to let the command terminate naturally",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="useTaskkill",
     *      in="formData",
     *      description="flag (0,1) Windows only, should the player use taskkill to terminate commands",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="commandCode",
     *      in="formData",
     *      description="Enter a reference code for exiting command in CMS",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation"
     *  )
     * )
     *
     * @throws \Xibo\Exception\XiboException
     */
    public function edit()
    {
        // Any Options (we need to encode shell commands, as they sit on the options rather than the raw
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));
        $this->setOption('name', $this->getSanitizer()->getString('name'));
        $this->setOption('enableStat', $this->getSanitizer()->getString('enableStat'));

        // Commands
        $windows = $this->getSanitizer()->getString('windowsCommand');
        $linux = $this->getSanitizer()->getString('linuxCommand');
        $webos = $this->getSanitizer()->getString('webosCommand');
        $tizen = $this->getSanitizer()->getString('tizenCommand');

        $this->setOption('launchThroughCmd', $this->getSanitizer()->getCheckbox('launchThroughCmd'));
        $this->setOption('terminateCommand', $this->getSanitizer()->getCheckbox('terminateCommand'));
        $this->setOption('useTaskkill', $this->getSanitizer()->getCheckbox('useTaskkill'));
        $this->setOption('commandCode', $this->getSanitizer()->getString('commandCode'));
        $this->setOption('windowsCommand', urlencode($windows));
        $this->setOption('linuxCommand', urlencode($linux));
        $this->setOption('webosCommand', urlencode($webos));
        $this->setOption('tizenCommand', urlencode($tizen));

        // Save the widget
        $this->isValid();
        $this->saveWidget();
    }

    /** @inheritdoc */
    public function preview($width, $height, $scaleOverride = 0)
    {
        if ($this->module->previewEnabled == 0)
            return parent::Preview($width, $height);

        $commandCode = $this->getOption('commandCode');

        if (!empty($commandCode)) {
            return __('Stored Command: %s', $commandCode);
        } else {
            $preview  = '<p>' . __('Windows Command') . ': ' . urldecode($this->getOption('windowsCommand')) . '</p>';
            $preview .= '<p>' . __('Android/Linux Command') . ': ' . urldecode($this->getOption('linuxCommand')) . '</p>';
            $preview .= '<p>' . __('webOS Command') . ': ' . urldecode($this->getOption('webosCommand')) . '</p>';
            $preview .= '<p>' . __('Tizen Command') . ': ' . urldecode($this->getOption('tizenCommand')) . '</p>';

            return $preview;
        }
    }

    /** @inheritdoc */
    public function isValid()
    {
        if ($this->getOption('windowsCommand') == '' && $this->getOption('linuxCommand') == '' && $this->getOption('commandCode') == '' && $this->getOption('webosCommand' == '') && $this->getOption('tizenCommand') == '')
            throw new InvalidArgumentException(__('You must enter a command'), 'command');

        return self::$STATUS_PLAYER;
    }

    /** @inheritdoc */
    public function setTemplateData($data)
    {
        $data['commands'] = $this->commandFactory->query();
        return $data;
    }

    /** @inheritdoc */
    public function getResource($displayId)
    {
        // Get resource isn't required for this module.
        return null;
    }
}
