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

import type { useEventActions } from '../../../hooks/useEventActions';
import { useEventData } from '../../../hooks/useEventData';

import type { FetchEventResponse } from '@/services/eventApi';
import { fetchEvent } from '@/services/eventApi';

// -----------------------------------------------------------------------------
// Typed mock helpers
// -----------------------------------------------------------------------------
export type UseEventReturn = ReturnType<typeof useEventData>;
export type UseEventActionsReturn = ReturnType<typeof useEventActions>;

// Use this when your test directly mocks useEventData (parallels mockLayoutData).
export const mockEventData = (rawData: FetchEventResponse) => {
  vi.mocked(useEventData).mockReturnValue({
    data: rawData,
    isFetching: false,
    isError: false,
    error: null,
  } as UseEventReturn);
};

// Use this when you want the real useEventData and React Query to run.
export const mockFetchEvent = (rawData: FetchEventResponse) => {
  vi.mocked(fetchEvent).mockResolvedValue(rawData);
};

// Returns a fresh useEventActions mock value for every beforeEach call.
export const defaultEventActions = (
  overrides: Partial<UseEventActionsReturn> = {},
): UseEventActionsReturn => ({
  isDeleting: false,
  isCloning: false,
  deleteError: null,
  setDeleteError: vi.fn(),
  confirmDelete: vi.fn(),
  confirmDeleteOccurrence: vi.fn(),
  handleConfirmClone: vi.fn(),
  ...overrides,
});
