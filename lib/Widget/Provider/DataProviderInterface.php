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

namespace Xibo\Widget\Provider;

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
     * Set the provider to use an event instead of providing its own data
     * @return \Xibo\Widget\Provider\DataProviderInterface
     */
    public function setIsUseEvent(): DataProviderInterface;

    /**
     * Should the data provider try to find its data via an event?
     * @return bool
     */
    public function isUseEvent(): bool;

    /**
     * Get data already added to this provider
     * @return array
     */
    public function getData(): array;

    /**
     * Add an item to the provider
     * You should ensure that you provide all properties required by the datatype you are returning
     * example data types would be: article, social, event, menu, tabular
     * @param array $item An array containing the item to render in any templates used by this data provider
     * @return \Xibo\Widget\Provider\DataProviderInterface
     */
    public function addItem(array $item): DataProviderInterface;

    /**
     * Clear any data already added to this provider
     * @return \Xibo\Widget\Provider\DataProviderInterface
     */
    public function clearData(): DataProviderInterface;
}
