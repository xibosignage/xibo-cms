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

import type { useDisplayProfileActions } from '../../hooks/useDisplayProfileActions';
import { useDisplayProfileData } from '../../hooks/useDisplayProfileData';

import { fetchDisplayProfile } from '@/services/displayProfileApi';
import type { FetchDisplayProfileResponse } from '@/services/displayProfileApi';

export type UseDisplayProfileReturn = ReturnType<typeof useDisplayProfileData>;
export type UseDisplayProfileActionsReturn = ReturnType<typeof useDisplayProfileActions>;

export const mockFetchDisplayProfile = (rawData: FetchDisplayProfileResponse) => {
  vi.mocked(fetchDisplayProfile).mockResolvedValue(rawData);
};

export const mockDisplayProfileData = (rawData: FetchDisplayProfileResponse) => {
  vi.mocked(useDisplayProfileData).mockReturnValue({
    data: rawData,
    isFetching: false,
    isError: false,
    error: null,
  } as unknown as UseDisplayProfileReturn);
};

export const defaultDisplayProfileActions = (
  overrides: Partial<UseDisplayProfileActionsReturn> = {},
): UseDisplayProfileActionsReturn =>
  ({
    isDeleting: false,
    deleteError: null,
    setDeleteError: vi.fn(),
    confirmDelete: vi.fn(),
    isCopying: false,
    confirmCopy: vi.fn(),
    ...overrides,
  }) as UseDisplayProfileActionsReturn;
