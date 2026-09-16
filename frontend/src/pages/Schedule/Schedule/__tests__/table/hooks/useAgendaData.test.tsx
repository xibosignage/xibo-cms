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

import { QueryClientProvider } from '@tanstack/react-query';
import { renderHook, waitFor } from '@testing-library/react';
import type { ReactNode } from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  AGENDA_DAY_END,
  AGENDA_DAY_START,
  ATRIUM_GROUP,
  buildAgendaEvent,
  buildAgendaLayout,
  buildAgendaResponse,
  FOYER_GROUP,
  SINGLE_AGENDA_EVENT,
} from '../../fixtures/agenda';

import { useAgendaData } from '@/pages/Schedule/Schedule/hooks/useAgendaData';
import { fetchAgendaEvents } from '@/services/eventApi';
import type { FetchAgendaEventsRequest } from '@/services/eventApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/eventApi');

// =============================================================================
// Helpers
// =============================================================================

const wrapper = ({ children }: { children: ReactNode }) => (
  <QueryClientProvider client={testQueryClient}>{children}</QueryClientProvider>
);

const WHOLE_DAY: Omit<FetchAgendaEventsRequest, 'signal'> = {
  displayGroupId: FOYER_GROUP.id,
  singlePointInTime: 0,
  startDate: AGENDA_DAY_START,
  endDate: AGENDA_DAY_END,
};

// =============================================================================
// Tests
// =============================================================================

describe('useAgendaData', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(fetchAgendaEvents).mockResolvedValue(SINGLE_AGENDA_EVENT);
  });

  test('fetches the agenda with exactly the parameters it was given', async () => {
    const { result } = renderHook(() => useAgendaData(WHOLE_DAY, true), { wrapper });

    await waitFor(() => {
      expect(result.current.data).toEqual(SINGLE_AGENDA_EVENT);
    });
    expect(fetchAgendaEvents).toHaveBeenCalledWith(expect.objectContaining(WHOLE_DAY));
  });

  // The agenda modal opens before a display group has been chosen, and asking
  // for group 0 would be a wasted request against a group that cannot exist.
  test('asks for nothing at all while it is disabled', async () => {
    const { result } = renderHook(() => useAgendaData(WHOLE_DAY, false), { wrapper });

    expect(fetchAgendaEvents).not.toHaveBeenCalled();
    expect(result.current.data).toBeUndefined();
  });

  // The query key has to carry the parameters. If it collapsed to a constant,
  // every display group would be served the first group's agenda from cache -
  // which looks like a working page showing the wrong screens' schedule.
  test('a different display group is fetched afresh rather than served from cache', async () => {
    const atrium = buildAgendaResponse(
      [buildAgendaEvent({ eventId: 950, layoutId: 200 })],
      [buildAgendaLayout({ layoutId: 200, layout: 'Atrium Loop' })],
    );
    vi.mocked(fetchAgendaEvents).mockImplementation(({ displayGroupId }) =>
      Promise.resolve(displayGroupId === ATRIUM_GROUP.id ? atrium : SINGLE_AGENDA_EVENT),
    );

    const { result, rerender } = renderHook(
      ({ displayGroupId }) => useAgendaData({ ...WHOLE_DAY, displayGroupId }, true),
      { wrapper, initialProps: { displayGroupId: FOYER_GROUP.id } },
    );

    await waitFor(() => {
      expect(result.current.data).toEqual(SINGLE_AGENDA_EVENT);
    });

    rerender({ displayGroupId: ATRIUM_GROUP.id });

    await waitFor(() => {
      expect(result.current.data).toEqual(atrium);
    });
    expect(fetchAgendaEvents).toHaveBeenCalledWith(
      expect.objectContaining({ displayGroupId: ATRIUM_GROUP.id }),
    );
  });

  test('surfaces a failure to the caller instead of hanging', async () => {
    vi.mocked(fetchAgendaEvents).mockRejectedValue(new Error('Network Error'));

    const { result } = renderHook(() => useAgendaData(WHOLE_DAY, true), { wrapper });

    await waitFor(() => {
      expect(result.current.isError).toBe(true);
    });
  });
});
