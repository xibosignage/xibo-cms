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

import http from '@/lib/api';

export interface PlayerSoftware {
  versionId: number;
  version: string;
  playerShowVersion: string;
  type: string;
}

export interface FetchPlayerSoftwareRequest {
  start?: number;
  length?: number;
  playerType?: string;
  playerShowVersion?: string;
}

export interface FetchPlayerSoftwareResponse {
  rows: PlayerSoftware[];
  totalCount: number;
}

export async function fetchPlayerSoftware(
  options: FetchPlayerSoftwareRequest | string = {},
): Promise<FetchPlayerSoftwareResponse> {
  const params =
    typeof options === 'string'
      ? { start: 0, length: 10, playerType: options }
      : { start: 0, length: 10, ...options };

  const response = await http.get('/playersoftware', { params });

  const totalCountHeader = response.headers['x-total-count'];
  const totalCount = totalCountHeader
    ? parseInt(totalCountHeader, 10)
    : (response.data as PlayerSoftware[]).length;

  return { rows: response.data as PlayerSoftware[], totalCount };
}
