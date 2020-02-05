<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2014-2015 Daniel Garner
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

use Emojione\Client;
use Emojione\Ruleset;
use Respect\Validation\Validator as v;
use Stash\Invalidation;
use Xibo\Entity\Media;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\XiboException;
use Xibo\Factory\ModuleFactory;

/**
 * Class Twitter
 * @package Xibo\Widget
 */
class Twitter extends TwitterBase
{
    public $codeSchemaVersion = 1;
    private $resourceFolder;

    /**
     * Twitter constructor.
     */
    public function init()
    {
        $this->resourceFolder = PROJECT_ROOT . '/modules/twitter/player';

        // Initialise extra validation rules
        v::with('Xibo\\Validation\\Rules\\');
    }
    
    /**
     * Install or Update this module
     * @param ModuleFactory $moduleFactory
     */
    public function installOrUpdate($moduleFactory)
    {
        if ($this->module == null) {
            // Install
            $module = $moduleFactory->createEmpty();
            $module->name = 'Twitter';
            $module->type = 'twitter';
            $module->class = 'Xibo\Widget\Twitter';
            $module->description = 'Twitter Search Module';
            $module->enabled = 1;
            $module->previewEnabled = 1;
            $module->assignable = 1;
            $module->regionSpecific = 1;
            $module->renderAs = 'html';
            $module->schemaVersion = $this->codeSchemaVersion;
            $module->defaultDuration = 60;
            $module->settings = [];
            $module->installName = 'twitter';

            $this->setModule($module);
            $this->installModule();
        }

        // Check we are all installed
        $this->installFiles();
    }

    /**
     * Install Files
     */
    public function installFiles()
    {
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/jquery-1.11.1.min.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-text-render.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-image-render.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-layout-scaler.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/emojione/emojione.sprites.png')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/emojione/emojione.sprites.css')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/bootstrap.min.css')->save();
        
        foreach ($this->mediaFactory->createModuleFileFromFolder($this->resourceFolder) as $media) {
            /* @var Media $media */
            $media->save();
        }

