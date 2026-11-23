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

import { usePaginatedOptions } from './usePaginatedOptions';
import type { OptionsLoader } from './usePaginatedOptions';

import { fetchDisplayGroups } from '@/services/displayGroupApi';

const PAGE_SIZE = 20;

export function useDisplayGroupSelect(enabled = true) {
  const loader: OptionsLoader = async (search, start, signal) => {
    const { rows, totalCount } = await fetchDisplayGroups({
      start,
      length: PAGE_SIZE,
      displayGroup: search,
      signal,
    });
    return {
      options: rows.map((g) => ({ value: String(g.displayGroupId), label: g.displayGroup })),
      totalCount,
    };
  };

  const select = usePaginatedOptions({ loader, enabled });

  const resolveLabel = async (val: string): Promise<string> => {
    const { rows } = await fetchDisplayGroups({ start: 0, length: 1, displayGroupId: Number(val) });
    return rows[0]?.displayGroup ?? '';
  };

  return { ...select, resolveLabel };
}
