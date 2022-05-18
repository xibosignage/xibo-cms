<?php
/*
 * Copyright (C) 2022 Xibo Signage Ltd
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

namespace Xibo\Connector;

use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Event\WidgetDataRequestEvent;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Sanitizer\SanitizerInterface;
use Xibo\Widget\DataType\SocialMedia;
use Xibo\Widget\Provider\DataProviderInterface;

/**
 * A connector to get data from the Twitter API for use by the Twitter Widget
 */
class TwitterConnector implements ConnectorInterface
{
    use ConnectorTrait;

    public function registerWithDispatcher(EventDispatcherInterface $dispatcher): ConnectorInterface
    {
        $dispatcher->addListener(WidgetDataRequestEvent::$NAME, [$this, 'onDataRequest']);
        return $this;
    }

    public function getSourceName(): string
    {
        return 'twitter';
    }

    public function getTitle(): string
    {
        return 'Twitter';
    }

    public function getDescription(): string
    {
        return 'Display Tweets';
    }

    public function getThumbnail(): string
    {
        return '';
    }

    public function getSettingsFormTwig(): string
    {
        return 'twitter-form-settings';
    }

    public function processSettingsForm(SanitizerInterface $params, array $settings): array
    {
        $settings['delegated'] = $params->getCheckbox('delegated');
        $settings['apiKey'] = $params->getString('apiKey');
        $settings['apiSecret'] = $params->getString('apiSecret');
        $settings['cachePeriod'] = $params->getInt('cachePeriod');
        $settings['cachePeriodImages'] = $params->getInt('cachePeriodImages');
        return $settings;
    }

    public function onDataRequest(WidgetDataRequestEvent $event)
    {
        if ($event->getDataProvider()->getDataSource() === 'twitter') {
            if (empty($this->getSetting('apiKey')) || empty($this->getSetting('apiSecret'))) {
                $this->getLogger()->debug('onDataRequest: twitter not configured.');
                return;
            }

            $delegated = $this->getSetting('delegated');
            if ($delegated && empty($this->getSetting('oAuthToken'))) {
                $this->getLogger()->debug('onDataRequest: twitter not configured.');
                return;
            }

            // Handle this event.
            $event->stopPropagation();

            // Expiry time for any media that is downloaded
            $expires = Carbon::now()->addHours($this->getSetting('cachePeriodImages', 24))->format('U');

            try {
                $dataProvider = $event->getDataProvider();
                foreach ($this->getFeed($dataProvider) as $item) {
                    // Parse the tweet
                    $tweet = new SocialMedia();

                    // Get the tweet text to operate on
                    // if it is a retweet we need to take the full_text in a different way
                    if (isset($item['retweeted_status'])) {
                        $tweet->text = 'RT @' . $item['retweeted_status']['user']['screen_name']
                            . ': ' . $item['retweeted_status']['full_text'];
                    } else {
                        $tweet->text = $item['full_text'];
                    }

                    // Replace URLs with their display_url before removal
                    if (isset($item['entities']['urls'])) {
                        foreach ($item['entities']['urls'] as $url) {
                            $tweet->text = str_replace($url['url'], $url['display_url'], $tweet->text);
                        }
                    }

                    $tweet->user = $item['user']['name'];
                    $tweet->screenName = $item['user']['screen_name'] != '' ? '@' . $item['user']['screen_name'] : '';
                    $tweet->date = $item['created_at'];
                    $tweet->location = $item['user']['location'];

                    // Profile image
                    if (!empty($item['user']['profile_image_url'])) {
                        $id = $item['user']['id_str'] ?: $item['user']['id'];

                        // Original Default Image
                        $tweet->userProfileImage = $dataProvider->addImage(
                            $id,
                            $item['user']['profile_image_url'],
                            $expires
                        );

                        // Mini image
                        $url = str_replace('_normal', '_mini', $item['user']['profile_image_url']);
                        $tweet->userProfileImageMini = $dataProvider->addImage($id . '_mini', $url, $expires);
                    }

                    // Photo
                    // See if there are any photos associated with this tweet.
                    if ((isset($item['entities']['media']) && count($item['entities']['media']) > 0)
                        || (isset($item['retweeted_status']['entities']['media'])
                            && count($item['retweeted_status']['entities']['media']) > 0)
                    ) {
                        // See if it's an image from a tweet or RT, and only take the first one
                        $mediaObject = (isset($item['entities']['media']))
                            ? $item['entities']['media'][0]
                            : $item['retweeted_status']['entities']['media'][0];

                        $photoUrl = $mediaObject['media_url'];
                        if (!empty($photoUrl)) {
                            $tweet->photo = $dataProvider->addImage($mediaObject->id_str, $photoUrl, $expires);
                        }
                    }

                    $event->getDataProvider()->addItem($tweet);
                }

                // If we've got data, then set our cache period.
                $event->getDataProvider()->setCacheTtl($this->getSetting('cachePeriod', 3600));
            } catch (\Exception $exception) {
                $this->getLogger()->error('onDataRequest: Failed to get feed. e = ' . $exception->getMessage());
            }
        }
    }

