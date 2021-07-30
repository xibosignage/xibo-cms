<?php
/**
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

namespace Xibo\Xmds;

use Carbon\Carbon;
use Xibo\Entity\Bandwidth;
use Xibo\Helper\DateFormatHelper;

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
        //$this->logProcessor->setDisplay(0, 1);

        $sanitizer = $this->getSanitizer([
            'serverKey' => $serverKey,
            'hardwareKey' => $hardwareKey
        ]);
        
        // Sanitize
        $serverKey = $sanitizer->getString('serverKey');
        $hardwareKey = $sanitizer->getString('hardwareKey');

        // Check the serverKey matches
        if ($serverKey != $this->getConfig()->getSetting('SERVER_KEY')) {
            throw new \SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');
        }

        // Auth this request...
        if (!$this->authDisplay($hardwareKey)) {
            throw new \SoapFault('Receiver', 'This Display is not authorised.');
        }

        // Now that we authenticated the Display, make sure we are sticking to our bandwidth limit
        if (!$this->checkBandwidth($this->display->displayId)) {
            throw new \SoapFault('Receiver', 'Bandwidth Limit exceeded');
        }

        // clear existing records from player_faults table
        $this->getStore()->update('DELETE FROM `player_faults` WHERE displayId = :displayId', [
            'displayId' => $this->display->displayId
        ]);

        $faultDecoded = json_decode($fault, true);

        foreach ($faultDecoded as $faultAlert) {
            $sanitizedFaultAlert = $this->getSanitizer($faultAlert);

            $incidentDt = $sanitizedFaultAlert->getDate('date', ['default' => Carbon::now()])->format(DateFormatHelper::getSystemFormat());
            $code = $sanitizedFaultAlert->getInt('code');
            $reason = $sanitizedFaultAlert->getString('reason');
            $scheduleId = $sanitizedFaultAlert->getInt('scheduleId');
            $layoutId = $sanitizedFaultAlert->getInt('layoutId');
            $regionId = $sanitizedFaultAlert->getInt('regionId');
            $mediaId = $sanitizedFaultAlert->getInt('mediaId');
            $widgetId = $sanitizedFaultAlert->getInt('widgetId');

            try {
                $dbh = $this->getStore()->getConnection();

                $insertSth = $dbh->prepare('
                        INSERT INTO player_faults (displayId, incidentDt, code, reason, scheduleId, layoutId, regionId, mediaId, widgetId)
                            VALUES (:displayId, :incidentDt, :code, :reason, :scheduleId, :layoutId, :regionId, :mediaId, :widgetId)
                    ');

                $insertSth->execute([
                    'displayId' => $this->display->displayId,
                    'incidentDt' => $incidentDt,
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
}
