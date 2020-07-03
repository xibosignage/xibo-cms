<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\Translate;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * Class WorldClock
 * @package Xibo\Widget
 */
class WorldClock extends ModuleWidget
{
    public $codeSchemaVersion = 1;

    /**
     * @inheritDoc
     */
    public function init()
    {
        $this->resourceFolder = PROJECT_ROOT . '/modules/Worldclock';

        // Initialise extra validation rules
        v::with('Xibo\\Validation\\Rules\\');
    }
    
    /**
     * @inheritDoc
     */
    public function installOrUpdate($moduleFactory)
    {
        if ($this->module == null) {
            // Install
            $module = $moduleFactory->createEmpty();
            $module->name = 'WorldClock';
            $module->type = 'worldclock';
            $module->class = 'Xibo\Widget\WorldClock';
            $module->description = 'WorldClock Module';
            $module->enabled = 1;
            $module->previewEnabled = 1;
            $module->assignable = 1;
            $module->regionSpecific = 1;
            $module->renderAs = 'html';
            $module->schemaVersion = $this->codeSchemaVersion;
            $module->defaultDuration = 60;
            $module->settings = [];
            $module->installName = 'worldclock';

            $this->setModule($module);
            $this->installModule();
        }

        // Check we are all installed
        $this->installFiles();
    }

    /**
     * @inheritDoc
     */
    public function installFiles()
    {
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/jquery.min.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-worldclock-render.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/moment.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/moment-timezone.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-layout-scaler.js')->save();
    }

    /**
     * @inheritDoc
     */
    public function layoutDesignerJavaScript()
    {
        return 'worldclock-designer-javascript';
    }

    /**
     * Edit WorldClock
     *
     * @inheritDoc
     */
    public function edit(Request $request, Response $response): Response
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        // You must also provide a duration (all media items must provide this field)
        $this->setOption('name', $sanitizedParams->getString('name'));
        $this->setUseDuration($sanitizedParams->getCheckbox('useDuration'));
        $this->setDuration($sanitizedParams->getInt('duration', ['default' => $this->getDuration()]));
        $this->setOption('enableStat', $sanitizedParams->getString('enableStat'));
        $this->setOption('clockType', $sanitizedParams->getInt('clockType', ['default' => 1]));
        $this->setOption('clockCols', $sanitizedParams->getInt('clockCols', ['default' => 1]));
        $this->setOption('clockRows', $sanitizedParams->getInt('clockRows', ['default' => 1]));

        // Clocks
        $clockTimezones = $sanitizedParams->getArray('clockTimezone');
        $clockHighlight = $sanitizedParams->getArray('clockHighlightValue');
        $clockLabel = $sanitizedParams->getArray('clockLabel');
        $worldClocks = [];

        $i = -1;
        foreach ($clockTimezones as $clockTimezone) {
            $i++;

            if ($clockTimezone == '')
                continue;

            $worldClocks[] = [
                'clockTimezone' => $clockTimezone,
                'clockHighlight' => isset($clockHighlight[$i]) ? $clockHighlight[$i] : false,
                'clockLabel' => isset($clockLabel[$i]) ? $clockLabel[$i] : ''
            ];
        }

        $this->setOption('worldClocks', json_encode($worldClocks));

