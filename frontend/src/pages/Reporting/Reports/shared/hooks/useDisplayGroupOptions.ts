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

import { useEffect, useState } from 'react';

import type { MultiSelectOption } from '@/components/ui/forms/MultiSelectDropdown';
import { fetchDisplayGroups } from '@/services/displayGroupApi';

const MAX_DISPLAY_GROUPS = 200;

export function useDisplayGroupOptions(enabled = true): MultiSelectOption[] {
  const [options, setOptions] = useState<MultiSelectOption[]>([]);

  useEffect(() => {
    if (!enabled) {
      return;
    }
    const controller = new AbortController();
    fetchDisplayGroups({
      start: 0,
      length: MAX_DISPLAY_GROUPS,
      displayGroup: '',
      signal: controller.signal,
    })
      .then(({ rows }) => {
        setOptions(rows.map((g) => ({ value: String(g.displayGroupId), label: g.displayGroup })));
      })
      .catch(() => {
        // Silently ignore
      });
    return () => controller.abort();
  }, [enabled]);

  return options;
}
