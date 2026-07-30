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

import { usePlayerVersionData } from '../../hooks/usePlayerVersionsData';

import { fetchPlayerVersions } from '@/services/playerVersionApi';
import type { FetchPlayerVersionsResponse } from '@/services/playerVersionApi';

export type UsePlayerVersionDataReturn = ReturnType<typeof usePlayerVersionData>;

export const mockFetchPlayerVersions = (rawData: FetchPlayerVersionsResponse) => {
  vi.mocked(fetchPlayerVersions).mockResolvedValue(rawData);
};

export const mockPlayerVersionData = (rawData: FetchPlayerVersionsResponse) => {
  vi.mocked(usePlayerVersionData).mockReturnValue({
    data: rawData,
    isFetching: false,
    isError: false,
    error: null,
  } as unknown as UsePlayerVersionDataReturn);
};
