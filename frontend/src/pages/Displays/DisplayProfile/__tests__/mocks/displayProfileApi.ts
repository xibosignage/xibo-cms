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
