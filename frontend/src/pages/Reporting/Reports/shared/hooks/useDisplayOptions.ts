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

import { usePaginatedOptions } from './usePaginatedOptions';
import type { OptionsLoader } from './usePaginatedOptions';

import { fetchDisplays } from '@/services/displaysApi';

const PAGE_SIZE = 20;

export function useDisplayOptions(enabled = true) {
  const loader: OptionsLoader = async (search, start, signal) => {
    const { rows, totalCount } = await fetchDisplays({
      start,
      length: PAGE_SIZE,
      display: search,
      signal,
    });
    return {
      options: rows.map((d) => ({ value: String(d.displayId), label: d.display })),
      totalCount,
    };
  };

  const select = usePaginatedOptions({ loader, enabled });

  const resolveLabel = async (val: string): Promise<string> => {
    const { rows } = await fetchDisplays({ start: 0, length: 1, displayId: Number(val) });
    return rows[0]?.display ?? '';
  };

  return { ...select, resolveLabel };
}
