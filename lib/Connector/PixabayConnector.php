<?php
/*
 * Copyright (C) 2021 Xibo Signage Ltd
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
use GuzzleHttp\Client;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Entity\SearchResult;
use Xibo\Event\LibraryProviderEvent;
use Xibo\Event\LibraryProviderImportEvent;

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
        return $this;
    }

    public function getSourceName(): string
    {
        return 'pixabay';
    }

    /**
     * @param \Xibo\Event\LibraryProviderEvent $event
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function onLibraryProvider(LibraryProviderEvent $event)
    {
        $this->getLogger()->debug('onLibraryProvider');

        // Do we have an API key?
        $apiKey = $this->getSetting('apiKey');
        if (empty($apiKey)) {
            $this->getLogger()->debug('onLibraryProvider: No api key');
            return;
        }

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
            'per_page' => $perPage
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
            $client = new Client();
            $uri = $type === 'video' ? 'https://pixabay.com/api/videos' : 'https://pixabay.com/api/';
            $request = $client->request('GET', $uri, [
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
        $providerDetails->backgroundColor = '';

        // Process each hit into a search result and add it to the overall results we've been given.
        foreach ($body->hits as $result) {
            $searchResult = new SearchResult();
            // TODO: add more info
            $searchResult->source = $this->getSourceName();
            $searchResult->id = $result->id;
            $searchResult->title = $result->tags;
            $searchResult->provider = $providerDetails;

            if ($type === 'video') {
                $searchResult->type = 'video';
                $searchResult->thumbnail = $result->videos->tiny->url;
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
}

