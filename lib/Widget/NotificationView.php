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
 */


namespace Xibo\Widget;

use Respect\Validation\Validator as v;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Factory\NotificationFactory;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
/**
 * Class NotificationView
 * @package Xibo\Widget
 */
class NotificationView extends ModuleWidget
{
    /**
     * Install Files
     */
    public function installFiles()
    {
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/jquery-1.11.1.min.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-layout-scaler.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-text-render.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/jquery.marquee.min.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/jquery-cycle-2.1.6.min.js')->save();
    }

    /**
     * @return string
     */
    public function layoutDesignerJavaScript()
    {
        return 'notificationview-designer-javascript';
    }

    /**
     * Edit Widget
     *
     * @SWG\Put(
     *  path="/playlist/widget/{widgetId}?notificationView",
     *  operationId="WidgetNotificationEdit",
     *  tags={"widget"},
     *  summary="Edit a Notification Widget",
     *  description="Edit a Notification Widget. This call will replace existing Widget object, all not supplied parameters will be set to default.",
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
     *      name="age",
     *      in="formData",
     *      description="The maximum notification age in minutes - 0 for all",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="noDataMessage",
     *      in="formData",
     *      description="Message to show when no notifications are available",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="noDataMessage_advanced",
     *      in="formData",
     *      description="A flag (0, 1), Should text area by presented as a visual editor?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="effect",
     *      in="formData",
     *      description="Effect that will be used to transitions between items, available options: fade, fadeout, scrollVert, scollHorz, flipVert, flipHorz, shuffle, tileSlide, tileBlind ",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="speed",
     *      in="formData",
     *      description="The transition speed of the selected effect in milliseconds (1000 = normal)",
     *      type="integer",
     *      required=false
     *   ),     *
     *  @SWG\Parameter(
     *      name="durationIsPerItem",
     *      in="formData",
     *      description="A flag (0, 1), The duration specified is per page/item, otherwise the widget duration is divided between the number of pages/items",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="embedStyle",
     *      in="formData",
     *      description="Custom Style Sheets (CSS)",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @param $id
     * @throws InvalidArgumentException
     * @throws \Xibo\Exception\ValueTooLargeException
     */
    public function edit(Request $request, Response $response, $id)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $this->setDuration($sanitizedParams->getInt('duration', ['default' => $this->getDuration()]));
        $this->setUseDuration($sanitizedParams->getCheckbox('useDuration'));
        $this->setOption('name', $sanitizedParams->getString('name'));
        $this->setOption('age', $sanitizedParams->getInt('age'));
        $this->setOption('effect', $sanitizedParams->getString('effect'));
        $this->setOption('speed', $sanitizedParams->getInt('speed'));
        $this->setOption('durationIsPerItem', $sanitizedParams->getCheckbox('durationIsPerItem'));
        $this->setOption('enableStat', $sanitizedParams->getString('enableStat'));
        $this->setOption('updateInterval', $sanitizedParams->getInt('updateInterval', ['default' => 60]));
        $this->setRawNode('noDataMessage', $request->getParam('noDataMessage', null));
        $this->setOption('noDataMessage_advanced', $sanitizedParams->getCheckbox('noDataMessage_advanced'));
        $this->setRawNode('template', $request->getParam('template', null));
        $this->setRawNode('template_advanced', $request->getParam('template_advanced', null));
        $this->setRawNode('embedStyle', $request->getParam('embedStyle', null));

