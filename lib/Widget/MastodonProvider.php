<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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

use Carbon\Carbon;
use GuzzleHttp\Exception\RequestException;
use Xibo\Support\Exception\ConfigurationException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Widget\DataType\SocialMedia;
use Xibo\Widget\Provider\DataProviderInterface;
use Xibo\Widget\Provider\DurationProviderInterface;
use Xibo\Widget\Provider\WidgetProviderInterface;
use Xibo\Widget\Provider\WidgetProviderTrait;

/**
 * Downloads a Mastodon feed and returns SocialMedia data types
 */
class MastodonProvider implements WidgetProviderInterface
{
    use WidgetProviderTrait;

    public function fetchData(DataProviderInterface $dataProvider): WidgetProviderInterface
    {
        $uri = $dataProvider->getSetting('defaultServerUrl', 'https://mastodon.social');

        try {
            $httpOptions = [
                'timeout' => 20, // wait no more than 20 seconds
                'query' => [
                    'limit' => $dataProvider->getProperty('numItems', 15)
                ]
            ];

            if ($dataProvider->getProperty('searchOn', 'all') === 'local') {
                $httpOptions['query']['local'] = true;
            } elseif ($dataProvider->getProperty('searchOn', 'all') === 'remote') {
                $httpOptions['query']['remote'] = true;
            }

            // Media Only
            if ($dataProvider->getProperty('onlyMedia', 0)) {
                $httpOptions['query']['only_media'] = true;
            }

            if (!empty($dataProvider->getProperty('serverUrl', ''))) {
                $uri = $dataProvider->getProperty('serverUrl', '');
            }

            // Hashtag: When empty we should do a public search, when filled we should do a hashtag search
            $hashtag = trim($dataProvider->getProperty('hashtag', ''));
            if (!empty($hashtag)) {
                $uri = rtrim($uri, '/').'/api/v1/timelines/tag/'. trim($hashtag, '#');
            } else {
                $uri = rtrim($uri, '/').'/api/v1/timelines/public';
            }

            $this->getLog()->debug('Mastodon: uri: ' . $uri . ' httpOptions: '. json_encode($httpOptions));

            $response = $dataProvider
                ->getGuzzleClient($httpOptions)
                ->get($uri);

            $result = json_decode($response->getBody()->getContents(), true);

            $this->getLog()->debug('Mastodon: count: ' . count($result));

            // Expiry time for any media that is downloaded
            $expires = Carbon::now()->addHours($dataProvider->getSetting('cachePeriodImages', 24))->format('U');

            foreach ($result as $item) {
                // Parse the mastodon
                $mastodon = new SocialMedia();

                $mastodon->text = strip_tags($item['content']);
                $mastodon->user = $item['account']['acct'];
                $mastodon->screenName = $item['account']['display_name'];
                $mastodon->date = $item['created_at'];

                // Original Default Image
                $mastodon->userProfileImage = $dataProvider->addImage(
                    'mastodon_' . $item['account']['id'],
                    $item['account']['avatar'],
                    $expires
                );

                // Mini image
                $mastodon->userProfileImageMini = $mastodon->userProfileImage;

                // Bigger image
                $mastodon->userProfileImageBigger = $mastodon->userProfileImage;


                // Photo
                // See if there are any photos associated with this status.
                if ((isset($item['media_attachments']) && count($item['media_attachments']) > 0)) {
                    // only take the first one
                    $mediaObject = $item['media_attachments'][0];

                    $photoUrl = $mediaObject['preview_url'];
                    if (!empty($photoUrl)) {
                        $mastodon->photo = $dataProvider->addImage(
                            'mastodon_' . $mediaObject['id'],
                            $photoUrl,
                            $expires
                        );
                    }
                }

                // Add the mastodon topic.
                $dataProvider->addItem($mastodon);
            }

            // If we've got data, then set our cache period.
            $dataProvider->setCacheTtl($dataProvider->getSetting('cachePeriod', 3600));
        } catch (RequestException $requestException) {
            // Log and return empty?
            $this->getLog()->error('Mastodon: Unable to get posts: ' . $uri
                . ', e: ' . $requestException->getMessage());
            throw new ConfigurationException(__('Unable to download posts'));
        } catch (\Exception $exception) {
            // Log and return empty?
            $this->getLog()->error('Mastodon: ' . $exception->getMessage());
            $this->getLog()->debug($exception->getTraceAsString());
        }

        return $this;
    }

    public function fetchDuration(DurationProviderInterface $durationProvider): WidgetProviderInterface
    {
        return $this;
    }

    public function getDataCacheKey(DataProviderInterface $dataProvider): ?string
    {
        // No special cache key requirements.
        return null;
    }

    public function getDataModifiedDt(DataProviderInterface $dataProvider): ?Carbon
    {
        return null;
    }
}
