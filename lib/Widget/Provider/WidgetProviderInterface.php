<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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

namespace Xibo\Widget\Provider;

use Carbon\Carbon;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The Widget Provider Interface should be implemented by any Widget which specifies a `class` in its Module
 * configuration.
 *
 * The provider should be modified accordingly before returning $this
 *
 * If the widget does not need to fetch Data or fetch Duration, then it can return without
 * modifying the provider.
 */
interface WidgetProviderInterface
{
    public function getLog(): LoggerInterface;
    public function setLog(LoggerInterface $logger): WidgetProviderInterface;

    /**
     * Get the event dispatcher
     * @return \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    public function getDispatcher(): EventDispatcherInterface;

    /**
     * Set the event dispatcher
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $logger
     * @return \Xibo\Widget\Provider\WidgetProviderInterface
     */
    public function setDispatcher(EventDispatcherInterface $logger): WidgetProviderInterface;

    /**
     * Fetch data
     * The widget provider must either addItems to the data provider, or indicate that data is provided by
     * an event instead by setting isUseEvent()
     * If data is to be provided by an event, core will raise the `widget.request.data` event with parameters
     * indicating this widget's datatype, name, settings and currently configured options
     * @param \Xibo\Widget\Provider\DataProviderInterface $dataProvider
     * @return \Xibo\Widget\Provider\WidgetProviderInterface
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function fetchData(DataProviderInterface $dataProvider): WidgetProviderInterface;

    /**
     * Fetch duration
     * This is typically only relevant to widgets which have a media file associated, for example video or audio
     * in cases where this is not appropriate, return without modifying to use the module default duration from
     * module configuration.
     * @param \Xibo\Widget\Provider\DurationProviderInterface $durationProvider
     * @return \Xibo\Widget\Provider\WidgetProviderInterface
     */
    public function fetchDuration(DurationProviderInterface $durationProvider): WidgetProviderInterface;

    /**
     * Get data cache key
     * Use this method to return a cache key for this widget. This is typically only relevant when the data cache
     * should be different based on the value of a setting. For example, if the tweetDistance is set on a Twitter
     * widget, then the cache should be by displayId. If the cache is always by displayId, then you should supply
     * the `dataCacheKey` via module config XML instead.
     * @param \Xibo\Widget\Provider\DataProviderInterface $dataProvider
     * @return string|null
     */
    public function getDataCacheKey(DataProviderInterface $dataProvider): ?string;

    /**
     * Get data modified date
     * Use this method to invalidate cache ahead of its expiry date/time by returning the date/time that the underlying
     * data is expected to or has been modified
     * @param \Xibo\Widget\Provider\DataProviderInterface $dataProvider
     * @return \Carbon\Carbon|null
     */
    public function getDataModifiedDt(DataProviderInterface $dataProvider): ?Carbon;
}
