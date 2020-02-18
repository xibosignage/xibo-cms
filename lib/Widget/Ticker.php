<?php
/**
 * Copyright (C) 2012-2018 Xibo Signage Ltd
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

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use PicoFeed\Config\Config;
use PicoFeed\Logging\Logger;
use PicoFeed\Parser\Item;
use PicoFeed\PicoFeedException;
use PicoFeed\Reader\Reader;
use Respect\Validation\Validator as v;
use Stash\Invalidation;
use Xibo\Controller\Library;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Helper\Environment;

/**
 * Class Ticker
 * @package Xibo\Widget
 */
class Ticker extends ModuleWidget
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

    /** @inheritdoc */
    public function layoutDesignerJavaScript()
    {
        // We use the same javascript as the data set view designer
        return 'ticker-designer-javascript';
    }

    /**
     * Form for updating the module settings
     */
    public function settingsForm()
    {
        return 'ticker-form-settings';
    }

    /**
     * Process any module settings
     * @throws InvalidArgumentException
     */
    public function settings()
    {
        $updateIntervalImages = $this->getSanitizer()->getInt('updateIntervalImages', 240);

        if ($this->module->enabled != 0) {
            if ($updateIntervalImages < 0)
                throw new InvalidArgumentException(__('Update Interval Images must be greater than or equal to 0'), 'updateIntervalImages');
        }

        $this->module->settings['updateIntervalImages'] = $updateIntervalImages;

        return $this->module->settings;
    }

    /**
     * Get Extra content for the form
     * @return array
     */
    public function getExtra()
    {
        return [
            'templates' => $this->templatesAvailable(),
        ];
    }

    /**
     * Edit Ticker
     *
     * @SWG\Put(
     *  path="/playlist/widget/{widgetId}?ticker",
     *  operationId="WidgetTickerEdit",
     *  tags={"widget"},
     *  summary="Edit a ticker Widget",
     *  description="Edit a ticker Widget. This call will replace existing Widget object, all not supplied parameters will be set to default.",
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
     *      name="uri",
     *      in="formData",
     *      description="The link for the rss feed",
     *      type="string",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="updateInterval",
     *      in="formData",
     *      description="Update interval in minutes",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="updateIntervalImages",
     *      in="formData",
     *      description="Update interval for downloaded Images, in minutes",
     *      type="integer",
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
     *      name="copyright",
     *      in="formData",
     *      description="Copyright information to display as the last item in this feed. can be styled with the #copyright CSS selector",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="numItems",
     *      in="formData",
     *      description="The number of RSS items you want to display",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="takeItemsFrom",
     *      in="formData",
     *      description="Take the items form the beginning or the end of the list, available options: start, end",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="reverseOrder",
     *      in="formData",
     *      description="A flag (0, 1), Should we reverse the order of the feed items as well?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="durationIsPerItem",
     *      in="formData",
     *      description="A flag (0, 1), The duration specified is per item, otherwise it is per feed",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="itemsSideBySide",
     *      in="formData",
     *      description="A flag (0, 1), Should items be shown side by side",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="itemsPerPage",
     *      in="formData",
     *      description="When in single mode, how many items per page should be shown",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="dateFormat",
     *      in="formData",
     *      description="The date format to apply to all dates returned by the ticker",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="allowedAttributes",
     *      in="formData",
     *      description="A comma separated list of attributes that should not be stripped from the feed",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="stripTags",
     *      in="formData",
     *      description="A comma separated list of attributes that should be stripped from the feed",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="decodeHtml",
     *      in="formData",
     *      description="Should we decode the HTML entities in this feed before parsing it?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="backgroundColor",
     *      in="formData",
     *      description="A HEX color to use as the background color of this widget",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="disableDateSort",
     *      in="formData",
     *      description="Should the date sort applied to the feed be disabled?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="textDirection",
     *      in="formData",
     *      description="Which direction does the text in the feed use? Available options: ltr, rtl",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="noDataMessage",
     *      in="formData",
     *      description="A message to display when no data is returned from the source",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="templateId",
     *      in="formData",
     *      description="Template you'd like to apply, options available: title-only, prominent-title-with-desc-and-name-separator, media-rss-with-title, media-rss-wth-left-hand-text, media-rss-image-only",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="overrideTemplate",
     *      in="formData",
     *      description="Flag (0, 1) override template checkbox",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="css",
     *      in="formData",
     *      description="Optional StyleSheet Pass only with overrideTemplate set to 1 ",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="javaScript",
     *      in="formData",
     *      description="Optional JavaScript, Pass only with overrideTemplate set to 1 ",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="randomiseItems",
     *      in="formData",
     *      description="A flag (0, 1), whether to randomise the feed",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     * @inheritdoc
     */
    public function edit()
    {
        // Other properties
        $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setOption('enableStat', $this->getSanitizer()->getString('enableStat'));
        $this->setOption('xmds', true);
        $this->setOption('uri', urlencode($this->getSanitizer()->getString('uri')));
        $this->setOption('updateInterval', $this->getSanitizer()->getInt('updateInterval', 120));

        if ($this->getSanitizer()->getInt('updateIntervalImages') !== null) {
            $this->setOption('updateIntervalImages', $this->getSanitizer()->getInt('updateIntervalImages'));
        }

        $this->setOption('speed', $this->getSanitizer()->getInt('speed', 2));
        $this->setOption('name', $this->getSanitizer()->getString('name'));
        $this->setOption('effect', $this->getSanitizer()->getString('effect'));
        $this->setOption('copyright', $this->getSanitizer()->getString('copyright'));
        $this->setOption('numItems', $this->getSanitizer()->getInt('numItems'));
        $this->setOption('takeItemsFrom', $this->getSanitizer()->getString('takeItemsFrom'));
        $this->setOption('reverseOrder', $this->getSanitizer()->getCheckbox('reverseOrder'));
        $this->setOption('durationIsPerItem', $this->getSanitizer()->getCheckbox('durationIsPerItem'));
        $this->setOption('randomiseItems', $this->getSanitizer()->getCheckbox('randomiseItems'));
        $this->setOption('itemsSideBySide', $this->getSanitizer()->getCheckbox('itemsSideBySide'));
        $this->setOption('itemsPerPage', $this->getSanitizer()->getInt('itemsPerPage'));
        $this->setOption('dateFormat', $this->getSanitizer()->getString('dateFormat'));
        $this->setOption('allowedAttributes', $this->getSanitizer()->getString('allowedAttributes'));
        $this->setOption('stripTags', $this->getSanitizer()->getString('stripTags'));
        $this->setOption('decodeHtml', $this->getSanitizer()->getCheckbox('decodeHtml'));
        $this->setOption('backgroundColor', $this->getSanitizer()->getString('backgroundColor'));
        $this->setOption('disableDateSort', $this->getSanitizer()->getCheckbox('disableDateSort'));
        $this->setOption('textDirection', $this->getSanitizer()->getString('textDirection'));
        $this->setOption('overrideTemplate', $this->getSanitizer()->getCheckbox('overrideTemplate'));
        $this->setOption('templateId', $this->getSanitizer()->getString('templateId'));
        $this->setRawNode('noDataMessage', $this->getSanitizer()->getParam('noDataMessage', ''));
        $this->setOption('noDataMessage_advanced', $this->getSanitizer()->getCheckbox('noDataMessage_advanced'));
        $this->setRawNode('javaScript', $this->getSanitizer()->getParam('javaScript', ''));

        if ($this->getOption('overrideTemplate') == 1) {
            // Feed tickers should only use the template if they have override set.
            $this->setRawNode('template', $this->getSanitizer()->getParam('ta_text', $this->getSanitizer()->getParam('template', null)));
            $this->setOption('ta_text_advanced', $this->getSanitizer()->getCheckbox('ta_text_advanced'));
            $this->setRawNode('css', $this->getSanitizer()->getParam('ta_css', $this->getSanitizer()->getParam('css', null)));
        }
        
        // Save the widget
        $this->isValid();
        $this->saveWidget();
    }

    /** @inheritdoc */
    public function getResource($displayId = 0)
    {
        // Load in the template
        $data = [];
        $isPreview = ($this->getSanitizer()->getCheckbox('preview') == 1);

        // Replace the View Port Width?
        $data['viewPortWidth'] = ($isPreview) ? $this->region->width : '[[ViewPortWidth]]';

        // Information from the Module
        $itemsSideBySide = $this->getOption('itemsSideBySide', 0);
        $duration = $this->getCalculatedDurationForGetResource();
        $durationIsPerItem = $this->getOption('durationIsPerItem', 1);
        $numItems = $this->getOption('numItems', 0);
        $takeItemsFrom = $this->getOption('takeItemsFrom', 'start');
        $reverseOrder = $this->getOption('reverseOrder', 0);
        $itemsPerPage = $this->getOption('itemsPerPage', 0);

        // Text/CSS subsitution variables.
        $text = null;
        $css = null;

        // Get CSS and HTML template from the original template or from the input field
        if ($this->getOption('overrideTemplate') == 0) {
            // Feed tickers without override set.
            $template = $this->getTemplateById($this->getOption('templateId', 'title-only'));
            
            if (isset($template)) {
                $text = $template['template'];
                $css = $template['css'];
            } else {
                $text = $this->getRawNode('template', '');
                $css = $this->getRawNode('css', '');
            }
        } else {
            // DataSet tickers or feed tickers without overrides.
            $text = $this->getRawNode('template', '');
            $css = $this->getRawNode('css', '');
        }
        
        // Parse library references on the template
        $text = $this->parseLibraryReferences($isPreview, $text);
        
        // Parse translations
        $text = $this->parseTranslations($text);

        // Parse library references on the CSS Node
        $css = $this->parseLibraryReferences($isPreview, $css);

        // Get the JavaScript node
        $javaScript = $this->parseLibraryReferences($isPreview, $this->getRawNode('javaScript', ''));

        // Handle older layouts that have a direction node but no effect node
        $oldDirection = $this->getOption('direction', 'none');

        if ($oldDirection == 'single')
            $oldDirection = 'noTransition';
        else if ($oldDirection != 'none')
            $oldDirection = 'marquee' . ucfirst($oldDirection);

        $effect = $this->getOption('effect', $oldDirection);

        $options = array(
            'type' => $this->getModuleType(),
            'fx' => $effect,
            'duration' => $duration,
            'durationIsPerItem' => (($durationIsPerItem == 0) ? false : true),
            'numItems' => $numItems,
            'takeItemsFrom' => $takeItemsFrom,
            'reverseOrder' => $reverseOrder,
            'itemsPerPage' => $itemsPerPage,
            'randomiseItems' => $this->getOption('randomiseItems', 0),
            'speed' => $this->getOption('speed', 1000),
            'originalWidth' => $this->region->width,
            'originalHeight' => $this->region->height
        );

        // Generate a JSON string of substituted items.
        $items = $this->getRssItems($text);

        // Return empty string if there are no items to show.
        if (count($items) == 0) {
            // Do we have a no-data message to display?
            $noDataMessage = $this->getRawNode('noDataMessage');

            if ($noDataMessage != '') {
                $items[] = $noDataMessage;
            } else {
                $this->getLog()->info('Request failed for dataSet id=%d. Widget=%d. Due to No Records Found', $this->getOption('dataSetId'), $this->getWidgetId());
                return '';
            }
        }

        // Work out how many pages we will be showing.
        $pages = $numItems;
        if ($numItems > count($items) || $numItems == 0)
            $pages = count($items);

        $pages = ($itemsPerPage > 0) ? ceil($pages / $itemsPerPage) : $pages;
        $totalDuration = ($durationIsPerItem == 0) ? $duration : ($duration * $pages);

        // Replace and Control Meta options
        $data['controlMeta'] = '<!-- NUMITEMS=' . $pages . ' -->' . PHP_EOL . '<!-- DURATION=' . $totalDuration . ' -->';   
        // Replace the head content
        $headContent = '';
        
        if ($itemsSideBySide == 1) {
            $headContent .= '<style type="text/css">';
            $headContent .= ' .item, .page { float: left; }';
            $headContent .= '</style>';
        }

        if ($this->getOption('textDirection') == 'rtl') {
            $headContent .= '<style type="text/css">';
            $headContent .= ' #content { direction: rtl; }';
            $headContent .= '</style>';
        }

        if ($this->getOption('backgroundColor') != '') {
            $headContent .= '<style type="text/css">';
            $headContent .= ' body { background-color: ' . $this->getOption('backgroundColor') . '; }';
            $headContent .= '</style>';
        }

        // Add the CSS if it isn't empty
        if ($css != '') {
            $headContent .= '<style type="text/css">' . $css . '</style>';
        }

        // Add our fonts.css file
        $headContent .= '<link href="' . (($isPreview) ? $this->getApp()->urlFor('library.font.css') : 'fonts.css') . '" rel="stylesheet" media="screen">';
        $headContent .= '<style type="text/css">' . file_get_contents($this->getConfig()->uri('css/client.css', true)) . '</style>';

        // Replace the Head Content with our generated javascript
        $data['head'] = $headContent;

        // Add some scripts to the JavaScript Content
        $javaScriptContent = '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery-1.11.1.min.js') . '"></script>';

        // Need the marquee plugin?
        if (stripos($effect, 'marquee') !== false)
            $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery.marquee.min.js') . '"></script>';

        // Need the cycle plugin?
        if ($effect != 'none')
            $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery-cycle-2.1.6.min.js') . '"></script>';

        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-layout-scaler.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-text-render.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-image-render.js') . '"></script>';

        $javaScriptContent .= '<script type="text/javascript">';
        $javaScriptContent .= '   var options = ' . json_encode($options) . ';';
        $javaScriptContent .= '   var items = ' . json_encode($items) . ';';
        $javaScriptContent .= '   $(document).ready(function() { ';
        $javaScriptContent .= '       $("body").xiboLayoutScaler(options); if(items != false) { $("#content").xiboTextRender(options, items); } $("#content").find("img").xiboImageRender(options); ';
        $javaScriptContent .= '   }); ';
        $javaScriptContent .= $javaScript;
        $javaScriptContent .= '</script>';

        // Replace the Head Content with our generated javascript
        $data['javaScript'] = $javaScriptContent;

        return $this->renderTemplate($data);
    }

    /**
     * @param $text
     * @return array|mixed|null
     * @throws \Xibo\Exception\ConfigurationException
     */
    private function getRssItems($text)
    {
        $items = [];

        // Make sure we have the cache location configured
        Library::ensureLibraryExists($this->getConfig()->getSetting('LIBRARY_LOCATION'));

        // Create a key to use as a caching key for this item.
        // the rendered feed will be cached, so it is important the key covers all options.
        $feedUrl = urldecode($this->getOption('uri'));

        /** @var \Stash\Item $cache */
        $cache = $this->getPool()->getItem($this->makeCacheKey(md5($feedUrl)));
        $cache->setInvalidationMethod(Invalidation::SLEEP, 5000, 15);

        $this->getLog()->debug('Ticker with RSS source ' . $feedUrl . '. Cache key: ' . $cache->getKey());

        // Get the document out of the cache
        $document = $cache->get();

        // Check our cache to see if the key exists
        if ($cache->isMiss()) {
            // Invalid local cache, requery using picofeed.
            $this->getLog()->debug('Cache Miss, getting RSS items');

            // Lock this cache item (120 seconds)
            $cache->lock(120);

            try {
                // Create a Guzzle Client to get the Feed XML
                $client = new Client();
                $response = $client->get($feedUrl, $this->getConfig()->getGuzzleProxy([
                    'headers' => [
                        'Accept' => 'application/rss+xml, application/rdf+xml;q=0.8, application/atom+xml;q=0.6, application/xml;q=0.4, text/xml;q=0.4, text/html;q=0.2, text/*;q=0.1'
                    ],
                    'timeout' => 20 // wait no more than 20 seconds: https://github.com/xibosignage/xibo/issues/1401
                ]));

                // Pull out the content type
                $contentType = $response->getHeaderLine('Content-Type');

                $this->getLog()->debug('Feed returned content-type ' . $contentType);

                // https://github.com/xibosignage/xibo/issues/1401
                if (stripos($contentType, 'rss') === false && stripos($contentType, 'xml') === false && stripos($contentType, 'text') === false && stripos($contentType, 'html') === false) {
                    // The content type isn't compatible
                    $this->getLog()->error('Incompatible content type: ' . $contentType);
                    return false;
                }

                // Get the body, etc
                $result = explode('charset=', $contentType);
                $document['encoding'] = isset($result[1]) ? $result[1] : '';
                $document['xml'] = $response->getBody()->getContents();

                // Add this to the cache.
                $cache->set($document);
                $cache->expiresAfter($this->getOption('updateInterval', 360) * 60);

                // Save
                $this->getPool()->saveDeferred($cache);

                $this->getLog()->debug('Processed feed and added to the cache for ' . $this->getOption('updateInterval', 360) . ' minutes');

            } catch (RequestException $requestException) {
                // Log and return empty?
                $this->getLog()->error('Unable to get feed: ' . $requestException->getMessage());
                $this->getLog()->debug($requestException->getTraceAsString());

                return false;
            }
        }

        // Cache HIT or we've requested
        // Load the feed XML document into a feed parser
        $picoFeedLoggingEnabled = Environment::isDevMode();

        try {
            // Enable logging if we need to
            if ($picoFeedLoggingEnabled) {
                $this->getLog()->debug('Setting Picofeed Logger to Enabled.');
                Logger::enable();
            }

            // Allowable attributes
            $clientConfig = new Config();

            // need a sensible way to set this
            // https://github.com/fguillot/picoFeed/issues/196
            //if ($this->getOption('allowedAttributes') != null) {
                //$clientConfig->setFilterWhitelistedTags(explode(',', $this->getOption('allowedAttributes')));
            //}

            // Get the feed parser
            $reader = new Reader($clientConfig);
            $parser = $reader->getParser($feedUrl, $document['xml'], $document['encoding']);

            // Get a feed object
            $feed = $parser->execute();

            // Get all items
            $feedItems = $feed->getItems();

        } catch (PicoFeedException $picoFeedException) {
            // Output any PicoFeed logs
            if ($picoFeedLoggingEnabled) {
                $this->getLog()->debug('Outputting Picofeed Logs.');
                foreach (Logger::getMessages() as $message) {
                    $this->getLog()->debug($message);
                }
            }

            // Log and return empty?
            $this->getLog()->error('Unable to parse feed: ' . $picoFeedException->getMessage());
            $this->getLog()->debug($picoFeedException->getTraceAsString());
            return false;
        }

        // Output any PicoFeed logs
        if ($picoFeedLoggingEnabled) {
            foreach (Logger::getMessages() as $message) {
                $this->getLog()->debug($message);
            }
        }

        // Parse the text template
        $matches = '';
        preg_match_all('/\[.*?\]/', $text, $matches);

        // Disable date sorting?
        if ($this->getOption('disableDateSort') == 0 && $this->getOption('randomiseItems', 0) == 0) {
            // Sort the items array by date
            usort($feedItems, function($a, $b) {
                /* @var Item $a */
                /* @var Item $b */

                return ($a->getDate() < $b->getDate());
            });
        }

        // Date format for the feed items
        $dateFormat = $this->getOption('dateFormat', $this->getConfig()->getSetting('DATE_FORMAT'));

        // Set an expiry time for the media
        $expiresImage = $this->getDate()->parse()->addMinutes($this->getOption('updateIntervalImages', $this->getSetting('updateIntervalImages', 1440)))->format('U');

        // Render the content now
        foreach ($feedItems as $item) {
            /* @var Item $item */

            // Substitute for all matches in the template
            $rowString = $text;

            // Run through all [] substitutes in $matches
            foreach ($matches[0] as $sub) {
                $replace = '';

                // Does our [] have a | - if so we need to do some special parsing
                if (strstr($sub, '|') !== false) {
                    // Use the provided name space to extract a tag
                    $attribute = NULL;
                    // Do we have more than 1 | - if we do then we are also interested in getting an attribute
                    if (substr_count($sub, '|') > 1)
                        list($tag, $namespace, $attribute) = explode('|', $sub);
                    else
                        list($tag, $namespace) = explode('|', $sub);

                    // Replace some things so that we know what we are looking at
                    $tag = str_replace('[', '', $tag);
                    $namespace = str_replace(']', '', $namespace);

                    if ($attribute !== null)
                        $attribute = str_replace(']', '', $attribute);

                    // What are we looking at
                    $this->getLog()->debug('Namespace: ' . $namespace . ', Tag: ' . $tag . ', Attribute: ' . $attribute);
                    //$this->getLog()->debug('Item content: %s', var_export($item, true));

                    // Are we an image place holder? [tag|image]
                    if ($namespace == 'image') {
                        // Try to get a link for the image
                        $link = null;

                        switch ($tag) {
                            case 'Link':
                                if (stripos($item->getEnclosureType(), 'image') > -1) {
                                    // Use the link to get the image
                                    $link = $item->getEnclosureUrl();

                                    if (empty($link)) {
                                        $this->getLog()->debug('No image found for Link|image tag using getEnclosureUrl');
                                    }
                                } else {
                                    $this->getLog()->debug('No image found for Link|image tag using getEnclosureType');
                                }
                                break;

                            default:
                                // Default behaviour just tries to get the content from the tag provided.
                                // it uses the attribute as a namespace if one has been provided
                                if ($attribute != null)
                                    $tags = $item->getTag($tag, $attribute);
                                else
                                    $tags = $item->getTag($tag);

                                if (count($tags) > 0 && !empty($tags[0]))
                                    $link = $tags[0];
                                else
                                    $this->getLog()->debug('Tag not found for [' . $tag . '] attribute [' . $attribute . ']');
                        }

                        $this->getLog()->debug('Resolved link: ' . $link);

                        // If we have managed to resolve a link, download it and replace the tag with the downloaded
                        // image url
                        if ($link != NULL) {
                            // Grab the profile image
                            $file = $this->mediaFactory->queueDownload('ticker_' . md5($this->getOption('url') . $link), $link, $expiresImage);

                            $replace = '<img src="' . $this->getFileUrl($file, 'image') . '" ' . $attribute . ' />';
                        }
                    } else {
                        // Our namespace is not "image". Which means we are a normal text substitution using a namespace/attribute
                        if ($attribute != null)
                            $tags = $item->getTag($tag, $attribute);
                        else
                            $tags = $item->getTag($tag);

                        // If we find some tags then do the business with them
                        if ($tags != NULL && count($tags) > 0) {
                            $replace = $tags[0];
                        } else {
                            $this->getLog()->debug('Tag not found for ' . $tag . ' attribute ' . $attribute);
                        }
                    }
                } else {
                    // Use the pool of standard tags
                    switch ($sub) {
                        case '[Name]':
                            $replace = $this->getOption('name');
                            break;

                        case '[Title]':
                            $replace = $item->getTitle();
                            break;

                        case '[Description]':
                            // Try to get the description tag
                            if (!$desc = $item->getTag('description')) {
                                // use content with tags stripped
                                $replace = strip_tags($item->getContent());
                            } else {
                                // use description
                                $replace = $desc[0];
                            }
                            break;

                        case '[Content]':
                            $replace = $item->getContent();
                            break;

                        case '[Copyright]':
                            $replace = $item->getAuthor();
                            break;

                        case '[Date]':
                            $replace = $this->getDate()->getLocalDate($item->getDate()->format('U'), $dateFormat);
                            break;

                        case '[PermaLink]':
                            $replace = $item->getTag('permalink');
                            break;

                        case '[Link]':
                            $replace = $item->getUrl();
                            break;

                        case '[Image]':
                            if (stripos($item->getEnclosureType(), 'image') > -1) {
                                // Use the link to get the image
                                $link = $item->getEnclosureUrl();

                                if (!(empty($link))) {
                                    // Grab the image
                                    $file = $this->mediaFactory->queueDownload('ticker_' . md5($this->getOption('url') . $link), $link, $expiresImage);

                                    $replace = '<img src="' . $this->getFileUrl($file, 'image') . '"/>';
                                } else {
                                    $this->getLog()->debug('No image found for image tag using getEnclosureUrl');
                                }
                            } else {
                                $this->getLog()->debug('No image found for image tag using getEnclosureType');
                            }
                            break;
                    }
                }

                if ($this->getOption('decodeHtml') == 1) {
                    $replace = htmlspecialchars_decode($replace);
                }

                if ($this->getOption('stripTags') != '') {
                    // Handle cache path for HTML serializer
                    $cachePath = $this->getConfig()->getSetting('LIBRARY_LOCATION') . 'cache/HTMLPurifier';
                    if (!is_dir($cachePath))
                        mkdir($cachePath);

                    $config = \HTMLPurifier_Config::createDefault();
                    $config->set('Cache.SerializerPath', $cachePath);
                    $config->set('HTML.ForbiddenElements', explode(',', $this->getOption('stripTags')));
                    $purifier = new \HTMLPurifier($config);
                    $replace = $purifier->purify($replace);
                }

                // Substitute the replacement we have found (it might be '')
                $rowString = str_replace($sub, $replace, $rowString);
            }

            $items[] = $rowString;
        }

        // Process queued downloads
        $this->mediaFactory->processDownloads(function($media) {
            // Success
            $this->getLog()->debug((($media->isSaveRequired) ? 'Successfully downloaded ' : 'Download not required for ') . $media->mediaId);

            // Tag this layout with this file
            $this->assignMedia($media->mediaId);
        });

        // Copyright information?
        if ($this->getOption('copyright', '') != '') {
            $items[] = '<span id="copyright">' . $this->getOption('copyright') . '</span>';
        }

        return $items;
    }

    /** @inheritdoc */
    public function isValid()
    {
        // Must have a duration
        if ($this->getUseDuration() == 1 && $this->getDuration() == 0)
            throw new InvalidArgumentException(__('Please enter a duration'), 'duration');

        // Validate the URL
        if (!v::url()->notEmpty()->validate(urldecode($this->getOption('uri'))))
            throw new InvalidArgumentException(__('Please enter a Link for this Ticker'), 'uri');

        // Make sure we have a number in here
        if (!v::numeric()->validate($this->getOption('numItems', 0)))
            throw new InvalidArgumentException(__('The value in Number of Items must be numeric.'), 'numItems');

        if ($this->getOption('updateInterval') !== null && !v::intType()->min(0)->validate($this->getOption('updateInterval', 0)))
            throw new InvalidArgumentException(__('Update Interval must be greater than or equal to 0'), 'updateInterval');

        if ($this->getOption('updateIntervalImages') !== null && !v::intType()->min(0)->validate($this->getOption('updateIntervalImages', 0)))
            throw new InvalidArgumentException(__('Update Interval Images must be greater than or equal to 0'), 'updateIntervalImages');

        return self::$STATUS_VALID;
    }

    /** @inheritdoc */
    public function getCacheDuration()
    {
        return $this->getOption('updateInterval', 120) * 60;
    }

    /** @inheritdoc */
    public function getCacheKey($displayId)
    {
        // Tickers are non-display specific
        return $this->getWidgetId() . (($displayId === 0) ? '_0' : '');
    }

    /** @inheritdoc */
    public function isCacheDisplaySpecific()
    {
        return ($this->getOption('sourceId', 1) == 2);
    }

    /** @inheritdoc */
    public function getLockKey()
    {
        // Tickers are locked to the feed
        return md5(urldecode($this->getOption('uri')));
    }
}
