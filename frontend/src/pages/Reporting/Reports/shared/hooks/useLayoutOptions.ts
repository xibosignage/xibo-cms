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

import { fetchLayouts } from '@/services/layoutsApi';

const PAGE_SIZE = 20;

export function useLayoutOptions(enabled = true) {
  const loader: OptionsLoader = async (search, start, signal) => {
    const { rows, totalCount } = await fetchLayouts({
      start,
      length: PAGE_SIZE,
      layout: search,
      signal,
    });
    return {
      options: rows.map((l) => ({ value: String(l.layoutId), label: l.layout })),
      totalCount,
    };
  };

  return usePaginatedOptions({ loader, enabled });
}
