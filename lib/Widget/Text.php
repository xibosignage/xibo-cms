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

use Xibo\Exception\InvalidArgumentException;
use Xibo\Helper\Translate;

/**
 * Class Text
 * @package Xibo\Widget
 */
class Text extends ModuleWidget
{
    /**
     * Install Files
     */
    public function installFiles()
    {
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/jquery-1.11.1.min.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/moment.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/jquery.marquee.min.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/jquery-cycle-2.1.6.min.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-layout-scaler.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-text-render.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-image-render.js')->save();
    }

    /**
     * Javascript functions for the layout designer
     */
    public function layoutDesignerJavaScript()
    {
        return 'text-designer-javascript';
    }

    /**
     * Edit Text Widget
     *
     * @SWG\Put(
     *  path="/playlist/widget/{widgetId}?text",
     *  operationId="WidgetTextEdit",
     *  tags={"widget"},
     *  summary="Edit a Text Widget",
     *  description="Edit a new Text Widget. This call will replace existing Widget object, all not supplied parameters will be set to default.",
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
     *      name="effect",
     *      in="formData",
     *      description="Effect that will be used to transitions between items, available options: fade, fadeout, scrollVert, scollHorz, flipVert, flipHorz, shuffle, tileSlide, tileBlind, marqueeUp, marqueeDown, marqueeRight, marqueeLeft",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="speed",
     *      in="formData",
     *      description="The transition speed of the selected effect in milliseconds (1000 = normal) or the Marquee speed in a low to high scale (normal = 1)",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="backgroundcolor",
     *      in="formData",
     *      description="A HEX color to use as the background color of this widget",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="marqueeInlineSelector",
     *      in="formData",
     *      description="The selector to use for stacking marquee items in a line when scrolling left/right",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="text",
     *      in="formData",
     *      description="Enter the text to display",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="ta_text_advanced",
     *      in="formData",
     *      description="A flag (0, 1), Should text area by presented as a visual editor?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="javaScript",
     *      in="formData",
     *      description="Optional JavaScript",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     * @throws InvalidArgumentException
     */
    public function edit()
    {
        $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setOption('enableStat', $this->getSanitizer()->getString('enableStat'));
        $this->setOption('xmds', true);
        $this->setOption('effect', $this->getSanitizer()->getString('effect'));
        $this->setOption('speed', $this->getSanitizer()->getInt('speed'));
        $this->setOption('backgroundColor', $this->getSanitizer()->getString('backgroundColor'));
        $this->setOption('name', $this->getSanitizer()->getString('name'));
        $this->setOption('marqueeInlineSelector', $this->getSanitizer()->getString('marqueeInlineSelector'));
        $this->setRawNode('text', $this->getSanitizer()->getParam('ta_text', $this->getSanitizer()->getParam('text', null)));
        $this->setOption('ta_text_advanced', $this->getSanitizer()->getCheckbox('ta_text_advanced'));
        $this->setRawNode('javaScript', $this->getSanitizer()->getParam('javaScript', ''));

        // Save the widget
        $this->isValid();
        $this->saveWidget();
    }

