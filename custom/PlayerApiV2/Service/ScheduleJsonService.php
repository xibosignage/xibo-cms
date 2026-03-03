<?php
/*
 * Copyright (C) 2026 Pau Aliagas <linuxnow@gmail.com>
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

namespace Xibo\Custom\PlayerApiV2\Service;

/**
 * Converts schedule XML (from Soap::doSchedule()) to structured JSON.
 *
 * This is the server-side equivalent of the SDK's schedule-parser.js,
 * eliminating 230+ lines of client-side XML parsing. The output format
 * matches the SDK parser exactly (lowercase field names: fromdt, todt,
 * scheduleid) so the SDK can consume it directly.
 */
class ScheduleJsonService
{
    /**
     * Convert schedule XML to a structured JSON string.
     *
     * @param string $xml Schedule XML from Soap::doSchedule()
     * @return string JSON string
     */
    public static function xmlToJson(string $xml): string
    {
        return json_encode(self::parse($xml), JSON_UNESCAPED_SLASHES);
    }

    /**
     * Parse schedule XML into a structured array.
     *
     * @param string $xml Schedule XML from Soap::doSchedule()
     * @return array Parsed schedule structure
     */
    public static function parse(string $xml): array
    {
        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        $schedule = [
            'default' => null,
            'defaultDependants' => [],
            'dependants' => [],
            'layouts' => [],
            'campaigns' => [],
            'overlays' => [],
            'actions' => [],
            'commands' => [],
            'dataConnectors' => [],
        ];

        $scheduleEl = $doc->getElementsByTagName('schedule')->item(0);
        if ($scheduleEl === null) {
            return $schedule;
        }

        // Global dependants (direct children of <schedule>)
        foreach (self::directChildren($scheduleEl, 'dependants') as $depContainer) {
            foreach ($depContainer->getElementsByTagName('file') as $fileEl) {
                $text = trim($fileEl->textContent);
                if ($text !== '') {
                    $schedule['dependants'][] = $text;
                }
            }
        }

        // Default layout
        $defaultEl = $doc->getElementsByTagName('default')->item(0);
        if ($defaultEl !== null) {
            $schedule['default'] = $defaultEl->getAttribute('file');
            foreach ($defaultEl->getElementsByTagName('file') as $fileEl) {
                $text = trim($fileEl->textContent);
                if ($text !== '') {
                    $schedule['defaultDependants'][] = $text;
                }
            }
        }

        // Campaigns
        foreach ($doc->getElementsByTagName('campaign') as $campaignEl) {
            $campaign = [
                'id' => $campaignEl->getAttribute('id'),
                'priority' => (int)($campaignEl->getAttribute('priority') ?: 0),
                'fromdt' => $campaignEl->getAttribute('fromdt'),
                'todt' => $campaignEl->getAttribute('todt'),
                'scheduleid' => $campaignEl->getAttribute('scheduleid'),
                'maxPlaysPerHour' => (int)($campaignEl->getAttribute('maxPlaysPerHour') ?: 0),
                'shareOfVoice' => (int)($campaignEl->getAttribute('shareOfVoice') ?: 0),
                'isGeoAware' => $campaignEl->getAttribute('isGeoAware') === '1',
                'geoLocation' => $campaignEl->getAttribute('geoLocation') ?: '',
                'syncEvent' => $campaignEl->getAttribute('syncEvent') === '1',
                'recurrenceType' => $campaignEl->getAttribute('recurrenceType') ?: null,
                'recurrenceDetail' => ((int)$campaignEl->getAttribute('recurrenceDetail')) ?: null,
                'recurrenceRepeatsOn' => $campaignEl->getAttribute('recurrenceRepeatsOn') ?: null,
                'recurrenceRange' => $campaignEl->getAttribute('recurrenceRange') ?: null,
                'criteria' => self::parseCriteria($campaignEl),
                'layouts' => [],
            ];

            foreach ($campaignEl->getElementsByTagName('layout') as $layoutEl) {
                $campaign['layouts'][] = self::parseLayoutElement($layoutEl, $campaign);
            }

            $schedule['campaigns'][] = $campaign;
        }

        // Standalone layouts (direct children of <schedule>)
        foreach (self::directChildren($scheduleEl, 'layout') as $layoutEl) {
            $layout = self::parseLayoutElement($layoutEl);
            $layout['recurrenceType'] = $layoutEl->getAttribute('recurrenceType') ?: null;
            $layout['recurrenceDetail'] = ((int)$layoutEl->getAttribute('recurrenceDetail')) ?: null;
            $layout['recurrenceRepeatsOn'] = $layoutEl->getAttribute('recurrenceRepeatsOn') ?: null;
            $layout['recurrenceRange'] = $layoutEl->getAttribute('recurrenceRange') ?: null;
            $schedule['layouts'][] = $layout;
        }

        // Overlays
        $overlaysEl = $doc->getElementsByTagName('overlays')->item(0);
        if ($overlaysEl !== null) {
            foreach ($overlaysEl->getElementsByTagName('overlay') as $overlayEl) {
                $schedule['overlays'][] = [
                    'id' => (string)$overlayEl->getAttribute('file'),
                    'file' => $overlayEl->getAttribute('file'),
                    'duration' => (int)($overlayEl->getAttribute('duration') ?: 60),
                    'fromdt' => $overlayEl->getAttribute('fromdt'),
                    'todt' => $overlayEl->getAttribute('todt'),
                    'priority' => (int)($overlayEl->getAttribute('priority') ?: 0),
                    'scheduleid' => $overlayEl->getAttribute('scheduleid'),
                    'isGeoAware' => $overlayEl->getAttribute('isGeoAware') === '1',
                    'geoLocation' => $overlayEl->getAttribute('geoLocation') ?: '',
                    'syncEvent' => $overlayEl->getAttribute('syncEvent') === '1',
                    'maxPlaysPerHour' => (int)($overlayEl->getAttribute('maxPlaysPerHour') ?: 0),
                    'recurrenceType' => $overlayEl->getAttribute('recurrenceType') ?: null,
                    'recurrenceDetail' => ((int)$overlayEl->getAttribute('recurrenceDetail')) ?: null,
                    'recurrenceRepeatsOn' => $overlayEl->getAttribute('recurrenceRepeatsOn') ?: null,
                    'recurrenceRange' => $overlayEl->getAttribute('recurrenceRange') ?: null,
                    'criteria' => self::parseCriteria($overlayEl),
                ];
            }
        }

        // Actions
        $actionsEl = $doc->getElementsByTagName('actions')->item(0);
        if ($actionsEl !== null) {
            foreach ($actionsEl->getElementsByTagName('action') as $actionEl) {
                $schedule['actions'][] = [
                    'actionType' => $actionEl->getAttribute('actionType') ?: '',
                    'triggerCode' => $actionEl->getAttribute('triggerCode') ?: '',
                    'layoutCode' => $actionEl->getAttribute('layoutCode') ?: '',
                    'commandCode' => $actionEl->getAttribute('commandCode') ?: '',
                    'duration' => (int)($actionEl->getAttribute('duration') ?: 0),
                    'fromdt' => $actionEl->getAttribute('fromdt'),
                    'todt' => $actionEl->getAttribute('todt'),
                    'priority' => (int)($actionEl->getAttribute('priority') ?: 0),
                    'scheduleid' => $actionEl->getAttribute('scheduleid'),
                    'isGeoAware' => $actionEl->getAttribute('isGeoAware') === '1',
                    'geoLocation' => $actionEl->getAttribute('geoLocation') ?: '',
                ];
            }
        }

        // Commands (direct children of <schedule>)
        foreach (self::directChildren($scheduleEl, 'command') as $cmdEl) {
            $schedule['commands'][] = [
                'code' => $cmdEl->getAttribute('code') ?: '',
                'date' => $cmdEl->getAttribute('date') ?: '',
            ];
        }

        // Data connectors
        $dataConnectorsEl = $doc->getElementsByTagName('dataConnectors')->item(0);
        if ($dataConnectorsEl !== null) {
            foreach ($dataConnectorsEl->getElementsByTagName('connector') as $dcEl) {
                $schedule['dataConnectors'][] = [
                    'id' => $dcEl->getAttribute('id') ?: '',
                    'dataConnectorId' => $dcEl->getAttribute('dataConnectorId') ?: '',
                    'dataSetId' => $dcEl->getAttribute('dataSetId') ?: '',
                    'dataKey' => $dcEl->getAttribute('dataKey') ?: '',
                    'dataParams' => $dcEl->getAttribute('dataParams') ?: '',
                    'js' => $dcEl->getAttribute('js') ?: '',
                    'url' => $dcEl->getAttribute('url') ?: '',
                    'updateInterval' => (int)($dcEl->getAttribute('updateInterval') ?: 300),
                ];
            }
        }

        return $schedule;
    }

