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

import { fetchDisplayNextSchedule } from '@/services/displaysApi';

// One request per card. The backend caches its own schedule-resolution result
// for 30s per display, so this hook's staleTime matches that — no point
// re-fetching more often than the server-side answer can actually change.
export function useDisplayNextSchedule(displayId: number, enabled = true) {
  return useQuery({
    queryKey: ['display', displayId, 'schedule', 'next'],
    queryFn: ({ signal }) => fetchDisplayNextSchedule(displayId, signal),
    enabled,
    staleTime: 1000 * 30,
  });
}
