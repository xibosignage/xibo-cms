<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

namespace Xibo\Factory;

use Xibo\Entity\PlayerFault;

class PlayerFaultFactory extends BaseFactory
{
    /**
     * Create Empty
     * @return PlayerFault
     */
    public function createEmpty()
    {
        return new PlayerFault(
            $this->getStore(),
            $this->getLog(),
            $this->getDispatcher()
        );
    }

    /**
     * @param int $displayId
     * @return PlayerFault[]
     */
    public function getByDisplayId(int $displayId, $sortOrder = null)
    {
        return $this->query($sortOrder, ['disableUserCheck' => 1, 'displayId' => $displayId]);
    }

    /**
     * @param null $sortOrder
     * @param array $filterBy
     * @return PlayerFault[]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        if ($sortOrder === null) {
            $sortOrder = ['incidentDt DESC'];
        }

        $sanitizedFilter = $this->getSanitizer($filterBy);

        $params = [];
        $entries = [];

        $select = '
            SELECT player_faults.playerFaultId,
               player_faults.displayId,
               player_faults.incidentDt,
               player_faults.expires,
               player_faults.code,
               player_faults.reason,
               player_faults.layoutId,
               player_faults.regionId,
               player_faults.widgetId,
               player_faults.scheduleId,
               player_faults.mediaId
            ';

        $body = ' FROM player_faults
                  WHERE 1 = 1 
        ';

        if ($sanitizedFilter->getInt('playerFaultId') !== null) {
            $body .= ' AND `player_faults`.playerFaultId = :playerFaultId ';
            $params['playerFaultId'] = $sanitizedFilter->getInt('playerFaultId');
        }

        if ($sanitizedFilter->getInt('displayId') !== null) {
            $body .= ' AND `player_faults`.displayId = :displayId ';
            $params['displayId'] = $sanitizedFilter->getInt('displayId');
        }

        // Sorting?
        $order = '';

        if (is_array($sortOrder)) {
            $order .= 'ORDER BY ' . implode(',', $sortOrder);
        }

        $limit = '';
        // Paging
        if ($filterBy !== null && $sanitizedFilter->getInt('start') !== null && $sanitizedFilter->getInt('length') !== null) {
            $limit = ' LIMIT ' . $sanitizedFilter->getInt('start', ['default' => 0]) . ', ' . $sanitizedFilter->getInt('length', ['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $playerFault = $this->createEmpty()->hydrate($row);
            $entries[] = $playerFault;
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}