    /**
     * @param \Xibo\Widget\Provider\DataProviderInterface $dataProvider
     * @return array
     * @throws \Xibo\Support\Exception\AccessDeniedException
     */
    private function getFeed(DataProviderInterface $dataProvider): array
    {
        // TODO: delegated access - user needs to have authenticated.

        // Append filters to the search term.
        $searchTerm = $dataProvider->getProperty('searchTerm', '');
        if ($searchTerm == 1) {
            $searchTerm .= ' -filter:media';
        } else if ($searchTerm == 2) {
            $searchTerm .= ' filter:twimg';
        }

        $query = [
            'q' => trim($searchTerm),
            'result_type' => $dataProvider->getProperty('resultType', 'mixed'),
            'count' => $dataProvider->getProperty('tweetCount', 15),
            'include_entities' => true,
            'tweet_mode' => 'extended'
        ];

        if (!empty($dataProvider->getProperty('language'))) {
            $query['lang'] = $dataProvider->getProperty('language');
        }

        // Do we need to do geo?
        $distance = $dataProvider->getProperty('tweetDistance', 0);
        if ($distance > 0) {
            $query['geocode'] = implode(
                ',',
                [
                    $dataProvider->getDisplayLatitude(),
                    $dataProvider->getDisplayLongitude(),
                    $distance
                ]
            ) . 'mi';
        }

        // Search
        try {
            $request = $this->getClient()->request('GET', 'https://api.twitter.com/1.1/search/tweets.json', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getToken(
                        $this->getSetting('apiKey'),
                        $this->getSetting('apiSecret')
                    )
                ],
                'query' => $query
            ]);

            $body = $request->getBody()->getContents();
            $this->getLogger()->debug($body);
            $body = json_decode($body, true);
            if (isset($body['statuses'])) {
                return $body['statuses'];
            }
        } catch (RequestException $requestException) {
            $this->getLogger()->error('Unable to reach twitter api. ' . $requestException->getMessage());
        } catch (GuzzleException $exception) {
            $this->getLogger()->error('Unable to reach twitter api. ' . $exception->getMessage());
        }
        return [];
    }

    /**
     * Get an auth token
     * @param string $apiKey
     * @param string $apiSecret
     * @return string
     * @throws \Xibo\Support\Exception\AccessDeniedException
     */
    private function getToken(string $apiKey, string $apiSecret): string
    {
        // Prepare the consumer key and secret
        $key = base64_encode(urlencode($apiKey) . ':' . urlencode($apiSecret));

        // Check to see if we have the bearer token already cached
        $cache = $this->getPool()->getItem('connector/bearer_' . md5($key));
        $token = $cache->get();

        if ($cache->isHit()) {
            $this->getLogger()->debug('Bearer Token served from cache');
            return $token;
        }

        // We can take up to 30 seconds to request a new token
        $cache->lock(30);

        try {
            $response = $this->getClient()->request('POST', 'https://api.twitter.com/oauth2/token', [
                'form_params' => [
                    'grant_type' => 'client_credentials'
                ],
                'headers' => [
                    'Authorization' => 'Basic ' . $key
                ]
            ]);

            $result = json_decode($response->getBody()->getContents());

            if ($result->token_type !== 'bearer') {
                $this->getLogger()->error('Twitter API returned OK, but without a bearer token. '
                    . var_export($result, true));
                throw new AccessDeniedException(__('Twitter is not authenticated'));
            }

            // It is, so lets cache it
            // long times...
            $cache->set($result->access_token);
            $cache->expiresAfter(100000);
            $this->getPool()->saveDeferred($cache);

            return $result->access_token;
        } catch (RequestException $requestException) {
            $this->getLogger()->error('Twitter API returned ' . $requestException->getMessage()
                . ' status. Unable to proceed.');
            throw new AccessDeniedException(__('Twitter is not authenticated'));
        } catch (GuzzleException $exception) {
            throw new AccessDeniedException(__('Twitter is not authenticated'));
        }
    }
}
