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

namespace Xibo\Widget;

use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use PicoFeed\Config\Config;
use PicoFeed\Logging\Logger;
use PicoFeed\Parser\Item;
use PicoFeed\PicoFeedException;
use PicoFeed\Reader\Reader;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Xibo\Helper\Environment;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Widget\DataType\Article;
use Xibo\Widget\Provider\DataProviderInterface;
use Xibo\Widget\Provider\DurationProviderNumItemsTrait;
use Xibo\Widget\Provider\WidgetProviderInterface;
use Xibo\Widget\Provider\WidgetProviderTrait;

/**
 * Downloads an RSS feed and returns Article data types
 */
class RssProvider implements WidgetProviderInterface
{
    use WidgetProviderTrait;
    use DurationProviderNumItemsTrait;

    public function fetchData(DataProviderInterface $dataProvider): WidgetProviderInterface
    {
        $uri = $dataProvider->getProperty('uri');
        if (empty($uri)) {
            throw new InvalidArgumentException(__('Please enter the URI to a valid RSS feed.'), 'uri');
        }

        $picoFeedLoggingEnabled = Environment::isDevMode();

        // Image expiry
        $expiresImage = Carbon::now()
            ->addMinutes($dataProvider->getProperty('updateIntervalImages', 1440))
            ->format('U');

        try {
            // Get the feed
            $response = $this->getFeed($dataProvider, $uri);

            // Pull out the content type
            $contentType = $response['contentType'];

            $this->getLog()->debug('Feed returned content-type ' . $contentType);

            // https://github.com/xibosignage/xibo/issues/1401
            if (stripos($contentType, 'rss') === false
                && stripos($contentType, 'xml') === false
                && stripos($contentType, 'text') === false
                && stripos($contentType, 'html') === false
            ) {
                // The content type isn't compatible
                $this->getLog()->error('Incompatible content type: ' . $contentType);
                return $this;
            }

            // Get the body, etc
            $result = explode('charset=', $contentType);
            $document['encoding'] = $result[1] ?? '';
            $document['xml'] = $response['body'];

            $this->getLog()->debug('Feed downloaded.');

            // Load the feed XML document into a feed parser
            // Enable logging if we need to
            if ($picoFeedLoggingEnabled) {
                $this->getLog()->debug('Setting Picofeed Logger to Enabled.');
                Logger::enable();
            }

            // Allowable attributes
            $clientConfig = new Config();

            // need a sensible way to set this
            // https://github.com/fguillot/picoFeed/issues/196
            //if ($dataProvider->getProperty('allowedAttributes') != null) {
            //$clientConfig->setFilterWhitelistedTags(explode(',', $dataProvider->getProperty('allowedAttributes')));
            //}

            // Get the feed parser
            $reader = new Reader($clientConfig);
            $parser = $reader->getParser($uri, $document['xml'], $document['encoding']);

            // Get a feed object
            $feed = $parser->execute();

            // Get all items
            $feedItems = $feed->getItems();

            // Disable date sorting?
            if ($dataProvider->getProperty('disableDateSort') == 0
                && $dataProvider->getProperty('randomiseItems', 0) == 0
            ) {
                // Sort the items array by date
                usort($feedItems, function ($a, $b) {
                    /* @var Item $a */
                    /* @var Item $b */
                    return $b->getDate()->getTimestamp() - $a->getDate()->getTimestamp();
                });
            }

            $sanitizer = null;
            if ($dataProvider->getProperty('stripTags') != '') {
                $sanitizer = (new HtmlSanitizerConfig())->allowSafeElements();

                // Add the tags to strip
                foreach (explode(',', $dataProvider->getProperty('stripTags')) as $forbidden) {
                    $this->getLog()->debug('fetchData: blocking element ' . $forbidden);
                    $sanitizer = $sanitizer->blockElement($forbidden);
                }
            }

            // Where should we get images?
            $imageSource = $dataProvider->getProperty('imageSource', 'enclosure');
            $imageTag = match ($imageSource) {
                'mediaContent' => 'media:content',
                'image' => 'image',
                'custom' => $dataProvider->getProperty('imageSourceTag', 'image'),
                default => 'enclosure'
            };
            $imageSourceAttribute = null;
            if ($imageSource === 'mediaContent') {
                $imageSourceAttribute = 'url';
            } else if ($imageSource === 'custom') {
                $imageSourceAttribute = $dataProvider->getProperty('imageSourceAttribute', null);
            }

            // Parse each item into an article
            foreach ($feedItems as $item) {
                /* @var Item $item */
                $article = new Article();
                $article->title = html_entity_decode($item->getTitle(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $article->author = html_entity_decode($item->getAuthor(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $article->link = $item->getUrl();
                $article->date = Carbon::instance($item->getDate());
                $article->publishedDate = Carbon::instance($item->getPublishedDate());

                // Body safe HTML
                $article->content = $dataProvider->getSanitizer(['content' => $item->getContent()])
                    ->getHtml('content', [
                        'htmlSanitizerConfig' => $sanitizer
                    ]);

                // RSS doesn't support a summary/excerpt tag.
                $descriptionTag = $item->getTag('description');
                $article->summary = trim(
                    $descriptionTag ?
                        html_entity_decode(strip_tags($descriptionTag[0]), ENT_QUOTES | ENT_HTML5, 'UTF-8')
                        : $article->content
                );

                // Do we have an image included?
                $link = null;
                if ($imageTag === 'enclosure') {
                    if (stripos($item->getEnclosureType(), 'image') > -1) {
                        $link = $item->getEnclosureUrl();
                    }
                } else {
                    $link = $item->getTag($imageTag, $imageSourceAttribute)[0] ?? null;
                }

                if (!(empty($link))) {
                    $article->image = $dataProvider->addImage('ticker_' . md5($link), $link, $expiresImage);
                } else {
                    $this->getLog()->debug('fetchData: no image found for image tag using ' . $imageTag);
                }

                if ($dataProvider->getProperty('decodeHtml') == 1) {
                    $article->content = htmlspecialchars_decode($article->content);
                }

                // Add the article.
                $dataProvider->addItem($article);
            }
            
            $dataProvider->setCacheTtl($dataProvider->getProperty('updateInterval', 60) * 60);
            $dataProvider->setIsHandled();
        } catch (GuzzleException $requestException) {
            // Log and return empty?
            $this->getLog()->error('Unable to get feed: ' . $uri
                . ', e: ' . $requestException->getMessage());
            $dataProvider->addError(__('Unable to download feed'));
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
            $dataProvider->addError(__('Unable to parse feed'));
        }

        // Output any PicoFeed logs
        if ($picoFeedLoggingEnabled) {
            foreach (Logger::getMessages() as $message) {
                $this->getLog()->debug($message);
            }
        }

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

    /**
     * @param DataProviderInterface $dataProvider
     * @param string $uri
     * @return array body, contentType
     * @throws GuzzleException
     */
    private function getFeed(DataProviderInterface $dataProvider, string $uri): array
    {
        // See if we have this feed cached already.
        $cache = $dataProvider->getPool()->getItem('/widget/' . $dataProvider->getDataType() . '/' . md5($uri));
        $body = $cache->get();

        if ($cache->isMiss() || $body === null || !is_array($body)) {
            // Make a new request.
            $this->getLog()->debug('getFeed: cache miss');
            $body = [];

            $httpOptions = [
                'headers' => [
                    'Accept' => 'application/rss+xml, application/rdf+xml;q=0.8, application/atom+xml;q=0.6,'
                        . 'application/xml;q=0.4, text/xml;q=0.4, text/html;q=0.2, text/*;q=0.1'
                ],
                'timeout' => 20, // wait no more than 20 seconds
            ];

            if (!empty($dataProvider->getProperty('userAgent'))) {
                $httpOptions['headers']['User-Agent'] = trim($dataProvider->getProperty('userAgent'));
            }

            $response = $dataProvider
                ->getGuzzleClient($httpOptions)
                ->get($uri);

            $body['body'] = $response->getBody()->getContents();
            $body['contentType'] = $response->getHeaderLine('Content-Type');

            // Save the resonse to cache
            $cache->set($body);
            $cache->expiresAfter($dataProvider->getSetting('cachePeriod', 1440) * 60);
            $dataProvider->getPool()->saveDeferred($cache);
        } else {
            $this->getLog()->debug('getFeed: cache hit');
        }

        return $body;
    }
}
