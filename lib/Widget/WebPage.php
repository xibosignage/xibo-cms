<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2015 Daniel Garner
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
use Respect\Validation\Validator as v;

class WebPage extends ModuleWidget
{
    /**
     * Install Files
     */
    public function installFiles()
    {
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/web/modules/vendor/jquery-1.11.1.min.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/web/modules/xibo-layout-scaler.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/web/modules/xibo-webpage-render.js')->save();
    }

    public function validate()
    {
        if (!v::url()->notEmpty()->validate(urldecode($this->getOption('uri'))))
            throw new InvalidArgumentException(__('Please enter a link'));

        if ($this->getUseDuration() == 1 && $this->getDuration() == 0)
            throw new InvalidArgumentException(__('You must enter a duration.'));
    }

    /**
     * Add Media
     */
    public function add()
    {
        $this->setOption('xmds', true);
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setDuration($this->getSanitizer()->getInt('duration'));
        $this->setOption('name', $this->getSanitizer()->getString('name'));
        $this->setOption('transparency', $this->getSanitizer()->getCheckbox('transparency'));
        $this->setOption('uri', urlencode($this->getSanitizer()->getString('uri')));
        $this->setOption('scaling', $this->getSanitizer()->getInt('scaling'));
        $this->setOption('offsetLeft', $this->getSanitizer()->getInt('offsetLeft'));
        $this->setOption('offsetTop', $this->getSanitizer()->getInt('offsetTop'));
        $this->setOption('pageWidth', $this->getSanitizer()->getInt('pageWidth'));
        $this->setOption('pageHeight', $this->getSanitizer()->getInt('pageHeight'));
        $this->setOption('modeid', $this->getSanitizer()->getInt('modeId'));

        // Save the widget
        $this->validate();
        $this->saveWidget();
    }

    /**
     * Edit Media
     */
    public function edit()
    {
        $this->setOption('xmds', true);
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setDuration($this->getSanitizer()->getInt('duration'));
        $this->setOption('name', $this->getSanitizer()->getString('name'));
        $this->setOption('transparency', $this->getSanitizer()->getCheckbox('transparency'));
        $this->setOption('uri', urlencode($this->getSanitizer()->getString('uri')));
        $this->setOption('scaling', $this->getSanitizer()->getInt('scaling'));
        $this->setOption('offsetLeft', $this->getSanitizer()->getInt('offsetLeft'));
        $this->setOption('offsetTop', $this->getSanitizer()->getInt('offsetTop'));
        $this->setOption('pageWidth', $this->getSanitizer()->getInt('pageWidth'));
        $this->setOption('pageHeight', $this->getSanitizer()->getInt('pageHeight'));
        $this->setOption('modeid', $this->getSanitizer()->getInt('modeId'));

        // Save the widget
        $this->validate();
        $this->saveWidget();
    }

    /**
     * Preview code for a module
     * @param int $width
     * @param int $height
     * @param int $scaleOverride The Scale Override
     * @return string The Rendered Content
     */
    public function preview($width, $height, $scaleOverride = 0)
    {
        // If we are opening the web page natively on the device, then we cannot offer a preview
        if ($this->getOption('modeid') == 1)
            return $this->previewIcon();

        return $this->previewAsClient($width, $height, $scaleOverride);
    }

    /**
     * GetResource for Web page Media
     * @param int $displayId
     * @return mixed|string
     */
    public function getResource($displayId = 0)
    {
        // Load in the template
        $data = [];

        // Replace the View Port Width?
        $isPreview = ($this->getSanitizer()->getCheckbox('preview') == 1);

        // Replace the View Port Width?
        $data['viewPortWidth'] = ($isPreview) ? $this->region->width : '[[ViewPortWidth]]';

        // Get some parameters
        $width = $this->getSanitizer()->getDouble('width', 0);
        $height = $this->getSanitizer()->getDouble('height', 0);

        // Work out the url
        $url = urldecode($this->getOption('uri'));
        $url = (preg_match('/^' . preg_quote('http') . "/", $url)) ? $url : 'http://' . $url;

        // Set the iFrame dimensions
        $iFrameWidth = $this->getOption('pageWidth');
        $iFrameHeight = $this->getOption('pageHeight');

        $options = array(
            'modeId' => $this->getOption('modeid'),
            'originalWidth' => intval($this->region->width),
            'originalHeight' => intval($this->region->height),
            'iframeWidth' => intval(($iFrameWidth == '' || $iFrameWidth == 0) ? $this->region->width : $iFrameWidth),
            'iframeHeight' => intval(($iFrameHeight == '' || $iFrameHeight == 0) ? $this->region->height : $iFrameHeight),
            'previewWidth' => intval($width),
            'previewHeight' => intval($height),
            'offsetTop' => intval($this->getOption('offsetTop', 0)),
            'offsetLeft' => intval($this->getOption('offsetLeft', 0)),
            'scale' => ($this->getOption('scaling', 100) / 100),
            'scaleOverride' => $this->getSanitizer()->getDouble('scale_override', 0)
        );

        // Head Content
        $headContent = '<style>#iframe { border:0; }</style>';
        $data['head'] = $headContent;

        // Body content
        // Replace the Body Content with our generated text
        $data['body'] = '<iframe id="iframe" scrolling="no" frameborder="0" src="' . $url . '"></iframe>';

        // After body content
        $javaScriptContent = '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery-1.11.1.min.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-layout-scaler.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-webpage-render.js') . '"></script>';
        $javaScriptContent .= '<script>
            var options = ' . json_encode($options) . '
            $(document).ready(function() {
                $("#content").xiboLayoutScaler(options);
                $("#iframe").xiboIframeScaler(options);
            });
            </script>';

        // Replace the After body Content
        $data['javaScript'] = $javaScriptContent;

        return $this->renderTemplate($data);
    }

    public function IsValid()
    {
        // Can't be sure because the client does the rendering
        return 2;
    }
}