        if ($this->getOption('clockType') == 1) {
            // Digital clock
            $this->setOption('templateId', $sanitizedParams->getString('templateId'));
            $this->setOption('overrideTemplate', $sanitizedParams->getCheckbox('overrideTemplate'));

            if ($this->getOption('overrideTemplate') == 1) {
                $this->setRawNode('mainTemplate', $request->getParam('mainTemplate', $request->getParam('mainTemplate', null)));
                $this->setRawNode('styleSheet', $request->getParam('styleSheet', $request->getParam('styleSheet', null)));

                $this->setOption('widgetOriginalWidth', $sanitizedParams->getInt('widgetOriginalWidth'));
                $this->setOption('widgetOriginalHeight', $sanitizedParams->getInt('widgetOriginalHeight'));
            }

        } elseif ($this->getOption('clockType') == 2) {
            // Analogue clock
            $this->setOption('bgColor', $sanitizedParams->getString('bgColor'));
            $this->setOption('caseColor', $sanitizedParams->getString('caseColor'));
            $this->setOption('hourHandColor', $sanitizedParams->getString('hourHandColor'));
            $this->setOption('minuteHandColor', $sanitizedParams->getString('minuteHandColor'));

            $this->setOption('showSecondsHand', $sanitizedParams->getCheckbox('showSecondsHand'));
            if ($this->getOption('showSecondsHand') == 1) {
                $this->setOption('secondsHandColor', $sanitizedParams->getString('secondsHandColor'));
            }

            $this->setOption('dialColor', $sanitizedParams->getString('dialColor'));

            $this->setOption('showSteps', $sanitizedParams->getCheckbox('showSteps'));
            if ($this->getOption('showSteps') == 1) {
                $this->setOption('stepsColor', $sanitizedParams->getString('stepsColor'));
                $this->setOption('secondaryStepsColor', $sanitizedParams->getString('secondaryStepsColor'));
            }

            $this->setOption('showDetailed', $sanitizedParams->getCheckbox('showDetailed'));

            $this->setOption('showMiniDigitalClock', $sanitizedParams->getCheckbox('showMiniDigitalClock'));
            if ($this->getOption('showMiniDigitalClock') == 1) {
                $this->setOption('digitalClockTextColor', $sanitizedParams->getString('digitalClockTextColor'));
                $this->setOption('digitalClockBgColor', $sanitizedParams->getString('digitalClockBgColor'));
            }

            $this->setOption('showLabel', $sanitizedParams->getCheckbox('showLabel'));
            if ($this->getOption('showLabel') == 1) {
                $this->setOption('labelTextColor', $sanitizedParams->getString('labelTextColor'));
                $this->setOption('labelBgColor', $sanitizedParams->getString('labelBgColor'));
            }
        }

        $this->isValid();

        // Save the widget
        $this->saveWidget();

