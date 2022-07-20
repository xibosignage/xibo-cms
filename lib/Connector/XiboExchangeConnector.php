<?php
/*
 * Copyright (c) 2022 Xibo Signage Ltd
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
use Illuminate\Support\Str;
use Parsedown;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Entity\SearchResult;
use Xibo\Event\TemplateProviderEvent;
use Xibo\Event\TemplateProviderImportEvent;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * XiboExchangeConnector
 * ---------------------
 * This connector will consume the Xibo Layout Exchange API and offer pre-built templates for selection when adding
 * a new layout.
 */
class XiboExchangeConnector implements ConnectorInterface
{
    use ConnectorTrait;

    /**
     * @param EventDispatcherInterface $dispatcher
     * @return ConnectorInterface
     */
    public function registerWithDispatcher(EventDispatcherInterface $dispatcher): ConnectorInterface
    {
        $dispatcher->addListener('connector.provider.template', [$this, 'onTemplateProvider']);
        $dispatcher->addListener('connector.provider.template.import', [$this, 'onTemplateProviderImport']);
        return $this;
    }

    public function getSourceName(): string
    {
        return 'xibo-exchange';
    }

    public function getTitle(): string
    {
        return 'Xibo Exchange';
    }

    public function getDescription(): string
    {
        return 'Show Templates provided by the Xibo Exchange in the add new Layout form.';
    }

    public function getThumbnail(): string
    {
        return 'theme/default/img/connectors/xibo-exchange.png';
    }

    public function getSettingsFormTwig(): string
    {
        return 'connector-form-edit';
    }

    public function processSettingsForm(SanitizerInterface $params, array $settings): array
    {
        return $settings;
    }

    /**
     * Get layouts available in Layout exchange and add them to the results
     * This is triggered in Template Controller search function
     * @param TemplateProviderEvent $event
     */
    public function onTemplateProvider(TemplateProviderEvent $event)
    {
        $this->getLogger()->debug('XiboExchangeConnector: onTemplateProvider');

        // Get a cache of the layouts.json file, or request one from download.
        $uri = 'https://download.xibosignage.com/layouts.json';
        $key = md5($uri);
        $cache = $this->getPool()->getItem($key);
        $body = $cache->get();

        if ($cache->isMiss()) {
            $this->getLogger()->debug('onTemplateProvider: cache miss, generating.');

            // Make the request
            $request = $this->getClient()->request('GET', $uri);

            $body = $request->getBody()->getContents();
            if (empty($body)) {
                $this->getLogger()->debug('onTemplateProvider: Empty body');
                return;
            }

            $body = json_decode($body);
            if ($body === null || $body === false) {
                $this->getLogger()->debug('onTemplateProvider: non-json body or empty body returned.');
                return;
            }

            // Cache for next time
            $cache->set($body);
            $cache->expiresAt(Carbon::now()->addHours(24));
            $this->getPool()->saveDeferred($cache);
        } else {
            $this->getLogger()->debug('onTemplateProvider: serving from cache.');
        }

        // We have the whole file locally, so handle paging
        $start = $event->getStart();
        $perPage = $event->getLength();

        // Create a provider to add to each search result
        $providerDetails = new ProviderDetails();
        $providerDetails->id = $this->getSourceName();
        $providerDetails->logoUrl = $this->getThumbnail();
        $providerDetails->message = $this->getTitle();
        $providerDetails->backgroundColor = '';

        // Filter the body based on search param.
        if (!empty($event->getSearch())) {
            $filtered = [];
            foreach ($body as $template) {
                if (Str::contains($template->title, $event->getSearch())) {
                    $filtered[] = $template;
                    continue;
                }

                if (!empty($template->description) && Str::contains($template->description, $event->getSearch())) {
                    $filtered[] = $template;
                    continue;
                }

                if (property_exists($template, 'tags') && count($template->tags) > 0) {
                    if (in_array($event->getSearch(), $template->tags)) {
                        $filtered[] = $template;
                    }
                }
            }
        } else {
            $filtered = $body;
        }

        for ($i = $start; $i < ($start + $perPage - 1) && $i < count($filtered); $i++) {
            $searchResult = $this->createSearchResult($filtered[$i]);
            $searchResult->provider = $providerDetails;
            $event->addResult($searchResult);
        }
    }

    /**
     * When remote source Template is selected on Layout add,
     * we need to get the zip file from specified url and import it to the CMS
     * imported Layout object is set on the Event and retrieved later in Layout controller
     * @param TemplateProviderImportEvent $event
     */
    public function onTemplateProviderImport(TemplateProviderImportEvent $event)
    {
        $downloadUrl = $event->getDownloadUrl();
        $client = new Client();
        $tempFile = $event->getLibraryLocation() . 'temp/' . $event->getFileName();
        $client->request('GET', $downloadUrl, ['sink' => $tempFile]);
        $event->setFilePath($tempFile);
    }

    /**
     * @param $template
     * @return SearchResult
     */
    private function createSearchResult($template) : SearchResult
    {
        $searchResult = new SearchResult();
        $searchResult->id = $template->fileName;
        $searchResult->source = 'remote';
        $searchResult->title = $template->title;
        $searchResult->description = empty($template->description)
            ? null
            : Parsedown::instance()->line($template->description);

        // Optional data
        if (property_exists($template, 'tags') && count($template->tags) > 0) {
            $searchResult->tags = $template->tags;
        }

        if (property_exists($template, 'orientation')) {
            $searchResult->orientation = $template->orientation;
        }

        // Thumbnail
        $searchResult->thumbnail = $template->thumbnailUrl;
        $searchResult->download = $template->downloadUrl;
        return $searchResult;
    }
}
