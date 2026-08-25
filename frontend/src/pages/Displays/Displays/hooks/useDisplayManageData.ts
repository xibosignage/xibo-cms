/*
 * Copyright (C) 2026 Xibo Signage Ltd
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

import { useQuery } from '@tanstack/react-query';

import {
  fetchBandwidthData,
  fetchDisconnectionEvents,
  fetchDisplayManageData,
  fetchPlayerFaults,
} from '@/services/displaysApi';
import type {
  BandwidthResponse,
  DisconnectionEvent,
  DisplayManageData,
  PlayerFault,
} from '@/types/displayManage';

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

export function useDisconnectionEvents(
  displayId: number | null,
  fromDt: string,
  toDt: string,
  eventTypeIds: number[],
  enabled: boolean,
) {
  return useQuery<DisconnectionEvent[]>({
    queryKey: ['display', 'disconnections', displayId, fromDt, toDt, eventTypeIds],
    queryFn: ({ signal }) =>
      fetchDisconnectionEvents({ displayId: displayId!, fromDt, toDt, eventTypeIds }, signal),
    enabled: enabled && !!displayId,
    staleTime: 1000 * 60 * 5,
  });
}
