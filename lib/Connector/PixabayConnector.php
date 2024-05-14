<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
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

namespace Xibo\Connector;

use Carbon\Carbon;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Entity\SearchResult;
use Xibo\Event\LibraryProviderEvent;
use Xibo\Event\LibraryProviderImportEvent;
use Xibo\Event\LibraryProviderListEvent;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Pixabay Connector
 *  This connector acts as a data provider for the Media Toolbar in the Layout/Playlist editor user interface
 */
class PixabayConnector implements ConnectorInterface
{
    use ConnectorTrait;

    public function registerWithDispatcher(EventDispatcherInterface $dispatcher): ConnectorInterface
    {
        $dispatcher->addListener('connector.provider.library', [$this, 'onLibraryProvider']);
        $dispatcher->addListener('connector.provider.library.import', [$this, 'onLibraryImport']);
        $dispatcher->addListener('connector.provider.library.list', [$this, 'onLibraryList']);
        return $this;
    }

    public function getSourceName(): string
    {
        return 'pixabay';
    }

    public function getTitle(): string
    {
        return 'Pixabay';
    }

    public function getDescription(): string
    {
        return 'Show Pixabay images and videos in the Layout editor toolbar and download them to the library for use on your Layouts.';
    }

    public function getThumbnail(): string
    {
        return 'theme/default/img/connectors/pixabay_square_green.png';
    }

    public function getSettingsFormTwig(): string
    {
        return 'pixabay-form-settings';
    }

    public function processSettingsForm(SanitizerInterface $params, array $settings): array
    {
        if (!$this->isProviderSetting('apiKey')) {
            $settings['apiKey'] = $params->getString('apiKey');
        }
        return $settings;
    }