        return $response;
    }

    /** @inheritdoc */
    public function getResource($displayId = 0)
    {
        $data = [];
        // Set the null values for template variables.
        $mainTemplate = null;
        $styleSheet = null;
        $widgetOriginalWidth = null;
        $widgetOriginalHeight = null;

        // Replace the View Port Width?
        $data['viewPortWidth'] = $this->isPreview() ? $this->region->width : '[[ViewPortWidth]]';

        // Information from the Module        
        $duration = $this->getCalculatedDurationForGetResource();

        // Get clock array
        $worldClocks = json_decode($this->getOption('worldClocks', '[]'), true);

        // Get number of cols/rows
        $clockCols = intval($this->getOption('clockCols'));
        $clockRows = intval($this->getOption('clockRows'));

        $clockType = intval($this->getOption('clockType'));

        if ($clockType == 1) {
            // Digital clock
            if($this->getOption('overrideTemplate') == 0) {
                $template = $this->getTemplateById($this->getOption('templateId'));
                
                if (isset($template)) {
                    $mainTemplate = $template['mainTemplate'];
                    $styleSheet = $template['css'];
                    $widgetOriginalWidth = $template['widgetOriginalWidth'];
                    $widgetOriginalHeight = $template['widgetOriginalHeight'];
                }
            } else {
                $mainTemplate = $this->getRawNode('mainTemplate');
                $styleSheet = $this->getRawNode('styleSheet', '');
                $widgetOriginalWidth = intval($this->getOption('widgetOriginalWidth'));
                $widgetOriginalHeight = intval($this->getOption('widgetOriginalHeight'));
            }
        } elseif ($clockType == 2) {
            // Analogue clock

            // Analogue clock fixed dimension
            $widgetOriginalWidth = 250;
            $widgetOriginalHeight = 250;

            // Build template and stylesheet
            $mainTemplate = '<div class="analogue-clock">';

            // Mini digital clock
            if ($this->getOption('showMiniDigitalClock') == 1) {
                $mainTemplate .= '<div class="analogue-mini-digital">[HH:mm:ss]</div>';
            }

            // Hands
            $mainTemplate .= '<div class="analogue-clock-hour"></div>
                <div class="analogue-clock-minute"></div>';

            // Seconds hand
            if ($this->getOption('showSecondsHand') == 1) {
                $mainTemplate .= '<div class="analogue-clock-second"></div>';
            }

            $mainTemplate .= '<div class="analogue-center"></div>';

            // Detailed clock overlay
            if ($this->getOption('showDetailed') == 1) {
                $mainTemplate .= '<div class="analogue-overlay"></div>';
            }

            // Dial steps
            if ($this->getOption('showSteps') == 1) {
                $mainTemplate .= '<div class="analogue-steps">';

                for ($i=0; $i < 12; $i++) { 
                    $mainTemplate .= '<div></div>';
                }

                $mainTemplate .= '</div>';
            }
            
            // Close main div
            $mainTemplate .= '</div>';

            // Clock label
            if ($this->getOption('showLabel') == 1) {
                $mainTemplate .= '<div class="analogue-clock-label world-clock-label"></div>';
            }

            // Build stylesheet
            // Main clock CSS
            $styleSheet = '
                .analogue-clock {
                    background: ' . $this->getOption('bgColor') . ';
                    position: relative;
                    text-align: center;
                    box-sizing: border-box;
                    border-radius: 50%;
                    width: 180px;
                    height: 180px;
                    left: 35px;
                    top: 20px;
                }

                .analogue-center {
                    width: 10px;
                    height: 10px;
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    border-radius: 50%;
                    background: ' . $this->getOption('dialColor') . ';
                }

                .analogue-clock-hour {
                    background: ' . $this->getOption('hourHandColor') . ';
                    width: 0;
                    height: 0;
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    margin: -3px 0 -4px -25%;
                    padding: 3px 0 4px 25%;
                    -webkit-transform-origin: 100% 50%;
                    -ms-transform-origin: 100% 50%;
                    transform-origin: 100% 50%;
                    border-radius: 3px;
                }

                .analogue-clock-minute {
                    background: ' . $this->getOption('minuteHandColor') . ';
                    width:0;
                    height:0;
                    position:absolute;
                    top:50%;
                    left:50%;
                    margin:-40% -2px 0;
                    padding:40% 2px 0;
                    -webkit-transform-origin:50% 100%;
                    -ms-transform-origin:50% 100%;
                    transform-origin:50% 100%;
                    border-radius: 2px;
                }

                /* Highlighted */
                .highlighted .analogue-clock-label {
                    font-weight: bold;
                    font-size: 20px;
                }';

                // Show or hide label
                if ($this->getOption('showLabel') == 1) {
                    $styleSheet .= '
                        .analogue-clock-label {
                            background: ' . $this->getOption('labelBgColor') . ';
                            color: ' . $this->getOption('labelTextColor') . ';
                            bottom: -30px;
                            position: relative;
                            font-size: 18px;
                            width: 80%;
                            left: 10%;
                            text-align: center;
                            line-height: 28px;
                        }';
                } else {
                    $styleSheet .= '
                        .analogue-clock {
                            top: 35px;
                        }';
                }

                // Mini digital clock
                if ($this->getOption('showMiniDigitalClock') == 1) {
                    $styleSheet .= '
                        .analogue-mini-digital {
                            background: ' . $this->getOption('digitalClockBgColor') . ';
                            color: ' . $this->getOption('digitalClockTextColor') . ';
                            top: 120px;
                            position: relative;
                            left: 50%;
                            width: 70px;
                            transform: translateX(-50%);
                            line-height: 22px;
                        }';
                }

                // Seconds hand
                if ($this->getOption('showSecondsHand') == 1) {
                    $styleSheet .= '
                        .analogue-clock-second {
                            width:0;
                            height:0;
                            background: ' . $this->getOption('secondsHandColor') . ';
                            position:absolute;
                            top:45%;
                            left:50%;
                            margin:-40% -1px 0;
                            padding:45% 1px 0;
                            -webkit-transform-origin:45% 100%;
                            -ms-transform-origin:45% 100%;
                            transform-origin:45% 100%;
                            border-radius: 1px;
                        }';
                }

                // Detailed version CSS ( shadows and 3D effects )
                if ($this->getOption('showDetailed') == 1) {
                    $styleSheet .= '
                        .analogue-overlay {
                            width: 100%;
                            height: 100%;
                            background: linear-gradient(330deg, rgb(176, 176, 176) 68%, rgb(255, 255, 255) 76%);
                            position: absolute;
                            top: 0;
                            border-radius: 50%;
                            opacity: 0.1;
                        }
                        .analogue-clock {
                            -moz-box-shadow: inset 1px 1px 4px -1px #222, 1px 1px 2px 0px #575757;
                            -webkit-box-shadow: inset 1px 1px 4px -1px #222, 1px 1px 2px 0px #575757;
                            box-shadow: inset 1px 1px 4px -1px #222, 1px 1px 2px 0px #575757;
                        }
                        
                        .analogue-mini-digital {
                            -moz-box-shadow: inset 1px 1px 3px -2px #222;
                            -webkit-box-shadow: inset 1px 1px 3px -2px #222;
                            box-shadow: inset 1px 1px 3px -2px #222;
                        }
                        
                        .analogue-clock-hour,  .analogue-clock-minute, .analogue-clock-second, .analogue-center {
                            -moz-box-shadow: inset 0px 0px 3px -1px #222;
                            -webkit-box-shadow: inset 0px 0px 3px -1px #222;
                            box-shadow: inset 0px 0px 3px -1px #222;
                        }';

                        // If there's label background, show shadows/effects
                        if($this->getOption('labelBgColor') != '') {
                            $styleSheet .= '
                                .analogue-clock-label {
                                    -moz-box-shadow: inset 1px 1px 4px -2px #222, 1px 1px 2px 0px #575757;
                                    -webkit-box-shadow: inset 1px 1px 4px -2px #222, 1px 1px 2px 0px #575757;
                                    box-shadow: inset 1px 1px 4px -2px #222, 1px 1px 2px 0px #575757;
                                }';
                        }
                }

                // Dial steps
                if ($this->getOption('showSteps') == 1) {
                    $styleSheet .= '
                        .analogue-steps > div {
                            background: ' . $this->getOption('secondaryStepsColor') . ';
                            width: 4px;
                            height: 10px;
                            position: absolute;
                            top: 6px;
                            left: 88px;
                            -webkit-transform-origin: 2px 84px;
                            -ms-transform-origin: 2px 84px;
                            transform-origin: 2px 84px;
                        }
                        
                        .analogue-steps > div:nth-child(3n+1) {
                            background: ' . $this->getOption('stepsColor') . ';
                            height: 14px;
                        }';

                    for ($i=0; $i < 12; $i++) { 
                        $styleSheet .= '
                            .analogue-steps > div:nth-child(' . ($i + 1) .') {
                                transform: rotate(' . ($i * 30) . 'deg);
                            }';
                    }
                }

                // If there's a case colour, show the clock border and adjust the steps' positions
                if($this->getOption('caseColor') != '') {
                    $styleSheet .= '
                        .analogue-clock {
                            border: 6px solid ' . $this->getOption('caseColor') . ';
                        }
                        
                        .analogue-steps > div {
                            top: 0px;
                            left: 82px;
                        }';
                }
        }

        // Run through each item and substitute with the template
        $mainTemplate = $this->parseLibraryReferences($this->isPreview(), $mainTemplate);

        // Parse translations
        $mainTemplate = $this->parseTranslations($mainTemplate);

        // Strip out the bit between the [] brackets and use that as the format mask for moment.
        $matches = '';
        preg_match_all('/\[.*?\]/', $mainTemplate, $matches);

        foreach ($matches[0] as $subs) {
            $mainTemplate = str_replace($subs, '<span class="momentClockTag" format="' . str_replace('[', '', str_replace(']', '', $subs)) . '"></span>', $mainTemplate);
        }
        
        $options = array(
            'type' => $this->getModuleType(),
            'clockType' => $clockType,
            'duration' => $duration,
            'originalWidth' => $this->region->width,
            'originalHeight' => $this->region->height,
            'widgetDesignWidth' => $widgetOriginalWidth,
            'widgetDesignHeight'=> $widgetOriginalHeight,
            'worldClocks' => $worldClocks,
            'numCols' => $clockCols,
            'numRows' => $clockRows
        );

        // Replace the head content
        $headContent = '';

        // Add our fonts.css file
        $headContent .= '<link href="' . ($this->isPreview() ? $this->urlFor('library.font.css') : 'fonts.css') . '" rel="stylesheet" media="screen">';
        
        // Add the CSS if it isn't empty, and replace the wallpaper
        if ($styleSheet != '') {
            $headContent .= '<style type="text/css">' . $this->parseLibraryReferences($this->isPreview(), $styleSheet) . '</style>';
        }
        $headContent .= '<style type="text/css">' . file_get_contents($this->getConfig()->uri('css/client.css', true)) . '</style>';

        // Replace the Head Content with our generated javascript
        $data['head'] = $headContent;

        // Add some scripts to the JavaScript Content
        $javaScriptContent = '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery.min.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/moment.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/moment-timezone.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-layout-scaler.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-worldclock-render.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-image-render.js') . '"></script>';

        $javaScriptContent .= '<script type="text/javascript">';
        $javaScriptContent .= '   var options = ' . json_encode($options) . ';';
        $javaScriptContent .= '   var body = ' . json_encode($mainTemplate) . ';';
        $javaScriptContent .= '   moment.locale("' . Translate::GetJsLocale() . '");';
        $javaScriptContent .= '   $(document).ready(function() { ';
        $javaScriptContent .= '       $("body").xiboWorldClockRender(options, body); $("body").xiboLayoutScaler(options); $("#content").find("img").xiboImageRender(options); ';
        $javaScriptContent .= '   }); ';
        $javaScriptContent .= '</script>';

        // Replace the Head Content with our generated javascript
        $data['javaScript'] = $javaScriptContent;

        return $this->renderTemplate($data);
    }

    /** @inheritdoc */
    public function isValid()
    {
        if ($this->getOption('clockRows') == '' || $this->getOption('clockRows') <= 0) {
            throw new InvalidArgumentException(__('Please enter a positive number of rows.'), 'clockRows');
        }

        if ($this->getOption('clockCols') == '' || $this->getOption('clockCols') <= 0) {
            throw new InvalidArgumentException(__('Please enter a positive number of columns.'), 'clockCols');
        }

        if(json_decode($this->getOption('worldClocks', '[]'), true) == []) {
            throw new InvalidArgumentException(__('Please add at least one clock'), 'clockTimezones');
        }
        
        if ($this->getOption('clockType') == 1) {
            // Digital clock
            if ($this->getOption('overrideTemplate') == 0 && ( $this->getOption('templateId') == '' || $this->getOption('templateId') == null)) {
                throw new InvalidArgumentException(__('Please choose a template'), 'templateId');
            }
        }
        
        return self::$STATUS_VALID;
    }

    /**
     * Get available timezones
     * @return mixed
     */
    public function getTimezones()
    {
        // A list of timezones
        $timeZones = [];
        foreach (DateFormatHelper::timezoneList() as $key => $value) {
            $timeZones[] = ['id' => $key, 'value' => $value];
        }

        return $timeZones;
    }

    /**
     * Get the Selected Clocks/Timezones
     * @return mixed
     */
    public function getWorldClocks()
    {
        return json_decode($this->getOption('worldClocks', "[]"), true);
    }

    /** @inheritdoc */
    public function getExtra()
    {
        return [
            'templates' => $this->templatesAvailable(),
            'timezones' => $this->getTimezones(),
            'worldClocks' => $this->getWorldClocks()
        ];
    }

    /** @inheritdoc */
    public function getCacheDuration()
    {
        // We have a long cache interval because we don't depend on any external data.
        return 1; //86400 * 365;
    }

    /** @inheritDoc */
    public function hasTemplates()
    {
        return true;
    }
}
