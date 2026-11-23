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

import { useQuery } from '@tanstack/react-query';

import {
  fetchBandwidthData,
  fetchDisplayManageData,
  fetchPlayerFaults,
} from '@/services/displaysApi';
import type { BandwidthResponse, DisplayManageData, PlayerFault } from '@/types/displayManage';

export function useDisplayManageData(displayId: number | null) {
  const manageQuery = useQuery<DisplayManageData>({
    queryKey: ['display', 'manage', displayId],
    queryFn: ({ signal }) => fetchDisplayManageData(displayId!, signal),
    enabled: !!displayId,
    staleTime: 0,
  });

  const faultsQuery = useQuery<PlayerFault[]>({
    queryKey: ['display', 'faults', displayId],
    queryFn: ({ signal }) => fetchPlayerFaults(displayId!, signal),
    enabled: !!displayId,
    staleTime: 0,
  });

  return { manageQuery, faultsQuery };
}

export function useBandwidthData(
  displayId: number | null,
  fromDt: string,
  toDt: string,
  enabled: boolean,
) {
  return useQuery<BandwidthResponse>({
    queryKey: ['display', 'bandwidth', displayId, fromDt, toDt],
    queryFn: ({ signal }) => fetchBandwidthData({ displayId: displayId!, fromDt, toDt }, signal),
    enabled: enabled && !!displayId,
    staleTime: 1000 * 60 * 5,
  });
}
