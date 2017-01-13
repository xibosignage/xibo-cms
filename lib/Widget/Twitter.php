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
use Xibo\Exception\ConfigurationException;
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
        $this->resourceFolder = PROJECT_ROOT . '/web/modules/twitter';

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
            $module->imageUri = 'forms/library.gif';
            $module->enabled = 1;
            $module->previewEnabled = 1;
            $module->assignable = 1;
            $module->regionSpecific = 1;
            $module->renderAs = 'html';
            $module->schemaVersion = $this->codeSchemaVersion;
            $module->defaultDuration = 60;
            $module->settings = [];

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
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/web/modules/vendor/jquery-1.11.1.min.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/web/modules/xibo-text-render.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/web/modules/xibo-layout-scaler.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/web/modules/emojione/emojione.sprites.svg')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/web/modules/vendor/bootstrap.min.css')->save();
        
        foreach ($this->mediaFactory->createModuleFileFromFolder($this->resourceFolder) as $media) {
            /* @var Media $media */
            $media->save();
        }
    }

    /**
     * Loads templates for this module
     */
    private function loadTemplates()
    {
        $this->module->settings['templates'] = [];

        // Scan the folder for template files
        foreach (glob(PROJECT_ROOT . '/modules/twitter/*.template.json') as $template) {
            // Read the contents, json_decode and add to the array
            $this->module->settings['templates'][] = json_decode(file_get_contents($template), true);
        }

        $this->getLog()->debug(count($this->module->settings['templates']));
    }

    /**
     * Templates available
     * @return array
     */
    public function templatesAvailable()
    {
        if (!isset($this->module->settings['templates']))
            $this->loadTemplates();

        return $this->module->settings['templates'];
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
     */
    public function settings()
    {
        // Process any module settings you asked for.
        $apiKey = $this->getSanitizer()->getString('apiKey');

        if ($apiKey == '')
            throw new \InvalidArgumentException(__('Missing API Key'));

        // Process any module settings you asked for.
        $apiSecret = $this->getSanitizer()->getString('apiSecret');

        if ($apiSecret == '')
            throw new \InvalidArgumentException(__('Missing API Secret'));

        $this->module->settings['apiKey'] = $apiKey;
        $this->module->settings['apiSecret'] = $apiSecret;
        $this->module->settings['cachePeriod'] = $this->getSanitizer()->getInt('cachePeriod', 300);
        $this->module->settings['cachePeriodImages'] = $this->getSanitizer()->getInt('cachePeriodImages', 24);

        // Return an array of the processed settings.
        return $this->module->settings;
    }

    public function validate()
    {
        if ($this->getUseDuration() == 1 && $this->getDuration() == 0)
            throw new \InvalidArgumentException(__('Please enter a duration'));

        if (!v::string()->notEmpty()->validate($this->getOption('searchTerm')))
            throw new \InvalidArgumentException(__('Please enter a search term'));
    }

    /**
     * Add Media
     */
    public function add()
    {
        $this->setCommonOptions();

        // Save the widget
        $this->validate();
        $this->saveWidget();
    }

    /**
     * Edit Media
     */
    public function edit()
    {
        $this->setCommonOptions();

        // Save the widget
        $this->validate();
        $this->saveWidget();
    }

    /**
     * Set common options from Request Params
     */
    private function setCommonOptions()
    {
        $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setOption('name', $this->getSanitizer()->getString('name'));
        $this->setOption('searchTerm', $this->getSanitizer()->getString('searchTerm'));
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
        $this->setOption('widgetOriginalPadding', $this->getSanitizer()->getInt('widgetOriginalPadding'));
        $this->setOption('widgetOriginalWidth', $this->getSanitizer()->getInt('widgetOriginalWidth'));
        $this->setOption('widgetOriginalHeight', $this->getSanitizer()->getInt('widgetOriginalHeight'));
        $this->setOption('resultContent', $this->getSanitizer()->getString('resultContent'));
        $this->setRawNode('template', $this->getSanitizer()->getParam('ta_text', $this->getSanitizer()->getParam('template', null)));
        $this->setRawNode('styleSheet', $this->getSanitizer()->getParam('ta_css', $this->getSanitizer()->getParam('styleSheet', null)));
        $this->setRawNode('javaScript', $this->getSanitizer()->getParam('javaScript', ''));
    }

    /**
     * @param int $displayId
     * @param bool $isPreview
     * @return array
     * @throws ConfigurationException
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
                $defaultLat = $this->getConfig()->GetSetting('DEFAULT_LAT');
                $defaultLong = $this->getConfig()->GetSetting('DEFAULT_LONG');
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
        
        // Connect to twitter and get the twitter feed.
        $cache = $this->getPool()->getItem(md5($searchTerm . $this->getOption('resultType') . $this->getOption('tweetCount', 15) . $geoCode));

        $data = $cache->get();

        if ($cache->isMiss()) {

            $this->getLog()->debug('Querying API for ' . $searchTerm);

            // We need to search for it
            if (!$token = $this->getToken())
                return false;

            // We have the token, make a tweet
            if (!$data = $this->searchApi($token, $searchTerm, $this->getOption('resultType'), $geoCode, $this->getOption('tweetCount', 15)))
                return false;

            // Cache it
            $cache->set($data);
            $cache->expiresAfter($this->getSetting('cachePeriod', 3600));
            $this->getPool()->saveDeferred($cache);
        }

        // Get the template
        $template = $this->parseLibraryReferences($isPreview, $this->getRawNode('template', null));

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
        $emoji->imageType = 'svg';
        $emoji->sprites = true;
        $emoji->imagePathSVGSprites = $this->getResourceUrl('emojione/emojione.sprites.svg');

        // Get the date format to apply
        $dateFormat = $this->getOption('dateFormat', $this->getConfig()->GetSetting('DATE_FORMAT'));

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
                        $replace = $tweet->user->name;
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

                            // Tag this layout with this file
                            $this->assignMedia($file->mediaId);

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

                                // Tag this layout with this file
                                $this->assignMedia($file->mediaId);

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

        // Process the download queue
        $this->mediaFactory->processDownloads();

        // Return the data array
        return $return;
    }

    /**
     * Get Resource
     * @param int $displayId
     * @return mixed
     */
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

        // Generate a JSON string of substituted items.
        $items = $this->getTwitterFeed($displayId, $isPreview);

        // Return empty string if there are no items to show.
        if (count($items) == 0)
            return '';

        $options = array(
            'type' => $this->getModuleType(),
            'fx' => $this->getOption('effect', 'noAnim'),
            'speed' => $this->getOption('speed', 500),
            'duration' => $duration,
            'durationIsPerItem' => ($this->getOption('durationIsPerItem', 0) == 1),
            'numItems' => count($items),
            'originalWidth' => $this->region->width,
            'originalHeight' => $this->region->height,
            'previewWidth' => $this->getSanitizer()->getDouble('width', 0),
            'previewHeight' => $this->getSanitizer()->getDouble('height', 0),
            'scaleOverride' => $this->getSanitizer()->getDouble('scale_override', 0),
            'widgetDesignPadding' => $this->getSanitizer()->int($this->getOption('widgetOriginalPadding')),
            'widgetDesignWidth' => $this->getSanitizer()->int($this->getOption('widgetOriginalWidth')),
            'widgetDesignHeight'=> $this->getSanitizer()->int($this->getOption('widgetOriginalHeight')),
            'resultContent'=> $this->getSanitizer()->string($this->getOption('resultContent')),
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

        $backgroundColor = $this->getOption('backgroundColor');
        if ($backgroundColor != '') {
            $headContent .= '<style type="text/css">body, .page, .item { background-color: ' . $backgroundColor . ' }</style>';
        }

        // Add our fonts.css file
        $headContent .= '<link href="' . (($isPreview) ? $this->getApp()->urlFor('library.font.css') : 'fonts.css') . '" rel="stylesheet" media="screen">
        <link href="' . $this->getResourceUrl('vendor/bootstrap.min.css')  . '" rel="stylesheet" media="screen">';
        
        // Add the CSS if it isn't empty
        $css = $this->getRawNode('styleSheet', null);
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

        $javaScriptContent .= '<script type="text/javascript">';
        $javaScriptContent .= '   var options = ' . json_encode($options) . ';';
        $javaScriptContent .= '   var items = ' . json_encode($items) . ';';
        $javaScriptContent .= '   $(document).ready(function() { ';
        $javaScriptContent .= '       $("body").xiboLayoutScaler(options); $("#content").xiboTextRender(options, items); ';
        $javaScriptContent .= '   }); ';
        $javaScriptContent .= $javaScript;
        $javaScriptContent .= '</script>';

        // Replace the Head Content with our generated javascript
        $data['javaScript'] = $javaScriptContent;

        // Update and save widget if we've changed our assignments.
        if ($this->hasMediaChanged())
            $this->widget->save(['saveWidgetOptions' => false, 'notifyDisplays' => true]);

        return $this->renderTemplate($data);
    }

    public function isValid()
    {
        // Using the information you have in your module calculate whether it is valid or not.
        // 0 = Invalid
        // 1 = Valid
        // 2 = Unknown
        return 1;
    }
}