        $this->saveWidget();
    }

    /** @inheritdoc */
    public function isValid()
    {
        if ($this->getUseDuration() == 1 && !v::intType()->min(1)->validate($this->getDuration())) {
            throw new InvalidArgumentException(__('You must enter a duration.'), 'duration');
        }

        // Can't be sure because the client does the rendering
        return self::$STATUS_PLAYER;
    }

    /**
     * @return NotificationFactory
     */
    private function getNotificationFactory()
    {
        return $this->container->get('notificationFactory');
    }

    /**
     * @param $isPreview
     * @param $displayId
     * @param Request $request
     * @return array
     */
    private function getNotifications($isPreview, $displayId = null, Request $request)
    {
        // Date format
        $dateFormat = $this->getOption('dateFormat', $this->getConfig()->getSetting('DATE_FORMAT'));
        $age = $this->getOption('age', 0);

        // Parse the text template
        $template = $this->getRawNode('template', '');
        $matches = '';
        preg_match_all('/\[.*?\]/', $template, $matches);

        $items = [];

        if ($isPreview)
            $notifications = $this->getNotificationFactory()->query(['releaseDt DESC', 'createDt DESC', 'subject'], [
                'releaseDt' => ($age === 0) ? null : $this->getDate()->parse()->subMinutes($age)->format('U'),
                'userId' => $this->getUser($request)->userId
            ], $request);
        else
            $notifications = $this->getNotificationFactory()->query(['releaseDt DESC', 'createDt DESC', 'subject'], [
                'releaseDt' => ($age === 0) ? null : $this->getDate()->parse()->subMinutes($age)->format('U'),
                'displayId' => $displayId
            ], $request);

        $this->getLog()->debug('There are ' . count($notifications) . ' to render.');

        foreach ($notifications as $notification) {
            $rowString = $template;

            // Run through all [] substitutes in $matches
            foreach ($matches[0] as $sub) {
                $replace = '';

                // Use the pool of standard tags
                switch ($sub) {
                    case '[Name]':
                        $replace = $this->getOption('name');
                        break;

                    case '[Subject]':
                        $replace = $notification->subject;
                        break;

                    case '[Body]':
                        $replace = strip_tags($notification->body);
                        break;

                    case '[Date]':
                        $replace = $this->getDate()->getLocalDate($notification->releaseDt, $dateFormat);
                        break;
                }

                // Substitute the replacement we have found (it might be '')
                $rowString = str_replace($sub, $replace, $rowString);

            }

            $items[] = $rowString;
        }

        if (count($items) <= 0) {
            $items[] = $this->getRawNode('noDataMessage', null);
        }

        return $items;
    }

    /** @inheritdoc */
    public function getResource(Request $request, Response $response)
    {
        // Behave exactly like the client.
        $data = [];
        $isPreview = ($this->getSanitizer($request->getParams())->getCheckbox('preview') == 1);
        $displayId = $request->getAttribute('displayId', 0);

        // Replace the View Port Width?
        $data['viewPortWidth'] = ($isPreview) ? $this->region->width : '[[ViewPortWidth]]';

        // Items
        $items = $this->getNotifications($isPreview, $displayId, $request);

        // Include some vendor items
        $javaScriptContent  = '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery-1.11.1.min.js', null, $request) . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-layout-scaler.js', null, $request) . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-text-render.js', null, $request) . '"></script>';// Need the marquee plugin?

        $effect = $this->getOption('effect');
        if (stripos($effect, 'marquee') !== false)
            $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery.marquee.min.js', null, $request) . '"></script>';

        // Need the cycle plugin?
        if ($effect != 'none')
            $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery-cycle-2.1.6.min.js', null, $request) . '"></script>';

        // Get the Style Sheet
        $styleSheetContent = $this->parseLibraryReferences($isPreview, $this->getRawNode('embedStyle', null), $request);

        // Set some options
        $options = array(
            'type' => $this->getModuleType(),
            'fx' => $effect,
            'duration' => $this->getDuration(),
            'durationIsPerItem' => false,
            'numItems' => 0,
            'takeItemsFrom' => 'start',
            'itemsPerPage' => 0,
            'speed' => $this->getOption('speed', 0),
            'originalWidth' => $this->region->width,
            'originalHeight' => $this->region->height,
            'marqueeInlineSelector' => $this->getOption('marqueeInlineSelector', '.item, .item p')
        );

        // Add an options variable with some useful information for scaling
        $javaScriptContent .= '<script type="text/javascript">';
        $javaScriptContent .= '   var options = ' . json_encode($options) . ';';
        $javaScriptContent .= '   var items = ' . json_encode($items) . ';';
        $javaScriptContent .= '   $(document).ready(function() { $("body").xiboLayoutScaler(options); $("#content").xiboTextRender(options, items); });';
        $javaScriptContent .= '</script>';

        // Add our fonts.css file
        $headContent = '<link href="' . (($isPreview) ? $this->urlFor($request,'library.font.css') : 'fonts.css') . '" rel="stylesheet" media="screen">';
        $headContent .= '<style type="text/css">' . file_get_contents($this->getConfig()->uri('css/client.css', true)) . '</style>';

        $data['head'] = $headContent;

        // Replace the Style Sheet Content with our generated Style Sheet
        $data['styleSheet'] = $styleSheetContent;

        // Replace the Head Content with our generated java script
        $data['javaScript'] = $javaScriptContent;

        return $this->renderTemplate($data, 'get-resource', $response);
    }

    /** @inheritdoc */
    public function getModifiedDate($displayId)
    {
        $widgetModifiedDt = $this->getDate()->parse($this->widget->modifiedDt, 'U');
        $age = $this->getOption('age', 0);

        // Get the date/time of the last notification drawn by this Widget
        $notifications = $this->getNotificationFactory()->query(['releaseDt DESC', 'createDt DESC'], [
            'releaseDt' => ($age === 0) ? null : $this->getDate()->parse()->subMinutes($age)->format('U'),
            'displayId' => $displayId,
            'length' => 1
        ]);

        // Get the release date from the notification returned
        $widgetModifiedDt = (count($notifications) > 0) ? $this->getDate()->parse($notifications[0]->releaseDt, 'U') : $widgetModifiedDt;

        return $widgetModifiedDt;
    }

    /** @inheritdoc */
    public function getCacheKey($displayId)
    {
        return $this->getWidgetId() . '_' . $displayId;
    }

    /** @inheritdoc */
    public function isCacheDisplaySpecific()
    {
        return true;
    }

    /** @inheritdoc */
    public function getCacheDuration()
    {
        // We have a long cache interval because we don't depend on any external data.
        // the modified timestamp expires us, unless we have an "age" parameter
        return $this->getOption('age', 1440 * 365) * 60;
    }
}