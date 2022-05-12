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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * A connector to get data from the Twitter API for use by the Twitter Widget
 */
class TwitterConnector implements ConnectorInterface
{
    use ConnectorTrait;

    public function registerWithDispatcher(EventDispatcherInterface $dispatcher): ConnectorInterface
    {
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
        $settings['apiKey'] = $params->getString('apiKey');
        $settings['apiSecret'] = $params->getString('apiSecret');
        $settings['cachePeriod'] = $params->getInt('cachePeriod');
        $settings['cachePeriodImages'] = $params->getInt('cachePeriodImages');
        return $settings;
    }
}
