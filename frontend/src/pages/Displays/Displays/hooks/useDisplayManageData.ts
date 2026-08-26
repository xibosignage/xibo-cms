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
  fetchScreenshotHistory,
} from '@/services/displaysApi';
import type {
  BandwidthResponse,
  DisconnectionEvent,
  DisplayManageData,
  DisplayScreenshot,
  PlayerFault,
} from '@/types/displayManage';
import { formatDateTime } from '@/utils/date';

/**
 * Player faults for a single display, from `/display/faults/{displayId}`. Shared by the
 * legacy Manage modal's full fault history (`activeOnly` omitted) and the Overview page's
 * Active Faults panel (`activeOnly: true`), so the fetch/query-key logic exists once.
 */
export function usePlayerFaults(displayId: number | null, options: { activeOnly?: boolean } = {}) {
  const { activeOnly = false } = options;

  return useQuery<PlayerFault[]>({
    queryKey: ['display', 'faults', displayId, activeOnly],
    queryFn: ({ signal }) =>
      fetchPlayerFaults(displayId!, signal, activeOnly ? { activeOnly: 1 } : undefined),
    enabled: !!displayId,
    staleTime: 0,
  });
}

export function useDisplayManageData(displayId: number | null) {
  const manageQuery = useQuery<DisplayManageData>({
    queryKey: ['display', 'manage', displayId],
    queryFn: ({ signal }) => fetchDisplayManageData(displayId!, signal),
    enabled: !!displayId,
    staleTime: 0,
  });

  const faultsQuery = usePlayerFaults(displayId);

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

/**
 * Takes a duration rather than two dates, so the current time stays out of the query key and it
 * does not refetch in a loop.
 */
/**
 * A display's recent screenshots. Polled, because a display on an interval keeps producing them
 * and the Live badge is time sensitive.
 */
export function useDisplayScreenshots(displayId: number | null, enabled: boolean) {
  return useQuery<DisplayScreenshot[]>({
    queryKey: ['display', 'screenshots', displayId],
    queryFn: ({ signal }) => fetchScreenshotHistory(displayId!, signal),
    enabled: enabled && !!displayId,
    staleTime: 1000 * 15,
    refetchInterval: 1000 * 30,
  });
}

export function useDisconnectionEvents(
  displayId: number | null,
  windowMinutes: number,
  timeZone: string,
  eventTypeIds: number[],
  enabled: boolean,
) {
  return useQuery<DisconnectionEvent[]>({
    queryKey: ['display', 'disconnections', displayId, windowMinutes, timeZone, eventTypeIds],
    queryFn: ({ signal }) => {
      // The endpoint reads these in the CMS timezone, so they have to be written in it.
      const now = Date.now();

      return fetchDisconnectionEvents(
        {
          displayId: displayId!,
          fromDt: formatDateTime(new Date(now - windowMinutes * 60 * 1000), timeZone),
          toDt: formatDateTime(new Date(now), timeZone),
          eventTypeIds,
        },
        signal,
      );
    },
    enabled: enabled && !!displayId,
    staleTime: 1000 * 15,
    refetchInterval: 1000 * 30,
  });
}
