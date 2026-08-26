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

// Reuses the exact transform already used by the Time Connected report page,
// so "Uptime" here is computed identically to what that report shows.
import { usePlayerFaults } from '@/pages/Displays/Displays/hooks/useDisplayManageData';
import { transformData } from '@/pages/Reporting/Reports/TimeConnected/hooks/useTimeConnectedData';
import { fetchTimeConnected } from '@/services/timeConnectedApi';

/**
 * A single display's current uptime percentage, sourced from the same
 * per-display availability report (`/report/data/timeconnected`, filtered by
 * `displayId`) used by the Time Connected report page. Disabled (and returns
 * no data) when the caller doesn't have the `displays.reporting` feature,
 * since the underlying report endpoint requires it.
 */
export function useDisplayUptime(displayId: number | null, enabled: boolean) {
  return useQuery<number | null>({
    queryKey: ['display', 'overview', 'uptime', displayId],
    queryFn: async ({ signal }) => {
      const response = await fetchTimeConnected({
        reportFilter: 'today',
        groupByFilter: 'byhour',
        displayId: displayId!,
        signal,
      });
      const rows = transformData(response.table);
      return rows[0]?.uptimePercent ?? null;
    },
    enabled: enabled && !!displayId,
    staleTime: 1000 * 60,
  });
}

/**
 * Active (non-expired) player faults for a single display. Thin wrapper around the shared
 * usePlayerFaults() hook (also used by the legacy Manage modal's full fault history), so the
 * two never end up with two independent implementations of the same fetch.
 */
export function useActivePlayerFaults(displayId: number | null) {
  return usePlayerFaults(displayId, { activeOnly: true });
}
