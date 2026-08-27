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
import { useEffect } from 'react';

import {
  fetchBandwidthData,
  fetchDisplayManageData,
  fetchPlayerFaults,
  fetchScreenshotTime,
  requestScreenShot,
} from '@/services/displaysApi';
import type { BandwidthResponse, DisplayManageData, PlayerFault } from '@/types/displayManage';

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
 * How often the Manage page asks for a new screenshot, and how often it looks to see whether one
 * has landed.
 *
 * Note what a request costs: requestScreenShot() pushes a ScreenShotAction over XMR when the
 * display has a channel, so on a player with working push this is a capture-and-upload every
 * tick for as long as the page is open, not just a flag being set. Raise this if that turns out
 * to be too much for a fleet.
 */
export const SCREENSHOT_POLL_MS = 3000;

/**
 * When the display's current screenshot was taken, polled so the caller can notice a new one.
 *
 * The value is only ever compared, never parsed for its own sake, so the CMS's own string is
 * returned as it stands.
 */
export function useCurrentScreenshotTime(displayId: number | null, poll: boolean = true) {
  return useQuery<string | null>({
    queryKey: ['display', 'screenshotTime', displayId],
    queryFn: ({ signal }) => fetchScreenshotTime(displayId!, signal),
    enabled: !!displayId,
    // Always refetched rather than served from cache: noticing the new capture is the whole job.
    staleTime: 0,
    // Fetched once and left alone when not polling, since a display that is not checking in
    // cannot produce a new answer. Deliberately not `enabled: false`, which would leave the query
    // reporting pending forever and the caller unable to tell that apart from a first load.
    refetchInterval: poll ? SCREENSHOT_POLL_MS : false,
  });
}

/**
 * Asks the display for a screenshot on a timer, for as long as the caller is mounted.
 *
 * Driven from the client on purpose: leaving the page unmounts this and the asking stops, which a
 * server-side interval could not do. A request only asks, so the reply arrives whenever the
 * player next acts on it rather than on this timer.
 *
 * Pass enabled=false for a display that is not checking in. Asking anyway is not harmless: each
 * request writes the display row, and it still publishes over XMR when the display has a channel,
 * which an offline display keeps from when it registered.
 */
export function useScreenshotAutoRequest(displayId: number | null, enabled: boolean = true) {
  useEffect(() => {
    if (!enabled || !displayId) {
      return;
    }

    let stopped = false;

    const ask = () => {
      if (stopped) {
        return;
      }

      // Swallowed deliberately. This runs unattended every few seconds, so a failed ask is not
      // worth a toast; the card keeps showing the last screenshot it managed to get.
      requestScreenShot(displayId).catch(() => {});
    };

    ask();
    const timer = setInterval(ask, SCREENSHOT_POLL_MS);

    return () => {
      stopped = true;
      clearInterval(timer);
    };
  }, [displayId, enabled]);
}
