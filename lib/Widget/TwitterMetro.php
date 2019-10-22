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
use Xibo\Exception\ConfigurationException;
use Xibo\Exception\XiboException;
use Xibo\Factory\ModuleFactory;

/**
 * Class TwitterMetro
 * @package Xibo\Widget
 */
class TwitterMetro extends TwitterBase
{
    private $parent;
    public $codeSchemaVersion = 1;
    private $resourceFolder;

    /**
     * TwitterMetro constructor.
     */
    public function init()
    {
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
            $module->name = 'Twitter Metro';
            $module->type = 'twittermetro';
            $module->class = 'Xibo\Widget\TwitterMetro';
            $module->description = 'Twitter Metro Search Module';
            $module->enabled = 1;
            $module->previewEnabled = 1;
            $module->assignable = 1;
            $module->regionSpecific = 1;
            $module->renderAs = 'html';
            $module->schemaVersion = $this->codeSchemaVersion;
            $module->defaultDuration = 60;
            $module->settings = [];
            $module->installName = 'twittermetro';

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
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-metro-render.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-layout-scaler.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-image-render.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/emojione/emojione.sprites.png')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/emojione/emojione.sprites.css')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/bootstrap.min.css')->save();
    }

    /**
     * Override the apikey/secret
     * @param string $setting
     * @param null $default
     * @return mixed|string
     */
    public function getSetting($setting, $default = NULL)
    {
        if ($setting == 'apiKey' || $setting == 'apiSecret' || $setting == 'cachePeriod') {
            // Create a Twitter Module as the source of this modules settings
            // only go to the stock twitter module
            if ($this->parent === null)
                $this->parent = $this->moduleFactory->createByClass('Xibo\Widget\Twitter');

            return $this->parent->getSetting($setting, $default);
        }

        return parent::getSetting($setting, $default);
    }
    
    /**
     * Javascript functions for the layout designer
     */
    public function layoutDesignerJavaScript()
    {
        // We use the same javascript as the data set view designer
        return 'twittermetro-designer-javascript';
    }
    
    /**
     * Get the template HTML, CSS, widgetOriginalWidth, widgetOriginalHeight giving its orientation (0:Landscape 1:Portrait)
     * @return array
     */
    public function getTemplateData() {
        
        $orientation = ($this->getSanitizer()->getDouble('width', $this->region->width) > $this->getSanitizer()->getDouble('height', $this->region->height)) ? 0 : 1; 
        
        $templateArray = array(
            array(  'template' => '<div class="cell-[itemType] [ShadowType] cell" id="item-[itemId]" style="[Photo]"> <div class="item-container [ShadowType]" style="[Color]"> <div class="item-text">[Tweet]</div> <div class="userData"> <div class="tweet-profilePic">[ProfileImage|normal]</div> <div class="tweet-userData"> <div class="user">[User]</div> <small>[Date]</small></div> </div> </div> </div>',
                    'styleSheet' => 'body { font-family: "Helvetica", "Arial", sans-serif; line-height: 1; margin: 0; } #content { width: 1920px !important; height: 1080px !important; background: rgba(255, 255, 255, 0.6); color: rgba(255, 255, 255, 1); } .row-1 { height: 360px; } .page { float: left; margin: 0; padding: 0; } .cell-1 { width: 310px; } .cell-2 { width: 630px; } .cell-3 { width: 950px; } .cell-1, .cell-2, .cell-3 { float: left; height: inherit; margin: 5px; background-repeat: no-repeat; background-size: cover; background-position-x: 50%; background-position-y: 50%; } .item-container { padding: 10px; color: #fff; height: 350px; } .userData { height: 50px; } .darken-container { background-color: rgba(0, 0, 0, 0.4); } .tweet-profilePic { width: 20%; float: left; } .tweet-profilePic img { width: 48px; } .tweet-userData { width: 80%; float: left; text-align: right; } .item-text { padding: 10px; color: #fff; } .emojione { width: 26px; height: 26px; } .cell-1 .item-text { line-height: 30px; font-size: 25px; height: 280px; } .cell-2 .item-text { line-height: 40px; font-size: 40px; height: 280px; } .cell-3 .item-text { line-height: 53px; font-size: 50px; height: 280px; } .user { font-size: 14px; font-weight: bold; padding-top: 20px; } .shadow { text-shadow: 1px 1px 2px rgba(0, 0, 3, 1); } .no-shadow { text-shadow: none !important; } small { font-size: 70%; }',
                    'originalWidth' => '1920',
                    'originalHeight' => '1080'
            ),
            array(  'template' => '<div class="cell-[itemType] [ShadowType] cell" id="item-[itemId]" style="[Photo]"> <div class="item-container [ShadowType]" style="[Color]"> <div class="item-text">[Tweet]</div> <div class="userData"> <div class="tweet-profilePic">[ProfileImage|normal]</div> <div class="tweet-userData"> <div class="user">[User]</div> <small>[Date]</small></div> </div> </div> </div>',
                    'styleSheet' => 'body { font-family: "Helvetica", "Arial", sans-serif; line-height: 1; margin: 0; } #content { width: 1080px !important; height: 1920px !important; background: rgba(255, 255, 255, 0.6); color: rgba(255, 255, 255, 1); } .row-1 { height: 320px; } .page { float: left; margin: 0; padding: 0; } .cell-1 { width: 350px; } .cell-2 { width: 710px; } .cell-3 { width: 1070px; } .cell-1, .cell-2, .cell-3 { float: left; height: inherit; margin: 5px; background-repeat: no-repeat; background-size: cover; background-position-x: 50%; background-position-y: 50%; } .item-container { padding: 10px; color: #fff; height: 310px; } .userData { height: 50px; } .darken-container { background-color: rgba(0, 0, 0, 0.4); } .tweet-profilePic { width: 20%; float: left; } .tweet-profilePic img { width: 48px; } .tweet-userData { width: 80%; float: left; text-align: right; } .item-text { padding: 10px; color: #fff; } .emojione { width: 26px; height: 26px; } .cell-1 .item-text { line-height: 30px; font-size: 25px; height: 240px; } .cell-2 .item-text { line-height: 40px; font-size: 40px; height: 240px; } .cell-3 .item-text { line-height: 53px; font-size: 50px; height: 240px; } .user { font-size: 14px; font-weight: bold; padding-top: 20px; } .shadow { text-shadow: 1px 1px 2px rgba(0, 0, 3, 1); } .no-shadow { text-shadow: none !important; } small { font-size: 70%; }',
                    'originalWidth' => '1080',
                    'originalHeight' => '1920'
            )
        );
        
        return $templateArray[$orientation];
    }

    /**
     * Form for updating the module settings
     */
    public function settingsForm()
    {
        return 'twittermetro-form-settings';
    }

    public function validate()
    {
        // If overrideColorTemplate is false we have to define a template Id 
        if($this->getOption('overrideColorTemplate') == 0 && ( $this->getOption('colorTemplateId') == '' || $this->getOption('colorTemplateId') == null) )
            throw new \InvalidArgumentException(__('Please choose a template'));
            
        if ($this->getUseDuration() == 1 && $this->getDuration() == 0)
            throw new \InvalidArgumentException(__('Please enter a duration'));

        if (!v::stringType()->notEmpty()->validate($this->getOption('searchTerm')))
            throw new \InvalidArgumentException(__('Please enter a search term'));
    }

    /**
     * Edit Twitter Metro
     *
     * @SWG\Put(
     *  path="/playlist/widget/{widgetId}?twitterMetro",
     *  operationId="WidgetTwitterMetroEdit",
     *  tags={"widget"},
     *  summary="Edit a Twitter Metro Widget",
     *  description="Edit a Twitter Metro Widget. This call will replace existing Widget object, all not supplied parameters will be set to default.",
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
     *      name="colorTemplateId",
     *      in="formData",
     *      description="Use pre-configured templates, available options: default, full, gray, light, soft, vivid",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="overrideColorTemplate",
     *      in="formData",
     *      description="flag (0, 1) set to 0 and use colorTemplateId or set to 1 and provide colours to use in templateColours parameter",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="templateColours",
     *      in="formData",
     *      description="Provide a string of new HEX colour codes to use, separated by |, for example: #e0d2c8|#5e411d|#fccf12|#82ff00|#64bae8",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="resultContent",
     *      in="formData",
     *      description="Intended content Type, available Options: 0 - All Tweets 1 - Tweets with the text only content 2 - Tweets with the text and image content. Pass only with overrideColorTemplate set to 1",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="removeRetweets",
     *      in="formData",
     *      description="Flag (0, 1) Should retweets be filtered?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=201,
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
        $this->setOption('tweetCount', $this->getSanitizer()->getInt('tweetCount', 60));
        $this->setOption('removeUrls', $this->getSanitizer()->getCheckbox('removeUrls'));
        $this->setOption('removeMentions', $this->getSanitizer()->getCheckbox('removeMentions'));
        $this->setOption('removeHashtags', $this->getSanitizer()->getCheckbox('removeHashtags'));
        $this->setOption('overrideColorTemplate', $this->getSanitizer()->getCheckbox('overrideColorTemplate'));
        $this->setOption('updateInterval', $this->getSanitizer()->getInt('updateInterval', 60));
        $this->setOption('colorTemplateId', $this->getSanitizer()->getString('colorTemplateId'));
        $this->setOption('resultContent', $this->getSanitizer()->getString('resultContent'));
        $this->setOption('removeRetweets', $this->getSanitizer()->getCheckbox('removeRetweets'));

        if ($this->getOption('overrideColorTemplate') == 1) {
            // Convert the colors array to string to be able to save it
            $stringColor = $this->getSanitizer()->getStringArray('color')[0];
            for ($i=1; $i < count($this->getSanitizer()->getStringArray('color')); $i++) {
                if(!empty($this->getSanitizer()->getStringArray('color')[$i]))
                    $stringColor .= "|" . $this->getSanitizer()->getStringArray('color')[$i];
            }
            $this->setOption('templateColours', $stringColor);
        }

        // Save the widget
        $this->validate();
        $this->saveWidget();
    }

    /**
     * @param int $displayId
     * @param bool $isPreview
     * @return array|false
     * @throws XiboException
     */
    protected function getTwitterFeed($displayId = 0, $isPreview = true)
    {
        if (!extension_loaded('curl'))
            throw new ConfigurationException(__('cURL extension is required for Twitter'));

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
        
        // Search content filtered by type of tweets  
        $searchTerm = $this->getOption('searchTerm');
        $resultContent = $this->getOption('resultContent');
        
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
        
        // Search term retweets filter
        $searchTerm .= ($this->getOption('removeRetweets')) ? ' -filter:retweets' : '';
        
        // Connect to twitter and get the twitter feed.
        /** @var \Stash\Item $cache */
        $cache = $this->getPool()->getItem($this->makeCacheKey(md5($searchTerm . $this->getOption('language') . $this->getOption('resultType') . $this->getOption('tweetCount', 60) . $geoCode)));
        $cache->setInvalidationMethod(Invalidation::SLEEP, 5000, 15);

        $data = $cache->get();

        if ($cache->isMiss()) {

            $this->getLog()->debug('Querying API for ' . $searchTerm);

            // Lock this cache item (for 30 seconds)
            $cache->lock();

            // We need to search for it
            if (!$token = $this->getToken())
                return false;

            // We have the token, make a tweet
            if (!$data = $this->searchApi($token, $searchTerm, $this->getOption('language'), $this->getOption('resultType'), $geoCode, $this->getOption('tweetCount', 60)))
                return false;

            // Cache it
            $cache->set($data);
            $cache->expiresAfter($this->getSetting('cachePeriod', 3600));
            $this->getPool()->saveDeferred($cache);
        }

        // Get the template data
        $templateData = $this->getTemplateData();
        $template = $this->parseLibraryReferences($isPreview, $templateData['template']);

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
            $tweet->created_at = '';
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
                        // Get the tweet text to operate on
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
                        if($tweet->created_at != '')
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
                        
                    case 'Color':
                        // See if there is a profile image
                        if (!$this->tweetHasPhoto($tweet)) {
                        
                            // Get the colors array
                            if ($this->getOption('overrideColorTemplate') == 0) {
                                $colorTemplate = $this->getTemplateById($this->getOption('colorTemplateId'));

                                if ($colorTemplate === null)
                                    $colorTemplate = $this->getTemplateById('default');

                                $colorArray = $colorTemplate['colors'];
                            } else {
                                $colorArray = explode("|", $this->getOption('templateColours'));
                            }
                            
                            // Find a random color
                            $randomNum = rand(0,count($colorArray)-1);
                            $randomColor = $colorArray[$randomNum];
                            
                            $replace = 'background-color:' . $randomColor;
                        }
                        break;
                        
                    case 'ShadowType':
                        // See if there is a profile image
                        $replace = ($this->tweetHasPhoto($tweet)) ? 'shadow darken-container' : '';
                        break;

                    case 'Photo':
                        // See if there are any photos associated with this tweet.
                        if ($this->tweetHasPhoto($tweet)) {
                            
                            // See if it's an image from a tweet or RT, and only take the first one
                            $mediaObject = (isset($tweet->entities->media))
                                ? $tweet->entities->media[0]
                                : $tweet->retweeted_status->entities->media[0];
                            
                            $photoUrl = $mediaObject->media_url;
                            
                            if ($photoUrl != '') {
                                $file = $this->mediaFactory->queueDownload('twitter_photo_' . $tweet->user->id . '_' . $mediaObject->id_str, $photoUrl, $expires);

                                $replace = "background-image: url(" 
                                    . (($isPreview) ? $this->getApp()->urlFor('library.download', ['id' => $file->mediaId, 'type' => 'image']) : $file->storedAs)
                                    . ")";
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
                        $replace = '[' . $subClean . ']';
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

        // Generate a JSON string of substituted items.
        $items = $this->getTwitterFeed($displayId, $isPreview);
        
        // Get the template data
        $templateData = $this->getTemplateData();
        
        // Return empty string if there are no items to show.
        if (count($items) == 0) {
            return '';
        }

        $options = array(
            'type' => $this->getModuleType(),
            'fx' => $this->getOption('effect', 'noAnim'),
            'speed' => $this->getOption('speed', 500),
            'duration' => $duration,
            'numItems' => count($items),
            'originalWidth' => $this->region->width,
            'originalHeight' => $this->region->height,
            'widgetDesignWidth' => $templateData['originalWidth'],
            'widgetDesignHeight'=> $templateData['originalHeight'],
            'resultContent'=> $this->getSanitizer()->string($this->getOption('resultContent'))            
        );

        // Replace the control meta with our data from twitter
        $data['controlMeta'] = '<!-- NUMITEMS=' . count($items) . ' -->' . PHP_EOL . '<!-- DURATION=' . $duration . ' -->';

        // Replace the head content
        $headContent = '';

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
        $css = $templateData['styleSheet'];
        
        if ($css != '') {
            $headContent .= '<style type="text/css">' . $this->parseLibraryReferences($isPreview, $css) . '</style>';
        }
        $headContent .= '<style type="text/css">' . file_get_contents($this->getConfig()->uri('css/client.css', true)) . '</style>';

        // Replace the Head Content with our generated javascript
        $data['head'] = $headContent;

        // Add some scripts to the JavaScript Content
        $javaScriptContent = '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery-1.11.1.min.js') . '"></script>';

        // Get the colors array
        if ($this->getOption('overrideColorTemplate') == 0) {
            $template = $this->getTemplateById($this->getOption('colorTemplateId'));

            if ($template === null)
                $template = $this->getTemplateById('default');

            $colorArray = $template['colors'];
        } else {
            $colorArray = explode("|", $this->getOption('templateColours'));
        }
        
        // Need the cycle plugin?
        if ($this->getOption('effect') != 'none')
            $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery-cycle-2.1.6.min.js') . '"></script>';

        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-layout-scaler.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-metro-render.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-image-render.js') . '"></script>';

        $javaScriptContent .= '<script type="text/javascript">';
        $javaScriptContent .= '   var options = ' . json_encode($options) . ';';
        $javaScriptContent .= '   var items = ' . json_encode($items) . ';';
        $javaScriptContent .= '   var colors = ' . json_encode($colorArray) . ';';
        $javaScriptContent .= '   $(document).ready(function() { ';
        $javaScriptContent .= '       $("body").xiboLayoutScaler(options); $("#content").xiboMetroRender(options, items, colors); $("#content").find("img").xiboImageRender(options); $("#content").find(".cell").xiboImageRender(options);';
        $javaScriptContent .= '   }); ';
        $javaScriptContent .= '</script>';

        // Replace the Head Content with our generated javascript
        $data['javaScript'] = $javaScriptContent;

        return $this->renderTemplate($data);
    }

    /** @inheritdoc */
    public function isValid()
    {
        return self::$STATUS_VALID;
    }

    /**
     * @param $tweet
     * @return bool
     */
    private function tweetHasPhoto($tweet)
    {
        return ((isset($tweet->entities->media)
                && count($tweet->entities->media) > 0)
            || (isset($tweet->retweeted_status->entities->media)
                && count($tweet->retweeted_status->entities->media) > 0));
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
            return $this->getWidgetId() . (($displayId === 0) ? '_0' : '');
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
