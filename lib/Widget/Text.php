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

use Xibo\Factory\MediaFactory;
use Xibo\Helper\Sanitize;
use Xibo\Helper\Theme;

class Text extends ModuleWidget
{
    /**
     * Install Files
     */
    public function installFiles()
    {
        MediaFactory::createModuleSystemFile('modules/vendor/jquery-1.11.1.min.js')->save();
        MediaFactory::createModuleSystemFile('modules/vendor/moment.js')->save();
        MediaFactory::createModuleSystemFile('modules/vendor/jquery.marquee.min.js')->save();
        MediaFactory::createModuleSystemFile('modules/xibo-layout-scaler.js')->save();
        MediaFactory::createModuleSystemFile('modules/xibo-text-render.js')->save();
    }

    public function validate()
    {
        // Validation
        if ($this->getOption('text') == '')
            throw new \InvalidArgumentException(__('Please enter some text'));

        if ($this->getDuration() == 0)
            throw new \InvalidArgumentException(__('You must enter a duration.'));
    }

    /**
     * Add Media
     */
    public function add()
    {
        $this->setDuration(Sanitize::getInt('duration', $this->getDuration()));
        $this->setOption('xmds', true);
        $this->setOption('effect', Sanitize::getString('effect'));
        $this->setOption('speed', Sanitize::getInt('speed'));
        $this->setOption('backgroundColor', Sanitize::getString('backgroundColor'));
        $this->setOption('name', Sanitize::getString('name'));
        $this->setOption('marqueeInlineSelector', Sanitize::getString('marqueeInlineSelector'));
        $this->setRawNode('text', Sanitize::getParam('ta_text', null));

        // Save the widget
        $this->validate();
        $this->saveWidget();
    }

    /**
     * Edit Media
     */
    public function edit()
    {
        $this->setDuration(Sanitize::getInt('duration', $this->getDuration()));
        $this->setOption('xmds', true);
        $this->setOption('effect', Sanitize::getString('effect'));
        $this->setOption('speed', Sanitize::getInt('speed'));
        $this->setOption('backgroundColor', Sanitize::getString('backgroundColor'));
        $this->setOption('name', Sanitize::getString('name'));
        $this->setOption('marqueeInlineSelector', Sanitize::getString('marqueeInlineSelector'));
        $this->setRawNode('text', Sanitize::getParam('ta_text', null));

        // Save the widget
        $this->validate();
        $this->saveWidget();
    }

    /**
     * Get Resource
     * @param int $displayId
     * @return mixed
     */
    public function GetResource($displayId = 0)
    {
        $data = [];
        $isPreview = (Sanitize::getCheckbox('preview') == 1);

        // Clear all linked media.
        $this->clearMedia();

        // Replace the View Port Width?
        $data['viewPortWidth'] = ($isPreview) ? $this->region->width : '[[ViewPortWidth]]';

        $duration = $this->getDuration();

        $text = $this->parseLibraryReferences($isPreview, $this->getRawNode('text', null));

        // Handle older layouts that have a direction node but no effect node
        $oldDirection = $this->GetOption('direction', 'none');

        if ($oldDirection != 'none')
            $oldDirection = 'marquee' . ucfirst($oldDirection);

        $effect = $this->GetOption('effect', $oldDirection);

        // Set some options
        $options = array(
            'type' => $this->getModuleType(),
            'fx' => $effect,
            'duration' => $duration,
            'durationIsPerItem' => false,
            'numItems' => 1,
            'takeItemsFrom' => 'start',
            'itemsPerPage' => 0,
            'speed' => $this->GetOption('speed', 0),
            'originalWidth' => $this->region->width,
            'originalHeight' => $this->region->height,
            'previewWidth' => Sanitize::getDouble('width', 0),
            'previewHeight' => Sanitize::getDouble('height', 0),
            'scaleOverride' => Sanitize::getDouble('scale_override', 0),
            'marqueeInlineSelector' => $this->GetOption('marqueeInlineSelector', '.item, .item p')
        );

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

        if ($clock) {
            // Strip out the bit between the [] brackets and use that as the format mask for moment.
            $matches = '';
            preg_match_all('/\[.*?\]/', $text, $matches);

            foreach ($matches[0] as $subs) {
                $text = str_replace($subs, '<span class="clock" format="' . str_replace('[', '', str_replace(']', '', $subs)) . '"></span>', $text);
            }
        }

        // Generate a JSON string of substituted items.
        $items[] = $text;

        // Replace the head content
        $javaScriptContent = '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery-1.11.1.min.js') . '"></script>';

        // Need the marquee plugin?
        if (stripos($effect, 'marquee') !== false)
            $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery.marquee.min.js') . '"></script>';

        // Need the cycle plugin?
        if ($effect != 'none')
            $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery-cycle-2.1.6.min.js') . '"></script>';

        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-layout-scaler.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-text-render.js') . '"></script>';

        // Do we need to include moment?
        if ($clock)
            $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/moment.js') . '"></script>';

        $javaScriptContent .= '<script type="text/javascript">';
        $javaScriptContent .= '   var options = ' . json_encode($options) . ';';
        $javaScriptContent .= '   var items = ' . json_encode($items) . ';';
        $javaScriptContent .= '   $(document).ready(function() { ';
        $javaScriptContent .= '       $("#content").xiboTextRender(options, items); $("body").xiboLayoutScaler(options);';

        if ($clock)
            $javaScriptContent .= ' updateClock(); setInterval(updateClock, 1000); ';

        $javaScriptContent .= '   }); ';

        if ($clock) {
            $javaScriptContent .= '
                function updateClock() {
                    $(".clock").each(function() {
                        $(this).html(moment().format($(this).attr("format")));
                    });
                }
            ';
        }

        $javaScriptContent .= '</script>';

        // Replace the Head Content with our generated javascript
        $data['javaScript'] = $javaScriptContent;

        // Add our fonts.css file
        $headContent = '<link href="' . $this->getResourceUrl('fonts.css') . ' rel="stylesheet" media="screen">';
        $headContent .= '<style type="text/css">' . file_get_contents(Theme::uri('css/client.css', true)) . '</style>';

        $data['head'] = $headContent;

        // Update and save widget if we've changed our assignments.
        if ($this->hasMediaChanged())
            $this->widget->save(['saveWidgetOptions' => false]);

        return $this->renderTemplate($data);
    }

    public function hoverPreview()
    {
        // Default Hover window contains a thumbnail, media type and duration
        $output = parent::hoverPreview();

        $output .= '<div class="hoverPreview">';
        $output .= '    ' . $this->getRawNode('text', null);;
        $output .= '</div>';

        return $output;
    }

    public function isValid()
    {
        // Text rendering will be valid
        return 1;
    }
}