    /** @inheritdoc */
    public function getResource($displayId = 0)
    {
        // Start building the template
        $this
            ->initialiseGetResource()
            ->appendViewPortWidth($this->region->width)
            ->appendJavaScriptFile('vendor/jquery-1.11.1.min.js')
            ->appendJavaScriptFile('xibo-layout-scaler.js')
            ->appendJavaScriptFile('xibo-text-render.js')
            ->appendJavaScriptFile('xibo-image-render.js')
            ->appendFontCss()
            ->appendCss(file_get_contents($this->getConfig()->uri('css/client.css', true)))
            ->appendJavaScript($this->parseLibraryReferences($this->isPreview(), $this->getRawNode('javaScript', '')))
        ;

        // Handle older layouts that have a direction node but no effect node
        $oldDirection = $this->getOption('direction', 'none');

        if ($oldDirection != 'none')
            $oldDirection = 'marquee' . ucfirst($oldDirection);

        $effect = $this->getOption('effect', $oldDirection);

        // Set some options
        $this->appendOptions([
            'type' => $this->getModuleType(),
            'fx' => $effect,
            'duration' => $this->getCalculatedDurationForGetResource(),
            'durationIsPerItem' => false,
            'numItems' => 1,
            'takeItemsFrom' => 'start',
            'itemsPerPage' => 0,
            'speed' => $this->getOption('speed', 0),
            'originalWidth' => $this->region->width,
            'originalHeight' => $this->region->height,
            'marqueeInlineSelector' => $this->getOption('marqueeInlineSelector', '.item, .item p')
        ]);

        // Pull out our text
        $text = $this->parseLibraryReferences($this->isPreview(), $this->getRawNode('text', null));

        // See if we need to replace out any [clock] or [date] tags
        $clock = false;

        if (stripos($text, '[Clock]')) {
            $clock = true;
            $text = str_replace('[Clock]', '[HH:mm]', $text);
        }

        if (stripos($text, '[Clock|')) {
            $clock = true;
            $text = str_replace('[Clock|', '[', $text);
        }

        if (stripos($text, '[Date]')) {
            $clock = true;
            $text = str_replace('[Date]', '[DD/MM/YYYY]', $text);
        }

        if (stripos($text, '[Date|')) {
            $clock = true;
            $text = str_replace('[Date|', '[', $text);
        }

        if ($clock) {
            // Strip out the bit between the [] brackets and use that as the format mask for moment.
            $matches = '';
            preg_match_all('/\[.*?\]/', $text, $matches);

            foreach ($matches[0] as $subs) {
                $text = str_replace($subs, '<span class="clock" format="' . str_replace('[', '', str_replace(']', '', $subs)) . '"></span>', $text);
            }
        }

        // The xibo-text-render library will take these items and render them appropriately depending on the options provided
        $this->appendItems([$text]);

        // Replace the head content

        // Need the marquee plugin?
        if (stripos($effect, 'marquee') !== false)
            $this->appendJavaScriptFile('vendor/jquery.marquee.min.js');

        // Need the cycle plugin?
        if ($effect != 'none')
            $this->appendJavaScriptFile('vendor/jquery-cycle-2.1.6.min.js');

        // Do we need to include moment?
        if ($clock)
            $this->appendJavaScriptFile('vendor/moment.js');

        // Finalise some JavaScript to run.
        $javaScriptContent = '$(document).ready(function() { ';
        $javaScriptContent .= '       $("#content").xiboTextRender(options, items); $("body").xiboLayoutScaler(options); $("#content").find("img").xiboImageRender(options); ';

        if ($clock)
            $javaScriptContent .= ' moment.locale("' . Translate::GetJsLocale() . '"); updateClock(); setInterval(updateClock, 1000); ';

        $javaScriptContent .= '}); ';

        if ($clock) {
            $javaScriptContent .= '
                function updateClock() {
                    $(".clock").each(function() {
                        $(this).html(moment().format($(this).attr("format")));
                    });
                }
            ';
        }

        $this->appendJavaScript($javaScriptContent);

        // Fill in a background color?
        if ($this->getOption('backgroundColor') != '') {
            $this->appendCss('body { background-color: ' . $this->getOption('backgroundColor') . '; }');
        }

        return $this->finaliseGetResource();
    }

    /** @inheritdoc */
    public function isValid()
    {
        // Validation
        if ($this->getOption('text') == '')
            throw new InvalidArgumentException(__('Please enter some text'), 'text');

        if ($this->getUseDuration() == 1 && $this->getDuration() == 0)
            throw new InvalidArgumentException(__('You must enter a duration.'), 'duration');

        return self::$STATUS_VALID;
    }

    /** @inheritdoc */
    public function getCacheDuration()
    {
        // We have a long cache interval because we don't depend on any external data.
        return 86400 * 365;
    }
}
