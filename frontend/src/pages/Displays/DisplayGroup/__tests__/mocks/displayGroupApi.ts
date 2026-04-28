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

import { fetchDisplayGroups } from '@/services/displayGroupApi';
import type { FetchDisplayGroupsResponse } from '@/services/displayGroupApi';
import { useDisplayGroupData } from '../../hooks/useDisplayGroupData';

export type UseDisplayGroupReturn = ReturnType<typeof useDisplayGroupData>;

// Makes fetchDisplayGroups return the provided data (integration-style tests).
// Requires vi.mock('@/services/displayGroupApi') at the test file level.
export const mockFetchDisplayGroups = (rawData: FetchDisplayGroupsResponse) => {
  vi.mocked(fetchDisplayGroups).mockResolvedValue(rawData);
};

// Mocks the data hook directly (for search/pagination unit tests).
// Requires vi.mock('../hooks/useDisplayGroupData') at the test file level.
export const mockDisplayGroupData = (rawData: FetchDisplayGroupsResponse) => {
  vi.mocked(useDisplayGroupData).mockReturnValue({
    data: rawData,
    isFetching: false,
    isError: false,
    error: null,
  } as unknown as UseDisplayGroupReturn);
};