    /**
     * @param \Xibo\Event\LibraryProviderEvent $event
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function onLibraryProvider(LibraryProviderEvent $event)
    {
        $this->getLogger()->debug('onLibraryProvider');

        // Do we have an alternative URL (we may proxy requests for cache)
        $baseUrl = $this->getSetting('baseUrl');
        if (empty($baseUrl)) {
            $baseUrl = 'https://pixabay.com/api/';
        }

        // Do we have an API key?
        $apiKey = $this->getSetting('apiKey');
        if (empty($apiKey)) {
            $this->getLogger()->debug('onLibraryProvider: No api key');
            return;
        }

        // was Pixabay requested?
        if ($event->getProviderName() === $this->getSourceName()) {
            // We do! Let's get some results from Pixabay
            // first we look at paging
            $start = $event->getStart();
            $perPage = $event->getLength();
            if ($start == 0) {
                $page = 1;
            } else {
                $page = floor($start / $perPage) + 1;
            }

            $query = [
                'key' => $apiKey,
                'page' => $page,
                'per_page' => $perPage,
                'safesearch' => 'true'
            ];

            // Now we handle any other search
            if ($event->getOrientation() === 'landscape') {
                $query['orientation'] = 'horizontal';
            } else if ($event->getOrientation() === 'portrait') {
                $query['orientation'] = 'vertical';
            }

            if (!empty($event->getSearch())) {
                $query['q'] = urlencode($event->getSearch());
            }

            // Pixabay either returns images or videos, not both.
            if (count($event->getTypes()) !== 1) {
                return;
            }

            $type = $event->getTypes()[0];
            if (!in_array($type, ['image', 'video'])) {
                return;
            }

            // Pixabay require a 24-hour cache of each result set.
            $key = md5($type . '_' . json_encode($query));
            $cache = $this->getPool()->getItem($key);
            $body = $cache->get();

            if ($cache->isMiss()) {
                $this->getLogger()->debug('onLibraryProvider: cache miss, generating.');

                // Make the request
                $request = $this->getClient()->request('GET', $baseUrl . ($type === 'video' ? 'videos' : ''), [
                    'query' => $query
                ]);

                $body = $request->getBody()->getContents();
                if (empty($body)) {
                    $this->getLogger()->debug('onLibraryProvider: Empty body');
                    return;
                }

                $body = json_decode($body);
                if ($body === null || $body === false) {
                    $this->getLogger()->debug('onLibraryProvider: non-json body or empty body returned.');
                    return;
                }

                // Cache for next time
                $cache->set($body);
                $cache->expiresAt(Carbon::now()->addHours(24));
                $this->getPool()->saveDeferred($cache);
            } else {
                $this->getLogger()->debug('onLibraryProvider: serving from cache.');
            }

            $providerDetails = new ProviderDetails();
            $providerDetails->id = 'pixabay';
            $providerDetails->link = 'https://pixabay.com';
            $providerDetails->logoUrl = '/theme/default/img/connectors/pixabay_logo.svg';
            $providerDetails->iconUrl = '/theme/default/img/connectors/pixabay_logo_square.svg';
            $providerDetails->backgroundColor = '';

            // Process each hit into a search result and add it to the overall results we've been given.
            foreach ($body->hits as $result) {
                $searchResult = new SearchResult();
                $searchResult->source = $this->getSourceName();
                $searchResult->id = $result->id;
                $searchResult->title = $result->tags;
                $searchResult->provider = $providerDetails;

                if ($type === 'video') {
                    $searchResult->type = 'video';
                    $searchResult->thumbnail = $result->videos->tiny->url;
                    $searchResult->duration = $result->duration;
                    if (!empty($result->videos->large)) {
                        $searchResult->download = $result->videos->large->url;
                        $searchResult->width = $result->videos->large->width;
                        $searchResult->height = $result->videos->large->height;
                        $searchResult->fileSize = $result->videos->large->size;
                    } else if (!empty($result->videos->medium)) {
                        $searchResult->download = $result->videos->medium->url;
                        $searchResult->width = $result->videos->medium->width;
                        $searchResult->height = $result->videos->medium->height;
                        $searchResult->fileSize = $result->videos->medium->size;
                    } else if (!empty($result->videos->small)) {
                        $searchResult->download = $result->videos->small->url;
                        $searchResult->width = $result->videos->small->width;
                        $searchResult->height = $result->videos->small->height;
                        $searchResult->fileSize = $result->videos->small->size;
                    } else {
                        $searchResult->download = $result->videos->tiny->url;
                        $searchResult->width = $result->videos->tiny->width;
                        $searchResult->height = $result->videos->tiny->height;
                        $searchResult->fileSize = $result->videos->tiny->size;
                    }

                    if (!empty($result->picture_id ?? null)) {
                        // Try the old way (at some point this stopped working and went to the thumbnail approach above
                        $searchResult->videoThumbnailUrl = str_replace(
                            'pictureId',
                            $result->picture_id,
                            'https://i.vimeocdn.com/video/pictureId_960x540.png'
                        );
                    } else {
                        // Use the medium thumbnail if we have it, otherwise the tiny one.
                        $searchResult->videoThumbnailUrl = $result->videos->medium->thumbnail
                            ?? $result->videos->tiny->thumbnail;
                    }
                } else {
                    $searchResult->type = 'image';
                    $searchResult->thumbnail = $result->previewURL;
                    $searchResult->download = $result->fullHDURL ?? $result->largeImageURL;
                    $searchResult->width = $result->imageWidth;
                    $searchResult->height = $result->imageHeight;
                    $searchResult->fileSize = $result->imageSize;
                }
                $event->addResult($searchResult);
            }
        }
    }

    /**
     * @param \Xibo\Event\LibraryProviderImportEvent $event
     */
    public function onLibraryImport(LibraryProviderImportEvent $event)
    {
        foreach ($event->getItems() as $providerImport) {
            if ($providerImport->searchResult->provider->id === $this->getSourceName()) {
                // Configure this import, setting the URL, etc.
                $providerImport->configureDownload();
            }
        }
    }

    public function onLibraryList(LibraryProviderListEvent $event)
    {
        $this->getLogger()->debug('onLibraryList:event');

        if (empty($this->getSetting('apiKey'))) {
            $this->getLogger()->debug('onLibraryList: No api key');
            return;
        }

        $providerDetails = new ProviderDetails();
        $providerDetails->id = 'pixabay';
        $providerDetails->link = 'https://pixabay.com';
        $providerDetails->logoUrl = '/theme/default/img/connectors/pixabay_logo.svg';
        $providerDetails->iconUrl = '/theme/default/img/connectors/pixabay_logo_square.svg';
        $providerDetails->backgroundColor = '';
        $providerDetails->mediaTypes = ['image', 'video'];

        $event->addProvider($providerDetails);
    }
}
