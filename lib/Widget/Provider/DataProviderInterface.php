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

namespace Xibo\Widget\Provider;

use Carbon\Carbon;
use GuzzleHttp\Client;
use Stash\Interfaces\PoolInterface;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Data Provider
 * -------------
 * A data provider is passed to a Widget which specifies a class in its configuration file
 * It should return data for the widget in the formated expected by the widgets datatype
 *
 * The widget might provid a class for other reasons and wish to use the widget.request.data event
 * to supply its data. In which case it should set is `setIsUseEvent()`.
 *
 * void methods on the data provider are chainable.
 */
interface DataProviderInterface
{
    /**
     * Get the data source expected by this provider
     * This will be the Module type that requested the provider
     * @return string
     */
    public function getDataSource(): string;

    /**
     * Get the datatype expected by this provider
     * @return string
     */
    public function getDataType(): string;

    /**
     * Get the ID for this display
     * @return int
     */
    public function getDisplayId(): int;

    /**
     * Get the latitude for this display
     * @return float|null
     */
    public function getDisplayLatitude(): ?float;

    /**
     * Get the longitude for this display
     * @return float|null
     */
    public function getDisplayLongitude(): ?float;

    /**
     * Get the preview flag
     * @return bool
     */
    public function isPreview(): bool;

    /**
     * Get the ID for this Widget
     * @return int
     */
    public function getWidgetId(): int;

    /**
     * Get a configured Guzzle client
     *  this will have its proxy configuration set and be ready to use.
     * @param array $requestOptions An optional array of additional request options.
     * @return Client
     */
    public function getGuzzleClient(array $requestOptions = []): Client;

    /**
     * Get a cache pool interface
     *  this will be a cache pool configured using the CMS settings.
     * @return PoolInterface
     */
    public function getPool(): PoolInterface;

    /**
     * Get property
     * Properties are set on Widgets and can be things like "feedUrl"
     *  the property must exist in module properties for this type of widget
     * @param string $property The property name
     * @param mixed $default An optional default value. The return will be cast to the datatype of this default value.
     * @return mixed
     */
    public function getProperty(string $property, $default = null);

    /**
     * Get setting
     * Settings are set on Modules and can be things like "apiKey"
     *  the setting must exist in module settings for this type of widget
     * @param string $setting The setting name
     * @param mixed $default An optional default value. The return will be cast to the datatype of this default value.
     * @return mixed
     */
    public function getSetting(string $setting, $default = null);

    /**
     * Get a Santiziter
     * @param array $params key/value array of variable to sanitize
     * @return SanitizerInterface
     */
    public function getSanitizer(array $params): SanitizerInterface;

    /**
     * Get the widget modifiedDt
     * @return \Carbon\Carbon|null
     */
    public function getWidgetModifiedDt(): ?Carbon;

    /**
     * Indicate that we should use the event mechanism to handle this event.
     * @return \Xibo\Widget\Provider\DataProviderInterface
     */
    public function setIsUseEvent(): DataProviderInterface;

    /**
     * Indicate that this data provider has been handled.
     * @return DataProviderInterface
     */
    public function setIsHandled(): DataProviderInterface;

    /**
     * Add an error to this data provider, if no other data providers handle this request, the error will be
     * thrown as a configuration error.
     * @param string $errorMessage
     * @return DataProviderInterface
     */
    public function addError(string $errorMessage): DataProviderInterface;

    /**
     * Add an item to the provider
     * You should ensure that you provide all properties required by the datatype you are returning
     * example data types would be: article, social, event, menu, tabular
     * @param array|object $item An array containing the item to render in any templates used by this data provider
     * @return \Xibo\Widget\Provider\DataProviderInterface
     */
    public function addItem($item): DataProviderInterface;

    /**
     * Add items to the provider
     * You should ensure that you provide all properties required by the datatype you are returning
     * example data types would be: article, social, event, menu, tabular
     * @param array $items An array containing the item to render in any templates used by this data provider
     * @return \Xibo\Widget\Provider\DataProviderInterface
     */
    public function addItems(array $items): DataProviderInterface;

    /**
     * Add metadata to the provider
     * This is a key/value array of metadata which should be delivered alongside the data
     * @param string $key
     * @param mixed $item An array/object containing the metadata, which must be JSON serializable
     * @return \Xibo\Widget\Provider\DataProviderInterface
     */
    public function addOrUpdateMeta(string $key, $item): DataProviderInterface;

    /**
     * Add an image to the data provider and return the URL for that image
     * @param string $id A unique ID for this image, we recommend adding a module/connector specific prefix
     * @param string $url The URL on which this image should be downloaded
     * @param int $expiresAt A unix timestamp for when this image should be removed - should be longer than cache ttl
     * @return string
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function addImage(string $id, string $url, int $expiresAt): string;

    /**
     * Add a library file
     * @param int $mediaId The mediaId for this file.
     * @return string
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function addLibraryFile(int $mediaId): string;

    /**
     * Set the cache TTL
     * @param int $ttlSeconds The time to live in seconds
     * @return \Xibo\Widget\Provider\DataProviderInterface
     */
    public function setCacheTtl(int $ttlSeconds): DataProviderInterface;
}
