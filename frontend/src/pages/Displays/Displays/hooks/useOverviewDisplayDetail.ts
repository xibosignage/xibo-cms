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

import { displayQueryKeys } from '@/pages/Displays/Displays/hooks/useDisplaysData';
import { fetchDisplays } from '@/services/displaysApi';

/**
 * A single display, fetched directly by ID off the same list endpoint the
 * grid uses. Backs the Manage page (`/displays/displays/:displayId`), which
 * — unlike the legacy modal it complements — can be landed on directly (a
 * refresh, a bookmark, a shared link) without the row already being in
 * memory from the grid.
 */
export function useOverviewDisplayDetail(displayId: number | null) {
  return useQuery({
    queryKey: displayQueryKeys.list({ displayId }),
    queryFn: async ({ signal }) => {
      const { rows } = await fetchDisplays({ start: 0, length: 1, displayId: displayId!, signal });
      return rows[0] ?? null;
    },
    enabled: displayId !== null,
    staleTime: 1000 * 30,
  });
}
