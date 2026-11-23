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

// =============================================================================
// Test type: Hook — the available-reports list, incl. the SSP Activity card
// =============================================================================

import { QueryClientProvider } from '@tanstack/react-query';
import { renderHook, waitFor } from '@testing-library/react';
import type { ReactNode } from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { useAllReportsData } from '../../hooks/useAllReportsData';
import {
  ALL_REPORTS,
  plainUser,
  sessionHistory,
  SSP_FROM_SERVER,
  superAdmin,
} from '../fixtures/allReports';

import { UserProvider } from '@/context/UserContext';
import { fetchReports } from '@/services/reportApi';
import { testQueryClient } from '@/setupTests';
import type { User } from '@/types/user';

vi.mock('@/services/reportApi', () => ({ fetchReports: vi.fn() }));

const renderFor = (user: User, enabled = true) =>
  renderHook(() => useAllReportsData(enabled), {
    wrapper: ({ children }: { children: ReactNode }) => (
      <QueryClientProvider client={testQueryClient}>
        <UserProvider initialUser={user}>{children}</UserProvider>
      </QueryClientProvider>
    ),
  });

const allNames = (data: Record<string, { name: string }[]> | undefined) =>
  Object.values(data ?? {})
    .flat()
    .map((r) => r.name);

describe('useAllReportsData', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(fetchReports).mockReset().mockResolvedValue(ALL_REPORTS);
  });

  test('a super admin gets the SSP Activity card under Proof of Play', async () => {
    const { result } = renderFor(superAdmin);

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    const pop = result.current.data!['Proof of Play']!;
    expect(pop[pop.length - 1]).toEqual(
      expect.objectContaining({ name: 'ssp-activity', url: '/reporting/ssp-activity' }),
    );
  });

  test('a super admin gets a Proof of Play category even if the server sent none', async () => {
    vi.mocked(fetchReports).mockResolvedValue({ Audit: [sessionHistory] });
    const { result } = renderFor(superAdmin);

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(result.current.data!['Proof of Play']!.map((r) => r.name)).toEqual(['ssp-activity']);
  });

  test("the SSP card isn't added twice", async () => {
    vi.mocked(fetchReports).mockResolvedValue({
      ...ALL_REPORTS,
      'Proof of Play': [...ALL_REPORTS['Proof of Play']!, SSP_FROM_SERVER],
    });
    const { result } = renderFor(superAdmin);

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(allNames(result.current.data).filter((n) => n === 'ssp-activity')).toHaveLength(1);
  });

  test('other users never get the SSP card', async () => {
    const { result } = renderFor(plainUser);

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(allNames(result.current.data)).not.toContain('ssp-activity');
  });

  test('nothing is requested while disabled', () => {
    const { result } = renderFor(plainUser, false);

    expect(result.current.fetchStatus).toBe('idle');
    expect(fetchReports).not.toHaveBeenCalled();
  });
});
