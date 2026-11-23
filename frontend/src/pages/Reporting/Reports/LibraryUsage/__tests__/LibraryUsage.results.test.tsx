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
// Test type: Page integration — Library Usage results
// =============================================================================

import { screen, waitFor, within } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { vi, beforeAll, beforeEach, describe, test, expect } from 'vitest';

import { LIBRARY_RESPONSE } from '../../__tests__/fixtures/reportRows';
import { stubIntersectionObserver } from '../../__tests__/helpers/domStubs';
import { applyButton } from '../../__tests__/helpers/renderReportPage';
import { setupOptionServiceMocks } from '../../__tests__/mocks/optionServices';

import { renderLibraryUsage } from './helpers/renderLibraryUsage';

import { fetchLibraryUsage } from '@/services/libraryUsageApi';
import type { LibraryUsageResponse } from '@/services/libraryUsageApi';
import { testQueryClient } from '@/setupTests';
import { knownFailure } from '@/testUtils/knownFailure';

// =============================================================================
// Module mocks
// =============================================================================

const mockNavigate = vi.fn();
vi.mock('react-router-dom', async (importOriginal) => ({
  ...((await importOriginal()) as object),
  useNavigate: () => mockNavigate,
}));
vi.mock('@/services/displaysApi', async (o) => ({
  ...((await o()) as object),
  fetchDisplays: vi.fn(),
}));
vi.mock('@/services/displayGroupApi', async (o) => ({
  ...((await o()) as object),
  fetchDisplayGroups: vi.fn(),
}));
vi.mock('@/services/layoutsApi', async (o) => ({
  ...((await o()) as object),
  fetchLayouts: vi.fn(),
}));
vi.mock('@/services/mediaApi', async (o) => ({ ...((await o()) as object), fetchMedia: vi.fn() }));
vi.mock('@/services/campaignApi', async (o) => ({
  ...((await o()) as object),
  fetchCampaigns: vi.fn(),
}));
vi.mock('@/services/userGroupApi', async (o) => ({
  ...((await o()) as object),
  fetchUserGroups: vi.fn(),
}));
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
  fetchUsers: vi.fn(),
}));
vi.mock('@/services/reportApi', () => ({
  fetchReports: vi.fn().mockResolvedValue({}),
}));
vi.mock('@/services/reportScheduleApi', () => ({
  createReportSchedule: vi.fn().mockResolvedValue({}),
}));
vi.mock('@/components/ui/Notification', () => ({
  notify: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('@/services/libraryUsageApi', () => ({ fetchLibraryUsage: vi.fn() }));
// Recharts measures 0×0 in jsdom, so use named stand-ins.
vi.mock('recharts', () => {
  const box = (label: string) =>
    function Stub({ children }: { children?: ReactNode }) {
      return (
        <div role="img" aria-label={label}>
          {children}
        </div>
      );
    };
  return {
    ResponsiveContainer: ({ children }: { children?: ReactNode }) => <div>{children}</div>,
    PieChart: box('pie chart'),
    Tooltip: () => null,
    Legend: () => null,
    Pie: ({ data }: { data: { name: string; value: number }[] }) => (
      <>
        {data.map((d) => (
          <span key={d.name}>{`slice ${d.name} ${d.value}`}</span>
        ))}
      </>
    ),
  };
});

// =============================================================================
// Helpers
// =============================================================================

const runReport = async (user: UserEvent) => {
  await screen.findByRole('button', { name: /^apply$/i });
  await user.click(applyButton());
  await waitFor(() => expect(fetchLibraryUsage).toHaveBeenCalled());
};

const userColumn = () =>
  screen
    .getAllByRole('row')
    .slice(1)
    .map((row) => within(row).getAllByRole('cell')[1]!.textContent);

const emptyPie = {
  type: 'pie',
  data: { labels: [], datasets: [{ backgroundColor: [], data: [] }] },
};

// =============================================================================
// Tests
// =============================================================================

describe('Library Usage - results', () => {
  beforeAll(() => {
    stubIntersectionObserver();
  });

  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(fetchLibraryUsage).mockReset().mockResolvedValue(LIBRARY_RESPONSE);
    setupOptionServiceMocks();
  });

  test('each user is listed with their usage and file count', async () => {
    const user = userEvent.setup({ delay: null });
    renderLibraryUsage();
    await runReport(user);

    await screen.findByRole('cell', { name: '900 MiB' });
    expect(screen.getAllByRole('columnheader').map((th) => th.textContent)).toEqual([
      'ID',
      'User',
      'Usage',
      'Count Files',
    ]);
  });

  // Sorting the text would put "1.5 GiB" before "900 MiB"; the real sizes say otherwise.
  test('Usage sorts by real size, smallest first', async () => {
    const user = userEvent.setup({ delay: null });
    renderLibraryUsage();
    await runReport(user);
    await screen.findByRole('cell', { name: '900 MiB' });

    await user.click(
      within(screen.getByRole('columnheader', { name: 'Usage' })).getByRole('button'),
    );

    await waitFor(() => expect(userColumn()).toEqual(['bob', 'alice']));
  });

  test('the chart view shows the Library Usage and User Percentage Usage pies', async () => {
    const user = userEvent.setup({ delay: null });
    renderLibraryUsage();
    await runReport(user);
    await screen.findByRole('cell', { name: '900 MiB' });

    await user.click(screen.getByRole('button', { name: 'Chart View' }));

    await screen.findByRole('heading', { name: 'Library Usage' });
    expect(screen.getByRole('heading', { name: 'User Percentage Usage' })).toBeInTheDocument();
    expect(screen.getByText('slice Used 40')).toBeInTheDocument();
    expect(screen.getByText('slice alice 63')).toBeInTheDocument();
  });

  test('a pie with no data shows its own empty message', async () => {
    const user = userEvent.setup({ delay: null });
    vi.mocked(fetchLibraryUsage).mockResolvedValueOnce({
      ...LIBRARY_RESPONSE,
      chart: { ...LIBRARY_RESPONSE.chart, Library_Usage: emptyPie },
    } as LibraryUsageResponse);
    renderLibraryUsage();
    await runReport(user);
    await screen.findByRole('cell', { name: '900 MiB' });

    await user.click(screen.getByRole('button', { name: 'Chart View' }));

    await screen.findByText('No library data available.');
    expect(screen.queryByText('No user data available.')).not.toBeInTheDocument();
    expect(screen.getByText('slice alice 63')).toBeInTheDocument();
  });

  // Known issue 14: xibosignage/xibo#4105 (one chart missing crashes the page)
  describe('Pinned until https://github.com/xibosignage/xibo/issues/4105 is fixed', () => {
    knownFailure(
      'a response missing one chart still shows the table',
      async () => {
        const user = userEvent.setup({ delay: null });
        vi.mocked(fetchLibraryUsage).mockResolvedValueOnce({
          ...LIBRARY_RESPONSE,
          chart: { Library_Usage: LIBRARY_RESPONSE.chart.Library_Usage },
        } as unknown as LibraryUsageResponse);
        renderLibraryUsage();
        await runReport(user);
        // Control: the report did run.
        expect(fetchLibraryUsage).toHaveBeenCalled();
      },
      async () => {
        await screen.findByRole('cell', { name: '900 MiB' });
      },
    );
  });
});
