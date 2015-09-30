<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2014-15 Daniel Garner
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
 *
 */
namespace Xibo\Widget;

use Respect\Validation\Validator as v;
use Xibo\Factory\MediaFactory;
use Xibo\Helper\Sanitize;
use Xibo\Helper\Theme;
use Xibo\Helper\Translate;

class Clock extends ModuleWidget
{
    public $codeSchemaVersion = 1;

    public function installFiles()
    {
        MediaFactory::createModuleSystemFile('modules/vendor/jquery-1.11.1.min.js')->save();
        MediaFactory::createModuleSystemFile('modules/vendor/jquery-cycle-2.1.6.min.js')->save();
        MediaFactory::createModuleSystemFile('modules/vendor/moment.js')->save();
        MediaFactory::createModuleSystemFile('modules/vendor/flipclock.min.js')->save();
        MediaFactory::createModuleSystemFile('modules/xibo-layout-scaler.js')->save();
    }

    /**
     * Validate
     */
    public function validate()
    {
        // Validate
        if (!v::int()->min(1)->validate($this->getDuration()))
            throw new \InvalidArgumentException(__('You must enter a duration.'));
    }

    /**
     * Add Media to the Database
     */
    public function add()
    {
        // You must also provide a duration (all media items must provide this field)
        $this->setOption('name', Sanitize::getString('name'));
        $this->setDuration(Sanitize::getInt('duration'));
        $this->setOption('theme', Sanitize::getInt('themeId', 0));
        $this->setOption('clockTypeId', Sanitize::getInt('clockTypeId', 1));
        $this->setOption('offset', Sanitize::getInt('offset', 0));
        $this->setRawNode('format', Sanitize::getParam('ta_text', ''));

        $this->validate();

        // Save the widget
        $this->saveWidget();
    }

    /**
     * Edit Media in the Database
     */
    public function edit()
    {
        // You must also provide a duration (all media items must provide this field)
        $this->setOption('name', Sanitize::getString('name', $this->getOption('name')));
        $this->setDuration(Sanitize::getInt('duration'));
        $this->setOption('theme', Sanitize::getInt('themeId', 0));
        $this->setOption('clockTypeId', Sanitize::getInt('clockTypeId', 1));
        $this->setOption('offset', Sanitize::getInt('offset', 0));
        $this->setRawNode('format', Sanitize::getParam('ta_text', ''));

        $this->validate();

        // Save the widget
        $this->saveWidget();
    }

    /**
     * GetResource
     * Return the rendered resource to be used by the client (or a preview) for displaying this content.
     * @param integer $displayId If this comes from a real client, this will be the display id.
     * @return mixed
     */
    public function getResource($displayId = 0)
    {
        $template = null;
        $data = [];
        $isPreview = (Sanitize::getCheckbox('preview') == 1);

        // Clock Type
        switch ($this->getOption('clockTypeId', 1)) {

            case 1:
                // Analogue
                $template = 'clock-get-resource-analog';

                // Render our clock face
                $theme = ($this->getOption('theme') == 1 ? 'light' : 'dark');
                $theme_face = ($this->getOption('theme') == 1 ? 'clock_bg_modern_light.png' : 'clock_bg_modern_dark.png');

                $data['clockFace'] = base64_encode(file_get_contents('modules/clock/' . $theme_face));

                // Light or dark?
                $data['clockTheme'] = $theme;
                $data['offset'] = $this->getOption('offset', 0);

                // After body content
                $javaScriptContent = '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery-1.11.1.min.js') . '"></script>';
                $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/moment.js') . '"></script>';

                // Replace the After body Content
                $data['javaScript'] = $javaScriptContent;
                break;

            case 2:
                // Digital
                // Digital clock is essentially a cut down text module which always fits to the region
                $template = 'get-resource';

                // Extract the format from the raw node in the XLF
                $format = $this->getRawNode('format', null);

                // Strip out the bit between the [] brackets and use that as the format mask for moment.
                $matches = '';
                preg_match_all('/\[.*?\]/', $format, $matches);

                foreach ($matches[0] as $subs) {
                    $format = str_replace($subs, '<span class="clock" format="' . str_replace('[', '', str_replace(']', '', $subs)) . '"></span>', $format);
                }

                // Replace all the subs
                $data['body'] = $format;

                // After body content
                $options = array(
                    'previewWidth' => Sanitize::getDouble('width', 0),
                    'previewHeight' => Sanitize::getDouble('height', 0),
                    'originalWidth' => $this->region->width,
                    'originalHeight' => $this->region->height,
                    'scaleOverride' => Sanitize::getDouble('scale_override', 0)
                );

                $javaScriptContent = '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery-1.11.1.min.js') . '"></script>';
                $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/moment.js') . '"></script>';
                $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-layout-scaler.js') . '"></script>';
                $javaScriptContent .= '<script type="text/javascript">
                    var locale = "' . Translate::GetJsLocale() . '";
                    var options = ' . json_encode($options) . ';

                    function updateClock() {
                        $(".clock").each(function() {
                            $(this).html(moment().add(' . $this->getOption('offset', 0) . ', "m").format($(this).attr("format")));
                        });
                    }

                    $(document).ready(function() {
                                        moment.locale(locale);
                        updateClock();
                        setInterval(updateClock, 1000);
                        $("body").xiboLayoutScaler(options);
                    });
                </script>';

                // Replace the After body Content
                $data['javaScript'] = $javaScriptContent;

                // Add our fonts.css file
                $headContent  = '<link href = "' . $this->getResourceUrl('fonts.css') . '" rel="stylesheet" media="screen">';
                $headContent .= '<style type = "text/css" > ' . file_get_contents(Theme::uri('css/client.css', true)) . '</style>';

                $data['head'] = $headContent;

                break;

            case 3:
                // Flip Clock
                $template = 'clock-get-resource-flip';

                // Head Content (CSS for flip clock)
                $data['head'] = '<style type="text/css">' . file_get_contents('modules/vendor/flipclock.css') . ' </style>';
                $data['offset'] = $this->GetOption('offset', 0);

                // After body content
                $javaScriptContent  = '<script type = "text/javascript" src = "' . $this->getResourceUrl('vendor/jquery-1.11.1.min.js') . '" ></script > ';
                $javaScriptContent .= '<script type = "text/javascript" src = "' . $this->getResourceUrl('vendor/flipclock.min.js') . '" ></script > ';

                // Replace the After body Content
                $data['javaScript'] = $javaScriptContent;

                break;
        }

        // If we are a preview, then pass in the width and height
        $data['previewWidth'] = Sanitize::getDouble('width');
        $data['previewHeight'] = Sanitize::getDouble('height');

        // Replace the View Port Width?
        $data['viewPortWidth'] = ($isPreview) ? $this->region->width : '[[ViewPortWidth]]';

        // Return that content.
        return $this->renderTemplate($data, $template);
    }

    /**
     * Is Valid
     * @return int
     */
    public function isValid()
    {
        // Using the information you have in your module calculate whether it is valid or not.
        // 0 = Invalid
        // 1 = Valid
        // 2 = Unknown
        return 1;
    }
}
