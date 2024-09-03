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

namespace Xibo\Xmds;

use Carbon\Carbon;
use Xibo\Entity\Bandwidth;
use Xibo\Helper\DateFormatHelper;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Class Soap6
 * @package Xibo\Xmds
 */
class Soap6 extends Soap5
{
    /**
     * Report Player Fault to the CMS
     *
     * @param string $serverKey
     * @param string $hardwareKey
     * @param string $fault
     *
     * @return bool
     */
    public function reportFaults(string $serverKey, string $hardwareKey, string $fault): bool
    {
        $this->logProcessor->setRoute('ReportFault');
        //$this->logProcessor->setDisplay(0, 'debug');

        $sanitizer = $this->getSanitizer([
            'serverKey' => $serverKey,
            'hardwareKey' => $hardwareKey
        ]);

        // Sanitize
        $serverKey = $sanitizer->getString('serverKey');
        $hardwareKey = $sanitizer->getString('hardwareKey');

        // Check the serverKey matches
        if ($serverKey != $this->getConfig()->getSetting('SERVER_KEY')) {
            throw new \SoapFault(
                'Sender',
                'The Server key you entered does not match with the server key at this address'
            );
        }

        // Auth this request...
        if (!$this->authDisplay($hardwareKey)) {
            throw new \SoapFault('Receiver', 'This Display is not authorised.');
        }

        // Now that we authenticated the Display, make sure we are sticking to our bandwidth limit
        if (!$this->checkBandwidth($this->display->displayId)) {
            throw new \SoapFault('Receiver', 'Bandwidth Limit exceeded');
        }

        $faultDecoded = json_decode($fault, true);

        // check if we should record or update any display events.
        $this->manageDisplayAlerts($faultDecoded);

        // clear existing records from player_faults table
        $this->getStore()->update('DELETE FROM `player_faults` WHERE displayId = :displayId', [
            'displayId' => $this->display->displayId
        ]);

        foreach ($faultDecoded as $faultAlert) {
            $sanitizedFaultAlert = $this->getSanitizer($faultAlert);

            $incidentDt = $sanitizedFaultAlert->getDate(
                'date',
                ['default' => Carbon::now()]
            )->format(DateFormatHelper::getSystemFormat());
            $expires = $sanitizedFaultAlert->getDate('expires', ['default' => null]);
            $code = $sanitizedFaultAlert->getInt('code');
            $reason = $sanitizedFaultAlert->getString('reason');
            $scheduleId = $sanitizedFaultAlert->getInt('scheduleId');
            $layoutId = $sanitizedFaultAlert->getInt('layoutId');
            $regionId = $sanitizedFaultAlert->getInt('regionId');
            $mediaId = $sanitizedFaultAlert->getInt('mediaId');
            $widgetId = $sanitizedFaultAlert->getInt('widgetId');

            // Trim the reason if it is too long
            if (strlen($reason) >= 255) {
                $reason = substr($reason, 0, 255);
            }

            try {
                $dbh = $this->getStore()->getConnection();

                $insertSth = $dbh->prepare('
                        INSERT INTO player_faults (displayId, incidentDt, expires, code, reason, scheduleId, layoutId, regionId, mediaId, widgetId)
                            VALUES (:displayId, :incidentDt, :expires, :code, :reason, :scheduleId, :layoutId, :regionId, :mediaId, :widgetId)
                    ');

                $insertSth->execute([
                    'displayId' => $this->display->displayId,
                    'incidentDt' => $incidentDt,
                    'expires' => $expires,
                    'code' => $code,
                    'reason' => $reason,
                    'scheduleId' => $scheduleId,
                    'layoutId' => $layoutId,
                    'regionId' => $regionId,
                    'mediaId' => $mediaId,
                    'widgetId' => $widgetId
                ]);
            } catch (\Exception $e) {
                $this->getLog()->error('Unable to insert Player Faults records. ' . $e->getMessage());
                return false;
            }
        }

        $this->logBandwidth($this->display->displayId, Bandwidth::$REPORTFAULT, strlen($fault));

        return true;
    }

    private function manageDisplayAlerts(array $newPlayerFaults)
    {
        // check current faults for player
        $currentFaults = $this->playerFaultFactory->getByDisplayId($this->display->displayId);

        // if we had faults and now we no longer have any to add
        // set end date for any existing fault events
        // add display event for cleared all faults
        if (!empty($currentFaults) && empty($newPlayerFaults)) {
            $displayEvent = $this->displayEventFactory->createEmpty();
            $displayEvent->eventTypeId = $displayEvent->getEventIdFromString('Player Fault');
            $displayEvent->displayId = $this->display->displayId;
            // clear any open player fault events for this display
            $displayEvent->eventEnd($displayEvent->displayId, $displayEvent->eventTypeId);

            // log new event for all faults cleared.
            $displayEvent->start = Carbon::now()->format('U');
            $displayEvent->end = Carbon::now()->format('U');
            $displayEvent->detail = __('All Player faults cleared');
            $displayEvent->save();
        } else if (empty($currentFaults) && !empty($newPlayerFaults)) {
            $codesAdded = [];
            // we do not have any faults currently, but new ones will be added
            foreach ($newPlayerFaults as $newPlayerFault) {
                $sanitizedFaultAlert = $this->getSanitizer($newPlayerFault);
                // if we do not have an alert for the specific code yet, add it
                if (!in_array($sanitizedFaultAlert->getInt('code'), $codesAdded)) {
                    $this->addDisplayEvent($sanitizedFaultAlert);
                    // keep track of added codes, we want a single alert per code
                    $codesAdded[] = $sanitizedFaultAlert->getInt('code');
                }
            }
        } else if (!empty($newPlayerFaults) && !empty($currentFaults)) {
            // we have both existing faults and new faults
            $existingFaultsCodes = [];
            $newFaultCodes = [];
            $codesAdded = [];

            // keep track of existing fault codes.
            foreach ($currentFaults as $currentFault) {
                $existingFaultsCodes[] = $currentFault->code;
            }

            // go through new faults
            foreach ($newPlayerFaults as $newPlayerFault) {
                $sanitizedFaultAlert = $this->getSanitizer($newPlayerFault);
                $newFaultCodes[] = $sanitizedFaultAlert->getInt('code');
                // if it already exists, we do not do anything with alerts
                // if it is a new code and was not added already
                // add it now
                if (!in_array($sanitizedFaultAlert->getInt('code'), $existingFaultsCodes)
                    && !in_array($sanitizedFaultAlert->getInt('code'), $codesAdded)
                ) {
                    $this->addDisplayEvent($sanitizedFaultAlert);
                    // keep track of added codes, we want a single alert per code
                    $codesAdded[] = $sanitizedFaultAlert->getInt('code');
                }
            }

            // go through any existing codes that are no longer reported
            // update the end date on all of them.
            foreach (array_diff($existingFaultsCodes, $newFaultCodes) as $code) {
                $displayEvent = $this->displayEventFactory->createEmpty();
                $displayEvent->eventEndByReference(
                    $this->display->displayId,
                    $displayEvent->getEventIdFromString('Player Fault'),
                    $code
                );
            }
        }
    }

    private function addDisplayEvent(SanitizerInterface $sanitizedFaultAlert)
    {
        $this->getLog()->debug(
            sprintf(
                'displayEvent : add new display alert for player fault code %d and displayId %d',
                $sanitizedFaultAlert->getInt('code'),
                $this->display->displayId
            )
        );

        $displayEvent = $this->displayEventFactory->createEmpty();
        $displayEvent->eventTypeId = $displayEvent->getEventIdFromString('Player Fault');
        $displayEvent->displayId = $this->display->displayId;
        $displayEvent->start = $sanitizedFaultAlert->getDate(
            'date',
            ['default' => Carbon::now()]
        )->format('U');
        $displayEvent->end = null;
        $displayEvent->detail = $sanitizedFaultAlert->getString('reason');
        $displayEvent->refId = $sanitizedFaultAlert->getInt('code');
        $displayEvent->save();
    }
}