        // Tidy up the old SVG reference
        try {
            $oldSvg = $this->mediaFactory->createModuleFile(PROJECT_ROOT . '/modules/emojione/emojione.sprites.svg');

            if ($oldSvg->mediaId != null) {
                $this->getLog()->debug('Deleting old emoji svg file');
                $oldSvg->delete();
            }
        } catch (XiboException $xiboException) {
            $this->getLog()->error('Unable to delete old SVG reference during Twitter install. E = ' . $xiboException->getMessage());
            $this->getLog()->debug($xiboException->getTraceAsString());
        }
    }

    /**
     * Javascript functions for the layout designer
     */
    public function layoutDesignerJavaScript()
    {
        return 'twitter-designer-javascript';
    }

    /**
     * Form for updating the module settings
     */
    public function settingsForm()
    {
        return 'twitter-form-settings';
    }

    /**
     * Process any module settings
     * @throws InvalidArgumentException
     */
    public function settings()
    {
        // Process any module settings you asked for.
        $apiKey = $this->getSanitizer()->getString('apiKey');
        $apiSecret = $this->getSanitizer()->getString('apiSecret');
        $cachePeriod = $this->getSanitizer()->getInt('cachePeriod', 300);
        $cachePeriodImages = $this->getSanitizer()->getInt('cachePeriodImages', 24);

        if ($this->module->enabled != 0) {
            if ($apiKey == '')
                throw new InvalidArgumentException(__('Missing API Key'), 'apiKey');

            if ($apiSecret == '')
                throw new InvalidArgumentException(__('Missing API Secret'), 'apiSecret');

            if ($cachePeriod <= 0)
                throw new InvalidArgumentException(__('Cache period must be a positive number'), 'cachePeriod');

            if ($cachePeriodImages <= 0)
                throw new InvalidArgumentException(__('Image cache period must be a positive number'), 'cachePeriodImages');
        }

        $this->module->settings['apiKey'] = $apiKey;
        $this->module->settings['apiSecret'] = $apiSecret;
        $this->module->settings['cachePeriod'] = $cachePeriod;
        $this->module->settings['cachePeriodImages'] = $cachePeriodImages;

        // Return an array of the processed settings.
        return $this->module->settings;
    }

    /**
     * Edit Widget
     *
     * @SWG\Put(
     *  path="/playlist/widget/{widgetId}?twitter",
     *  operationId="WidgetTwitterEdit",
     *  tags={"widget"},
     *  summary="Edit a Twitter Widget",
     *  description="Edit a Twitter Widget. This call will replace existing Widget object, all not supplied parameters will be set to default.",
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
     *      description="Widget Duration",
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
     *      name="searchTerm",
     *      in="formData",
     *      description="Twitter search term, you can test your search term in twitter.com search box first",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="language",
     *      in="formData",
     *      description="Language in which tweets should be returned",
     *      type="string",
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
     *   ),
     *  @SWG\Parameter(
     *      name="backgroundColor",
     *      in="formData",
     *      description="A HEX color to use as the background color of this widget",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="noTweetsMessage",
     *      in="formData",
     *      description="A message to display when there are no tweets returned by the search query",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="dateFormat",
     *      in="formData",
     *      description="The format to apply to all dates returned by he widget",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="resultType",
     *      in="formData",
     *      description="1 - Mixed, 2 -Recent 3 - Popular, Recent shows only recent tweets, Popular the most popular tweets and Mixed included both popular and recent",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="tweetDistance",
     *      in="formData",
     *      description="Distance in miles that the tweets should be returned from. Set 0 for no restrictions",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="tweetCount",
     *      in="formData",
     *      description="The number of tweets to return",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="removeUrls",
     *      in="formData",
     *      description="Flag (0, 1) Should the URLs be removed from the tweet text?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="removeMentions",
     *      in="formData",
     *      description="Flag (0, 1) Should mentions (@someone) be removed from the tweet text?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="removeHashtags",
     *      in="formData",
     *      description="Flag (0, 1) Should the hashtags (#something) be removed from the tweet text",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="updateInterval",
     *      in="formData",
     *      description="Update interval in minutes, should be kept as high as possible, if data change once per hour, this should be set to 60",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="durationIsPerItem",
     *      in="formData",
     *      description="A flag (0, 1), The duration specified is per page/item, otherwise the widget duration is divided between the number of pages/items",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="itemsPerPage",
     *      in="formData",
     *      description="EDIT Only - When in single mode, how many items per page should be shown",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="templateId",
     *      in="formData",
     *      description="Use pre-configured templates, available options: full-timeline-np, full-timeline, tweet-only, tweet-with-profileimage-left, tweet-with-profileimage-right, tweet-1, tweet-2, tweet-4. tweet-6NP, tweet-6PL, tweet-7, tweet-8",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="overrideTemplate",
     *      in="formData",
     *      description="flag (0, 1) set to 0 and use templateId or set to 1 and provide whole template in the next parameters",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="widgetOriginalWidth",
     *      in="formData",
     *      description="This is the intended Width of the template and is used to scale the Widget within it's region when the template is applied, Pass only with overrideTemplate set to 1",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="widgetOriginalHeight",
     *      in="formData",
     *      description="This is the intended Height of the template and is used to scale the Widget within it's region when the template is applied, Pass only with overrideTemplate set to 1",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="widgetOriginalPadding",
     *      in="formData",
     *      description="This is the intended Padding of the template and is used to scale the Widget within it's region when the template is applied, Pass only with overrideTemplate set to 1",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="resultContent",
     *      in="formData",
     *      description="Intended content Type, available Options: 0 - All Tweets 1 - Tweets with the text only content 2 - Tweets with the text and image content. Pass only with overrideTemplate set to 1",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="template",
     *      in="formData",
     *      description="Main template, Pass only with overrideTemplate set to 1 ",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ta_text_advanced",
     *      in="formData",
     *      description="A flag (0, 1), Should text area by presented as a visual editor?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="styleSheet",
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
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     * @throws \Xibo\Exception\XiboException
     */
    public function edit()
    {
        $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setOption('name', $this->getSanitizer()->getString('name'));
        $this->setOption('enableStat', $this->getSanitizer()->getString('enableStat'));
        $this->setOption('searchTerm', $this->getSanitizer()->getString('searchTerm'));
        $this->setOption('language', $this->getSanitizer()->getString('language'));
        $this->setOption('effect', $this->getSanitizer()->getString('effect'));
        $this->setOption('speed', $this->getSanitizer()->getInt('speed'));
        $this->setOption('backgroundColor', $this->getSanitizer()->getString('backgroundColor'));
        $this->setOption('noTweetsMessage', $this->getSanitizer()->getString('noTweetsMessage'));
        $this->setOption('dateFormat', $this->getSanitizer()->getString('dateFormat'));
        $this->setOption('resultType', $this->getSanitizer()->getString('resultType'));
        $this->setOption('tweetDistance', $this->getSanitizer()->getInt('tweetDistance'));
        $this->setOption('tweetCount', $this->getSanitizer()->getInt('tweetCount'));
        $this->setOption('removeUrls', $this->getSanitizer()->getCheckbox('removeUrls'));
        $this->setOption('removeMentions', $this->getSanitizer()->getCheckbox('removeMentions'));
        $this->setOption('removeHashtags', $this->getSanitizer()->getCheckbox('removeHashtags'));
        $this->setOption('overrideTemplate', $this->getSanitizer()->getCheckbox('overrideTemplate'));
        $this->setOption('updateInterval', $this->getSanitizer()->getInt('updateInterval', 60));
        $this->setOption('templateId', $this->getSanitizer()->getString('templateId'));
        $this->setOption('durationIsPerItem', $this->getSanitizer()->getCheckbox('durationIsPerItem'));
        $this->setOption('itemsPerPage', $this->getSanitizer()->getInt('itemsPerPage', 5));
        $this->setRawNode('javaScript', $this->getSanitizer()->getParam('javaScript', ''));

        if ($this->getOption('overrideTemplate') == 1) {
            $this->setRawNode('template', $this->getSanitizer()->getParam('ta_text', $this->getSanitizer()->getParam('template', null)));
            $this->setOption('ta_text_advanced', $this->getSanitizer()->getCheckbox('ta_text_advanced'));
            $this->setRawNode('styleSheet', $this->getSanitizer()->getParam('ta_css', $this->getSanitizer()->getParam('styleSheet', null)));
            $this->setOption('resultContent', $this->getSanitizer()->getString('resultContent'));

            $this->setOption('widgetOriginalPadding', $this->getSanitizer()->getInt('widgetOriginalPadding'));
            $this->setOption('widgetOriginalWidth', $this->getSanitizer()->getInt('widgetOriginalWidth'));
            $this->setOption('widgetOriginalHeight', $this->getSanitizer()->getInt('widgetOriginalHeight'));
        }

        // Save the widget
        $this->isValid();
        $this->saveWidget();
    }

    /**
     * @param int $displayId
     * @param bool $isPreview
     * @return array|false
     * @throws \Xibo\Exception\XiboException
     */
    protected function getTwitterFeed($displayId = 0, $isPreview = true)
    {
        // Do we need to add a geoCode?
        $geoCode = '';
        $distance = $this->getOption('tweetDistance');
        if ($distance != 0) {
            // Use the display ID or the default.
            if ($displayId != 0) {
                // Look up the lat/long
                $display = $this->displayFactory->getById($displayId);
                $defaultLat = $display->latitude;
                $defaultLong = $display->longitude;
            } else {
                $defaultLat = $this->getConfig()->getSetting('DEFAULT_LAT');
                $defaultLong = $this->getConfig()->getSetting('DEFAULT_LONG');
            }

            // Built the geoCode string.
            $geoCode = implode(',', array($defaultLat, $defaultLong, $distance)) . 'mi';
        }
        
        if( $this->getOption('overrideTemplate') == 0 ) {
            
            $tmplt = $this->getTemplateById($this->getOption('templateId'));
            
            if (isset($tmplt)) {
                $template = $tmplt['template'];
                $resultContent = $tmplt['resultContent'];
            }
            
        } else {
            $template = $this->getRawNode('template', null);
            $resultContent = $this->getOption('resultContent');
        }
        
        // Search content filtered by type of tweets  
        $searchTerm = $this->getOption('searchTerm');
        
        switch ($resultContent) {
          case 0:
            //Default
            $searchTerm .= '';
            break;
            
          case 1:
            // Remove media
            $searchTerm .= ' -filter:media';
            break;
            
          case 2:
            // Only tweets with native images
            $searchTerm .= ' filter:twimg';
            break; 
               
          default:
            $searchTerm .= '';
            break;
        }
        
        // Connect to twitter and get the twitter feed.
        /** @var \Stash\Item $cache */
        $cache = $this->getPool()->getItem($this->makeCacheKey(md5($searchTerm . $this->getOption('language') . $this->getOption('resultType') . $this->getOption('tweetCount', 15) . $geoCode)));
        $cache->setInvalidationMethod(Invalidation::SLEEP, 5000, 15);

        $data = $cache->get();

        if ($cache->isMiss()) {

            // Lock this cache item (for 30 seconds)
            $cache->lock();

            $this->getLog()->debug('Querying API for ' . $searchTerm);

            // We need to search for it
            if (!$token = $this->getToken())
                return [];

            // We have the token, make a tweet
            if (!$data = $this->searchApi($token, $searchTerm, $this->getOption('language'), $this->getOption('resultType'), $geoCode, $this->getOption('tweetCount', 15)))
                return [];

            // Cache it
            $cache->set($data);
            $cache->expiresAfter($this->getSetting('cachePeriod', 3600));
            $this->getPool()->saveDeferred($cache);
        }

        // Get the template
        $template = $this->parseLibraryReferences($isPreview, $template);

        // Parse translations
        $template = $this->parseTranslations($template);

        // Parse the text template
        $matches = '';
        preg_match_all('/\[.*?\]/', $template, $matches);

        // Build an array to return
        $return = array();

        // Expiry time for any media that is downloaded
        $expires = $this->getDate()->parse()->addHours($this->getSetting('cachePeriodImages', 24))->format('U');

        // Remove URL setting
        $removeUrls = $this->getOption('removeUrls', 1)  == 1;
        $removeMentions = $this->getOption('removeMentions', 1)  == 1;
        $removeHashTags = $this->getOption('removeHashTags', 1)  == 1;

        // If we have nothing to show, display a no tweets message.
        if (count($data->statuses) <= 0) {
            // Create ourselves an empty tweet so that the rest of the code can continue as normal
            $user = new \stdClass();
            $user->name = '';
            $user->screen_name = '';
            $user->profile_image_url = '';
            $user->location = '';

            $tweet = new \stdClass();
            $tweet->full_text = $this->getOption('noTweetsMessage', __('There are no tweets to display'));
            $tweet->created_at = date("Y-m-d H:i:s");
            $tweet->user = $user;

            // Append to our statuses
            $data->statuses[] = $tweet;
        }

        // Make an emojione client
        $emoji = new Client(new Ruleset());
        $emoji->imageType = 'png';
        $emoji->sprites = true;
        $emoji->imagePathPNG = $this->getResourceUrl('emojione/emojione.sprites.png');

        // Get the date format to apply
        $dateFormat = $this->getOption('dateFormat', $this->getConfig()->getSetting('DATE_FORMAT'));

        // This should return the formatted items.
        foreach ($data->statuses as $tweet) {
            // Substitute for all matches in the template
            $rowString = $template;

            foreach ($matches[0] as $sub) {
                // Always clear the stored template replacement
                $replace = '';
                $tagOptions = array();
                
                // Get the options from the tag and create an array
                $subClean = str_replace('[', '', str_replace(']', '', $sub));
                if (stripos($subClean, '|') > -1) {
                    $tagOptions = explode('|', $subClean);
                    
                    // Save the main tag 
                    $subClean = $tagOptions[0];
                    
                    // Remove the tag from the first position
                    array_shift($tagOptions);
                }
                
                // Maybe make this more generic?
                switch ($subClean) {
                    case 'Tweet':
                        // Get the tweet text to operate on, if it is a retweet we need to take the full_text in a different way
                        if (isset($tweet->retweeted_status)){
                            $tweetText = 'RT @' . $tweet->retweeted_status->user->screen_name . ': ' . $tweet->retweeted_status->full_text;
                        }
                        else
                            $tweetText = $tweet->full_text;
                            
                        // Replace URLs with their display_url before removal
                        if (isset($tweet->entities->urls)) {
                            foreach ($tweet->entities->urls as $url) {
                                $tweetText = str_replace($url->url, $url->display_url, $tweetText);
                            }
                        }

                        // Clean up the tweet text
                        // thanks to https://github.com/solarbug (https://github.com/xibosignage/xibo/issues/703)
                        // Remove Mentions
                        if ($removeMentions)
                            $tweetText = preg_replace('/(\s+|^)@\S+/', '', $tweetText);

                        // Remove HashTags
                        if ($removeHashTags)
                            $tweetText = preg_replace('/(\s+|^)#\S+/', '', $tweetText);

                        if ($removeUrls)
                            // Regex taken from http://daringfireball.net/2010/07/improved_regex_for_matching_urls
                            $tweetText  = preg_replace('~(?i)\b((?:[a-z][\w-]+:(?:/{1,3}|[a-z0-9%])|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:\'".,<>?«»“”‘’]))~', '', $tweetText); // remove urls

                        $replace = $emoji->toImage($tweetText);
                        break;

                    case 'User':
                        $replace = $emoji->toImage($tweet->user->name);
                        break;

                    case 'ScreenName':
                        $replace = ($tweet->user->screen_name != '') ? ('@' . $tweet->user->screen_name) : '';
                        break;

                    case 'Date':
                        $replace = $this->getDate()->getLocalDate(strtotime($tweet->created_at), $dateFormat);
                        break;
  
                    case 'Location':
                        $replace = $tweet->user->location;
                        break;

                    case 'ProfileImage':
                        // Grab the profile image
                        if ($tweet->user->profile_image_url != '') {
                            
                            // Original Default Image
                            $imageSizeType = "";
                            if( count($tagOptions) > 0 ) {
                              // Image options ( normal, bigger, mini )
                              $imageSizeType = '_' . $tagOptions[0];
                            }
                            
                            // Twitter image size
                            $tweet->user->profile_image_url = str_replace('_normal', $imageSizeType, $tweet->user->profile_image_url);
                            
                            // Grab the profile image
                            $file = $this->mediaFactory->queueDownload('twitter_' . $tweet->user->id, $tweet->user->profile_image_url, $expires);

                            $replace = ($isPreview)
                                ? '<img src="' . $this->getApp()->urlFor('library.download', ['id' => $file->mediaId, 'type' => 'image']) . '?preview=1" />'
                                : '<img src="' . $file->storedAs . '"  />';
                        }
                        break;

                    case 'Photo':
                        // See if there are any photos associated with this tweet.
                        if ((isset($tweet->entities->media) && count($tweet->entities->media) > 0) || (isset($tweet->retweeted_status->entities->media) && count($tweet->retweeted_status->entities->media) > 0)) {
                            
                            // See if it's an image from a tweet or RT, and only take the first one
                            $mediaObject = (isset($tweet->entities->media))
                                ? $tweet->entities->media[0]
                                : $tweet->retweeted_status->entities->media[0];
                            
                            $photoUrl = $mediaObject->media_url;
                            
                            if ($photoUrl != '') {
                                $file = $this->mediaFactory->queueDownload('twitter_photo_' . $tweet->user->id . '_' . $mediaObject->id_str, $photoUrl, $expires);

                                $replace = ($isPreview)
                                    ? '<img src="' . $this->getApp()->urlFor('library.download', ['id' => $file->mediaId, 'type' => 'image']) . '?preview=1" />'
                                    : '<img src="' . $file->storedAs . '"  />';
                            }
                        }
                        break;
                        
                    case 'TwitterLogoWhite':
                        //Get the Twitter logo image file path
                        $replace = $this->getResourceUrl('twitter/twitter_white.png');
                        break;
                        
                    case 'TwitterLogoBlue':
                        //Get the Twitter logo image file path
                        $replace = $this->getResourceUrl('twitter/twitter_blue.png');
                        break;

                    default:
                        $replace = '';
                }

                $rowString = str_replace($sub, $replace, $rowString);
            }

            // Substitute the replacement we have found (it might be '')
            $return[] = $rowString;
        }

        // Process queued downloads
        $this->mediaFactory->processDownloads(function($media) {
            // Success
            $this->getLog()->debug('Successfully downloaded ' . $media->mediaId);

            // Tag this layout with this file
            $this->assignMedia($media->mediaId);
        });

        // Return the data array
        return $return;
    }

    /** @inheritdoc */
    public function getResource($displayId = 0)
    {
        // Make sure we are set up correctly
        if ($this->getSetting('apiKey') == '' || $this->getSetting('apiSecret') == '') {
            $this->getLog()->error('Twitter Module not configured. Missing API Keys');
            return '';
        }

        $data = [];
        $isPreview = ($this->getSanitizer()->getCheckbox('preview') == 1);

        // Replace the View Port Width?
        $data['viewPortWidth'] = ($isPreview) ? $this->region->width : '[[ViewPortWidth]]';

        // Information from the Module
        $duration = $this->getCalculatedDurationForGetResource();
        $numItems = $this->getOption('numItems', 0);
        $itemsPerPage = $this->getOption('itemsPerPage', 0);
        $durationIsPerItem = $this->getOption('durationIsPerItem', 1);
        
        if( $this->getOption('overrideTemplate') == 0 ) {
            
            $template = $this->getTemplateById($this->getOption('templateId'));
            
            if (isset($template)) {
                $css = $template['css'];
                $widgetOriginalWidth = $template['widgetOriginalWidth'];
                $widgetOriginalHeight = $template['widgetOriginalHeight'];
                $widgetOriginalPadding = $template['widgetOriginalPadding'];
                $resultContent = $template['resultContent'];
            }
            
        } else {
            $css = $this->getRawNode('styleSheet', '');
            $widgetOriginalWidth = $this->getSanitizer()->int($this->getOption('widgetOriginalWidth'));
            $widgetOriginalHeight = $this->getSanitizer()->int($this->getOption('widgetOriginalHeight'));
            $widgetOriginalPadding = $this->getSanitizer()->int($this->getOption('widgetOriginalPadding'));
            $resultContent = $this->getOption('resultContent');
        }

        // Generate a JSON string of substituted items.
        $items = $this->getTwitterFeed($displayId, $isPreview);

        // Return empty string if there are no items to show.
        if (count($items) == 0) {
            return '';
        }

        $options = array(
            'type' => $this->getModuleType(),
            'fx' => $this->getOption('effect', 'noAnim'),
            'speed' => $this->getOption('speed', 500),
            'duration' => $duration,
            'durationIsPerItem' => ($this->getOption('durationIsPerItem', 0) == 1),
            'numItems' => count($items),
            'originalWidth' => $this->region->width,
            'originalHeight' => $this->region->height,
            'widgetDesignPadding' => $widgetOriginalPadding,
            'widgetDesignWidth' => $widgetOriginalWidth,
            'widgetDesignHeight'=> $widgetOriginalHeight,
            'itemsPerPage' => $this->getSanitizer()->int($this->getOption('itemsPerPage', 5))
        );

        // Work out how many pages we will be showing.
        $pages = $numItems;

        if ($numItems > count($items) || $numItems == 0)
            $pages = count($items);

        $pages = ($itemsPerPage > 0) ? ceil($pages / $itemsPerPage) : $pages;
        $totalDuration = ($durationIsPerItem == 0) ? $duration : ($duration * $pages);
        
        // Replace the control meta with our data from twitter
        $data['controlMeta'] = '<!-- NUMITEMS=' . $pages . ' -->' . PHP_EOL . '<!-- DURATION=' . $totalDuration . ' -->';

        // Replace the head content
        $headContent = '';

        // Get the JavaScript node
        $javaScript = $this->parseLibraryReferences($isPreview, $this->getRawNode('javaScript', ''));

        // Add our fonts.css file
        $headContent .= '
            <link href="' . (($isPreview) ? $this->getApp()->urlFor('library.font.css') : 'fonts.css') . '" rel="stylesheet" media="screen">
            <link href="' . $this->getResourceUrl('vendor/bootstrap.min.css')  . '" rel="stylesheet" media="screen">
            <link href="' . $this->getResourceUrl('emojione/emojione.sprites.css')  . '" rel="stylesheet" media="screen">
        ';

        $backgroundColor = $this->getOption('backgroundColor');
        if ($backgroundColor != '') {
            $headContent .= '<style type="text/css">body { background-color: ' . $backgroundColor . ' }</style>';
        } else {
          $headContent .= '<style type="text/css"> body { background-color: transparent }</style>';
        }
        
        // Add the CSS if it isn't empty
        if ($css != '') {
            $headContent .= '<style type="text/css">' . $this->parseLibraryReferences($isPreview, $css) . '</style>';
        }
        $headContent .= '<style type="text/css">' . file_get_contents($this->getConfig()->uri('css/client.css', true)) . '</style>';

        // Replace the Head Content with our generated javascript
        $data['head'] = $headContent;

        // Add some scripts to the JavaScript Content
        $javaScriptContent = '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery-1.11.1.min.js') . '"></script>';

        // Need the cycle plugin?
        if ($this->getOption('effect') != 'none')
            $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery-cycle-2.1.6.min.js') . '"></script>';

        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-layout-scaler.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-text-render.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-image-render.js') . '"></script>';

        $javaScriptContent .= '<script type="text/javascript">';
        $javaScriptContent .= '   var options = ' . json_encode($options) . ';';
        $javaScriptContent .= '   var items = ' . json_encode($items) . ';';
        $javaScriptContent .= '   $(document).ready(function() { ';
        $javaScriptContent .= '       $("body").xiboLayoutScaler(options); $("#content").xiboTextRender(options, items); $("img").xiboImageRender(options);';
        $javaScriptContent .= '   }); ';
        $javaScriptContent .= $javaScript;
        $javaScriptContent .= '</script>';

        // Replace the Head Content with our generated javascript
        $data['javaScript'] = $javaScriptContent;

        return $this->renderTemplate($data);
    }

    /** @inheritdoc */
    public function isValid()
    {
        // If overrideTemplate is false we have to define a template Id
        if ($this->getOption('overrideTemplate') == 0 && ( $this->getOption('templateId') == '' || $this->getOption('templateId') == null))
            throw new InvalidArgumentException(__('Please choose a template'), 'templateId');

        if ($this->getUseDuration() == 1 && $this->getDuration() == 0)
            throw new InvalidArgumentException(__('Please enter a duration'), 'duration');

        if (!v::stringType()->notEmpty()->validate($this->getOption('searchTerm')))
            throw new InvalidArgumentException(__('Please enter a search term'), 'searchTerm');

        return self::$STATUS_VALID;
    }

    /** @inheritdoc */
    public function getCacheDuration()
    {
        $cachePeriod = $this->getSetting('cachePeriod', 3600);
        $updateInterval = $this->getOption('updateInterval', 60) * 60;
        return max($updateInterval, $cachePeriod);
    }

    /** @inheritdoc */
    public function getCacheKey($displayId)
    {
        if ($displayId === 0 || $this->getOption('tweetDistance', 0) > 0) {
            // We use the display to fence in the tweets to our location
            return $this->getWidgetId() . '_' . $displayId;
        } else {
            // Non-display specific
            return $this->getWidgetId(). (($displayId === 0) ? '_0' : '');
        }
    }

    /** @inheritdoc */
    public function isCacheDisplaySpecific()
    {
        return ($this->getOption('tweetDistance', 0) > 0);
    }

    /** @inheritdoc */
    public function getLockKey()
    {
        // What is the minimum likely lock we can get to prevent concurrent access - probably search term
        return $this->getOption('searchTerm');
    }
}
