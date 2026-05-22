<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
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
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Str;
use Parsedown;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Entity\SearchResult;
use Xibo\Event\TemplateProviderEvent;
use Xibo\Event\TemplateProviderImportEvent;
use Xibo\Event\TemplateProviderListEvent;
use Xibo\Helper\Guzzle\SafeClient;
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
        $dispatcher->addListener('connector.provider.template.list', [$this, 'onTemplateList']);
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
     * Fetch the Xibo Exchange template list, using a 24-hour cache.
     * @return array
     * @throws GuzzleException
     */
    private function getTemplateList(): array
    {
        $uri = 'https://download.xibosignage.com/layouts_v4_1.json';
        $cache = $this->getPool()->getItem(md5($uri));
        $body = $cache->get();

        if ($cache->isMiss()) {
            $this->getLogger()->debug('getTemplateList: cache miss, fetching.');

            $request = $this->getClient()->request('GET', $uri);
            $body = $request->getBody()->getContents();
            if (empty($body)) {
                return [];
            }

            $body = json_decode($body);
            if ($body === null || $body === false) {
                return [];
            }

            $cache->set($body);
            $cache->expiresAt(Carbon::now()->addHours(24));
            $this->getPool()->saveDeferred($cache);
        } else {
            $this->getLogger()->debug('getTemplateList: serving from cache.');
        }

        return (array)$body;
    }

    /**
     * Get layouts available in Layout exchange and add them to the results
     * This is triggered in Template Controller search function
     * @param TemplateProviderEvent $event
     * @throws GuzzleException
     */
    public function onTemplateProvider(TemplateProviderEvent $event): void
    {
        $this->getLogger()->debug('XiboExchangeConnector: onTemplateProvider');

        $body = $this->getTemplateList();
        if (empty($body)) {
            return;
        }

        // We have the whole file locally, so handle paging
        $start = $event->getStart();
        $perPage = $event->getLength();

        // Create a provider to add to each search result
        $providerDetails = new ProviderDetails();
        $providerDetails->id = $this->getSourceName();
        $providerDetails->logoUrl = $this->getThumbnail();
        $providerDetails->iconUrl = $this->getThumbnail();
        $providerDetails->message = $this->getTitle();
        $providerDetails->backgroundColor = '';

        // parse the templates based on orientation filter.
        if (!empty($event->getOrientation())) {
            $templates = [];
            foreach ($body as $template) {
                if (!empty($template->orientation) &&
                    Str::contains($template->orientation, $event->getOrientation(), true)
                ) {
                    $templates[] = $template;
                }
            }
        } else {
            $templates = $body;
        }

        // Filter the body based on search param.
        if (!empty($event->getSearch())) {
            $filtered = [];
            foreach ($templates as $template) {
                if (Str::contains($template->title, $event->getSearch(), true)) {
                    $filtered[] = $template;
                    continue;
                }

                if (!empty($template->description) &&
                    Str::contains($template->description, $event->getSearch(), true)
                ) {
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
            $filtered = $templates;
        }

        // sort, featured first, otherwise alphabetically.
        usort($filtered, function ($a, $b) {
            if (property_exists($a, 'isFeatured') && property_exists($b, 'isFeatured')) {
                return $b->isFeatured <=> $a->isFeatured;
            } else {
                return $a->title <=> $b->title;
            }
        });

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
     * @throws GuzzleException
     */
    public function onTemplateProviderImport(TemplateProviderImportEvent $event): void
    {
        // Resolve the download URL server-side from the cached template list.
        // The client supplies only the templateId (fileName).
        $downloadUrl = null;
        foreach ($this->getTemplateList() as $template) {
            if (($template->fileName ?? null) === $event->getFileName()) {
                $downloadUrl = $template->downloadUrl ?? null;
                break;
            }
        }

        if (empty($downloadUrl)) {
            throw new \InvalidArgumentException('Template not found in Xibo Exchange: ' . $event->getFileName());
        }

        // Sanitize the filename and enforce a .zip extension.
        $safeFileName = basename($event->getFileName());
        if (!str_ends_with(strtolower($safeFileName), '.zip')) {
            $safeFileName .= '.zip';
        }

        $tempDir = rtrim($event->getLibraryLocation(), '/\\') . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR;
        $tempFile = $tempDir . $safeFileName;

        // Belt-and-suspenders: confirm the resolved path is still inside the temp directory.
        $realTempDir = realpath($tempDir);
        if ($realTempDir === false || !str_starts_with($tempFile, $realTempDir . DIRECTORY_SEPARATOR)) {
            throw new \InvalidArgumentException('Template file path escapes the allowed temporary directory');
        }

        $client = SafeClient::getSafeClient();
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
            : Parsedown::instance()->setSafeMode(true)->line($template->description);

        // Optional data
        if (property_exists($template, 'tags') && count($template->tags) > 0) {
            $searchResult->tags = $template->tags;
        }

        if (property_exists($template, 'orientation')) {
            $searchResult->orientation = $template->orientation;
        }

        if (property_exists($template, 'isFeatured')) {
            $searchResult->isFeatured = $template->isFeatured;
        }

        // Thumbnail
        $searchResult->thumbnail = $template->thumbnailUrl;
        $searchResult->download = $template->downloadUrl;
        return $searchResult;
    }

    /**
     * Add this connector to the list of providers.
     * @param \Xibo\Event\TemplateProviderListEvent $event
     * @return void
     */
    public function onTemplateList(TemplateProviderListEvent $event): void
    {
        $this->getLogger()->debug('onTemplateList:event');

        $providerDetails = new ProviderDetails();
        $providerDetails->id = $this->getSourceName();
        $providerDetails->link = 'https://xibosignage.com';
        $providerDetails->logoUrl = $this->getThumbnail();
        $providerDetails->iconUrl = 'exchange-alt';
        $providerDetails->message = $this->getTitle();
        $providerDetails->backgroundColor = '';
        $providerDetails->mediaTypes = ['xlf'];

        $event->addProvider($providerDetails);
    }
}