    /**
     * Parse a layout element into a structured array.
     *
     * @param \DOMElement $el   The layout element
     * @param array|null  $campaign  Parent campaign data (for inherited fields)
     * @return array
     */
    private static function parseLayoutElement(\DOMElement $el, ?array $campaign = null): array
    {
        $fileId = $el->getAttribute('file');
        $depEls = [];
        foreach ($el->getElementsByTagName('dependants') as $depContainer) {
            foreach ($depContainer->getElementsByTagName('file') as $fileEl) {
                $text = trim($fileEl->textContent);
                if ($text !== '') {
                    $depEls[] = $text;
                }
            }
        }

        return [
            'id' => (string)$fileId,
            'file' => $fileId,
            'fromdt' => $el->getAttribute('fromdt') ?: ($campaign['fromdt'] ?? ''),
            'todt' => $el->getAttribute('todt') ?: ($campaign['todt'] ?? ''),
            'scheduleid' => $el->getAttribute('scheduleid') ?: ($campaign['scheduleid'] ?? ''),
            'priority' => (int)($el->getAttribute('priority') ?: ($campaign['priority'] ?? 0)),
            'campaignId' => $campaign['id'] ?? null,
            'maxPlaysPerHour' => (int)($el->getAttribute('maxPlaysPerHour') ?: 0),
            'isGeoAware' => $el->getAttribute('isGeoAware') === '1',
            'geoLocation' => $el->getAttribute('geoLocation') ?: '',
            'syncEvent' => $el->getAttribute('syncEvent') === '1',
            'shareOfVoice' => (int)($el->getAttribute('shareOfVoice') ?: 0),
            'duration' => (int)($el->getAttribute('duration') ?: 0),
            'cyclePlayback' => $el->getAttribute('cyclePlayback') === '1',
            'groupKey' => $el->getAttribute('groupKey') ?: null,
            'playCount' => (int)($el->getAttribute('playCount') ?: 0),
            'dependants' => $depEls,
            'criteria' => self::parseCriteria($el),
        ];
    }

    /**
     * Parse criteria child elements from a parent element.
     *
     * @param \DOMElement $parentEl
     * @return array
     */
    private static function parseCriteria(\DOMElement $parentEl): array
    {
        $criteria = [];
        foreach ($parentEl->childNodes as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE || $child->tagName !== 'criteria') {
                continue;
            }
            $criteria[] = [
                'metric' => $child->getAttribute('metric') ?: '',
                'condition' => $child->getAttribute('condition') ?: '',
                'type' => $child->getAttribute('type') ?: 'string',
                'value' => trim($child->textContent) ?: '',
            ];
        }
        return $criteria;
    }

    /**
     * Get direct child elements with a specific tag name.
     *
     * Unlike getElementsByTagName(), this only returns direct children,
     * not descendants at any depth.
     *
     * @param \DOMElement $parent
     * @param string $tagName
     * @return \DOMElement[]
     */
    private static function directChildren(\DOMElement $parent, string $tagName): array
    {
        $result = [];
        foreach ($parent->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE && $child->tagName === $tagName) {
                $result[] = $child;
            }
        }
        return $result;
    }
}
