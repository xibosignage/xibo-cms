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
use Xibo\Helper\Translate;

class Clock extends ModuleWidget
{
    public $codeSchemaVersion = 1;

    public function installFiles()
    {
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/jquery-1.11.1.min.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/jquery-cycle-2.1.6.min.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/moment.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/flipclock.min.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-layout-scaler.js')->save();
    }

    /**
     * Validate
     */
    public function validate()
    {
        // Validate
        if ($this->getUseDuration() == 1 && !v::intType()->min(1)->validate($this->getDuration()))
            throw new \InvalidArgumentException(__('Please enter a duration.'));
    }

    /**
     * Adds a Clock Widget
     * @SWG\Post(
     *  path="/playlist/widget/clock/{playlistId}",
     *  operationId="WidgetClockAdd",
     *  tags={"widget"},
     *  summary="Add a Clock Widget",
     *  description="Add a new Clock Widget to the specified playlist",
     *  @SWG\Parameter(
     *      name="playlistId",
     *      in="path",
     *      description="The playlist ID to add a Clock widget to",
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
     *      name="themeId",
     *      in="formData",
     *      description="Flag (0 , 1) for Analogue clock the light and dark theme",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="clockTypeId",
     *      in="formData",
     *      description="Type of a clock widget 1-Analogue, 2-Digital, 3-Flip clock",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="offset",
     *      in="formData",
     *      description="The offset in minutes that should be applied to the current time, if a counter is selected then date/time to run from in the format Y-m-d H:i:s",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="format",
     *      in="formData",
     *      description="For digital clock, format in which the time should be displayed example [HH:mm]",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="showSeconds",
     *      in="formData",
     *      description="For Flip Clock, should the clock show seconds or not",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ClockFace",
     *      in="formData",
     *      description="For Flip Clock, supported options: TwelveHourClock TwentyFourHourClock HourlyCounter MinuteCounter DailyCounter",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Widget"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new widget",
     *          type="string"
     *      )
     *  )
     * )
     */
    public function add()
    {
        // You must also provide a duration (all media items must provide this field)
        $this->setOption('name', $this->getSanitizer()->getString('name'));
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));
        $this->setOption('theme', $this->getSanitizer()->getInt('themeId', 0));
        $this->setOption('clockTypeId', $this->getSanitizer()->getInt('clockTypeId', 1));
        $this->setOption('offset', $this->getSanitizer()->getString('offset', 0));
        $this->setRawNode('format', $this->getSanitizer()->getParam('ta_text', $this->getSanitizer()->getParam('format', '')));
        $this->setOption('showSeconds', $this->getSanitizer()->getCheckbox('showSeconds', 1));
        $this->setOption('clockFace', $this->getSanitizer()->getString('clockFace', 'TwentyFourHourClock'));

        $this->validate();

        // Save the widget
        $this->saveWidget();
    }

     /**
     * Edit Clock
     */
    public function edit()
    {
        // You must also provide a duration (all media items must provide this field)
        $this->setOption('name', $this->getSanitizer()->getString('name'));
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));
        $this->setOption('theme', $this->getSanitizer()->getInt('themeId', 0));
        $this->setOption('clockTypeId', $this->getSanitizer()->getInt('clockTypeId', 1));
        $this->setOption('offset', $this->getSanitizer()->getString('offset', 0));
        $this->setRawNode('format', $this->getSanitizer()->getParam('ta_text', $this->getSanitizer()->getParam('format', '')));
        $this->setOption('showSeconds', $this->getSanitizer()->getCheckbox('showSeconds'));
        $this->setOption('clockFace', $this->getSanitizer()->getString('clockFace'));

        $this->validate();

        // Save the widget
        $this->saveWidget();
    }

    /**
     * Supported Clock Faces
     * @return array
     */
    public function clockFaces()
    {
        return [
            ['id' => 'TwelveHourClock', 'value' => __('12h Clock')],
            ['id' => 'TwentyFourHourClock', 'value' => __('24h Clock')],
            ['id' => 'HourlyCounter', 'value' => __('Hourly Counter')],
            ['id' => 'MinuteCounter', 'value' => __('Minute Counter')],
            ['id' => 'DailyCounter', 'value' => __('Daily Counter')]
        ];
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
        $isPreview = ($this->getSanitizer()->getCheckbox('preview') == 1);

        // After body content
        $options = [
            'previewWidth' => $this->getSanitizer()->getDouble('width', 0),
            'previewHeight' => $this->getSanitizer()->getDouble('height', 0),
            'originalWidth' => $this->region->width,
            'originalHeight' => $this->region->height,
            'scaleOverride' => $this->getSanitizer()->getDouble('scale_override', 0)
        ];

        // Clock Type
        switch ($this->getOption('clockTypeId', 1)) {

            case 1:
                // Analogue
                $template = 'clock-get-resource-analog';

                // Render our clock face
                $theme = ($this->getOption('theme') == 1 ? 'light' : 'dark');
                $theme_face = ($this->getOption('theme') == 1 ? 'clock_bg_modern_light.png' : 'clock_bg_modern_dark.png');

                $data['clockFace'] = base64_encode(file_get_contents(PROJECT_ROOT . '/modules/clock/' . $theme_face));

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
                $headContent  = '<link href = "' . (($isPreview) ? $this->getApp()->urlFor('library.font.css') : 'fonts.css') . '" rel="stylesheet" media="screen">';
                $headContent .= '<style type = "text/css" > ' . file_get_contents($this->getConfig()->uri('css/client.css', true)) . '</style>';

                $data['head'] = $headContent;

                break;

            case 3:
                // Flip Clock
                $template = 'clock-get-resource-flip';

                // Head Content (CSS for flip clock)
                $data['head'] = '<style type="text/css">' . file_get_contents(PROJECT_ROOT . '/modules/vendor/flipclock.css') . '</style>';
                $data['offset'] = $this->getOption('offset', '0');
                $data['duration'] = $this->getDuration();
                $data['clockFace'] = $this->getOption('clockFace', 'TwentyFourHourClock');
                $data['showSeconds'] = $this->getOption('showSeconds', 1);

                // After body content
                $javaScriptContent  = '<script type = "text/javascript" src = "' . $this->getResourceUrl('vendor/jquery-1.11.1.min.js') . '" ></script > ';
                $javaScriptContent .= '<script type = "text/javascript" src = "' . $this->getResourceUrl('vendor/flipclock.min.js') . '" ></script > ';

                // Replace the After body Content
                $data['javaScript'] = $javaScriptContent;

                break;
        }

        // If we are a preview, then pass in the width and height
        $data['options'] = json_encode($options);

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

    /** @inheritdoc */
    public function getCacheDuration()
    {
        // We have a long cache interval because we don't depend on any external data.
        return 86400 * 365;
    }
}
