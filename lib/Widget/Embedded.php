<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-2015 Daniel Garner
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

use Xibo\Factory\MediaFactory;
use Xibo\Helper\Sanitize;
use Xibo\Helper\Theme;

class Embedded extends ModuleWidget
{
    /**
     * Install Files
     */
    public function InstallFiles()
    {
        MediaFactory::createModuleSystemFile('modules/preview/vendor/jquery-1.11.1.min.js')->save();
        MediaFactory::createModuleSystemFile('modules/preview/xibo-layout-scaler.js')->save();
    }

    /**
     * Add Media to the Database
     */
    public function add()
    {
        // Required Attributes
        $this->setDuration(Sanitize::getInt('duration'));
        $this->SetOption('name', Sanitize::getString('name'));
        $this->SetOption('transparency', Sanitize::getCheckbox('transparency'));
        $this->SetOption('scaleContent', Sanitize::getCheckbox('scaleContent'));
        $this->setRawNode('embedHtml', Sanitize::getParam('embedHtml', null));
        $this->setRawNode('embedScript', Sanitize::getParam('embedScript', null));
        $this->setRawNode('embedStyle', Sanitize::getParam('embedStyle', null));

        // Save the widget
        $this->saveWidget();
    }

    /**
     * Edit Media in the Database
     */
    public function edit()
    {
        $this->setDuration(Sanitize::getInt('duration'));
        $this->SetOption('name', Sanitize::getString('name'));
        $this->SetOption('transparency', Sanitize::getCheckbox('transparency'));
        $this->SetOption('scaleContent', Sanitize::getCheckbox('scaleContent'));
        $this->setRawNode('embedHtml', Sanitize::getParam('embedHtml', null));
        $this->setRawNode('embedScript', Sanitize::getParam('embedScript', null));
        $this->setRawNode('embedStyle', Sanitize::getParam('embedStyle', null));

        // Save the widget
        $this->saveWidget();
    }

    public function isValid()
    {
        // Can't be sure because the client does the rendering
        return 2;
    }

    /**
     * GetResource
     * Return the rendered resource to be used by the client (or a preview) for displaying this content.
     * @param integer $displayId If this comes from a real client, this will be the display id.
     * @return mixed
     */
    public function getResource($displayId = 0)
    {
        // Behave exactly like the client.
        $data = [];
        $isPreview = (Sanitize::getCheckbox('preview') == 1);

        // Replace the View Port Width?
        $data['viewPortWidth'] = ($isPreview) ? $this->region->width : '[[ViewPortWidth]]';

        // Clear all linked media.
        $this->clearMedia();

        // Embedded Html
        $html = $this->parseLibraryReferences($isPreview, $this->getRawNode('embedHtml', null));

        // Include some vendor items
        $javaScriptContent  = '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery-1.11.1.min.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-layout-scaler.js') . '"></script>';

        // Get the Script
        $javaScriptContent .= $this->parseLibraryReferences($isPreview, $this->getRawNode('embedScript', null));

        // Get the Style Sheet
        $styleSheetContent = $this->parseLibraryReferences($isPreview, $this->getRawNode('embedStyle', null));

        // Set some options
        $options = array(
            'originalWidth' => $this->region->width,
            'originalHeight' => $this->region->height,
            'previewWidth' => Sanitize::getDouble('width', 0),
            'previewHeight' => Sanitize::getDouble('height', 0),
            'scaleOverride' => Sanitize::getDouble('scale_override', 0)
        );

        // Add an options variable with some useful information for scaling
        $javaScriptContent .= '<script type="text/javascript">';
        $javaScriptContent .= '   var options = ' . json_encode($options) . ';';
        $javaScriptContent .= '   $(document).ready(function() { EmbedInit(); });';
        $javaScriptContent .= '</script>';

        // Do we want to scale?
        if ($this->GetOption('scaleContent') == 1) {
            $javaScriptContent .= '<script>
                $(document).ready(function() {
                    $("body").xiboLayoutScaler(options);
                });
            </script>';
        }

        // Add our fonts.css file
        $headContent = '<link href="' . $this->getResourceUrl('fonts.css') . ' rel="stylesheet" media="screen">';
        $headContent .= '<style type="text/css">' . file_get_contents(Theme::uri('css/client.css', true)) . '</style>';

        $data['head'] = $headContent;

        // Replace the Style Sheet Content with our generated Style Sheet
        $data['styleSheet'] = $styleSheetContent;

        // Replace the Head Content with our generated java script
        $data['javaScript'] = $javaScriptContent;

        // Replace the Body Content with our generated text
        $data['body'] = $html;

        // Update and save widget if we've changed our assignments.
        if ($this->hasMediaChanged())
            $this->widget->save(['saveWidgetOptions' => false]);

        return $this->renderTemplate($data);
    }
}
