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

import { vi } from 'vitest';

import { fetchDaypart } from '@/services/daypartApi';
import type { Daypart } from '@/types/daypart';

// The modal only reads dayPartId, name, isAlways, isCustom from each row,
// so the test fixtures elide the rest of the Daypart shape.
type DaypartRow = Pick<Daypart, 'dayPartId' | 'name' | 'isAlways' | 'isCustom'>;

export const ALWAYS_ONLY: DaypartRow[] = [
  { dayPartId: 1, name: 'Always', isAlways: 1, isCustom: 0 },
];

export const ALWAYS_AND_CUSTOM: DaypartRow[] = [
  { dayPartId: 1, name: 'Always', isAlways: 1, isCustom: 0 },
  { dayPartId: 2, name: 'Custom', isAlways: 0, isCustom: 1 },
  { dayPartId: 3, name: 'Morning Slot', isAlways: 0, isCustom: 0 },
];

export const setupDaypartMocks = (): void => {
  vi.mocked(fetchDaypart).mockResolvedValue({ rows: [], totalCount: 0 });
};

export const mockDaypartRows = (rows: DaypartRow[]): void => {
  vi.mocked(fetchDaypart).mockResolvedValue({
    rows: rows as unknown as Daypart[],
    totalCount: rows.length,
  });
};
