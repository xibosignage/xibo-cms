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

import { useSyncGroupData } from '../../hooks/useSyncGroupsData';

import { fetchSyncGroups } from '@/services/syncGroupApi';
import type { FetchSyncGroupsResponse } from '@/services/syncGroupApi';

export type UseSyncGroupDataReturn = ReturnType<typeof useSyncGroupData>;

// Makes fetchSyncGroups return the provided data (integration-style tests).
// Requires vi.mock('@/services/syncGroupApi') at the test file level.
export const mockFetchSyncGroups = (rawData: FetchSyncGroupsResponse) => {
  vi.mocked(fetchSyncGroups).mockResolvedValue(rawData);
};

// Mocks the data hook directly (for unit-style tests that bypass the
// React Query layer entirely).
// Requires vi.mock('../../hooks/useSyncGroupsData') at the test file level.
export const mockSyncGroupData = (rawData: FetchSyncGroupsResponse) => {
  vi.mocked(useSyncGroupData).mockReturnValue({
    data: rawData,
    isFetching: false,
    isError: false,
    error: null,
  } as unknown as UseSyncGroupDataReturn);
};
