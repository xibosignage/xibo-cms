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

import { QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, test, vi } from 'vitest';

import Logs from '../Logs';

import { mockLogsList } from './fixtures/logEntry';

import { UserProvider } from '@/context/UserContext';
import { fetchLogs, type FetchLogsRequest } from '@/services/logApi';
import { testQueryClient } from '@/setupTests';
import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';

// Unlike the other Logs page tests, the data hook and table state run for real here, so the
// filter values travel the whole way from the filter panel to the service call. The service
// mock is declared here, not taken from Setup.tsx, so the page and this test share one fetchLogs.
vi.mock('@/services/logApi', () => ({
  fetchLogs: vi.fn(),
  truncateLogs: vi.fn(),
}));

vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
  fetchAutoSubmitPreference: vi.fn().mockResolvedValue(false),
  autoSubmitPrefQueryKey: (key: string) => ['autoSubmitPref', key],
  fetchUsers: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
}));

vi.mock('@/services/displaysApi', () => ({
  fetchDisplays: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
}));

vi.mock('@/services/displayGroupApi', () => ({
  fetchDisplayGroups: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
}));

vi.mock('@/hooks/useFilteredTabs', () => ({
  useFilteredTabs: vi.fn(() => [{ name: 'Log', path: '/advanced/log' }]),
}));

const currentUser = buildCurrentUser(PERSONAS.superAdmin, {
  settings: {
    defaultTimezone: 'UTC',
    defaultLanguage: 'en',
    DATE_FORMAT_JS: 'DD/MM/YYYY',
    TIME_FORMAT_JS: 'HH:mm',
  },
});

const renderLogsPage = () =>
  render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={currentUser}>
        <MemoryRouter>
          <Logs />
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );

describe('Logs page - applying filters from the filter panel', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    // The fake server honours the level and function filters, so the grid shows what was asked for.
    vi.mocked(fetchLogs).mockImplementation(async (request?: FetchLogsRequest) => {
      const rows = mockLogsList.filter(
        (entry) =>
          (!request?.level || entry.type === request.level) &&
          (!request?.function || entry.function === request.function),
      );
      return { rows, totalCount: rows.length };
    });
  });

  test(
    'applying a level, function and time window requests those logs and shows the matching entry',
    { tags: ['smoke'] },
    async () => {
      const user = userEvent.setup();
      renderLogsPage();
      // The panel's text and number filters have a visible label that isn't linked to the input
      // (components/ui/InputFilter.tsx), so they have no accessible name; the placeholder is the only handle.
      await user.type(await screen.findByPlaceholderText('Level'), 'ERROR');
      const durationBack = screen.getByPlaceholderText('Duration Back');
      await user.clear(durationBack);
      await user.type(durationBack, '30');
      await user.click(screen.getByRole('combobox', { name: 'Interval' }));
      await user.click(await screen.findByRole('option', { name: 'Minutes' }));
      await user.click(screen.getByRole('combobox', { name: 'Function' }));
      await user.click(await screen.findByRole('option', { name: 'POST' }));
      // Whole seconds, because the page sends the time without milliseconds.
      const before = Math.floor(Date.now() / 1000) * 1000;
      await user.click(screen.getByRole('button', { name: /apply filter/i }));

      await waitFor(() => {
        expect(fetchLogs).toHaveBeenLastCalledWith({
          start: 0,
          length: 5000,
          sortBy: 'logId',
          sortDir: 'desc',
          signal: expect.any(AbortSignal),
          fromDt: expect.stringMatching(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/),
          // 30 x 60 seconds: the server subtracts this from fromDt to get the window start.
          seconds: 30,
          intervalType: 60,
          level: 'ERROR',
          runNo: '',
          channel: '',
          page: '',
          function: 'POST',
          display: '',
          message: '',
          excludeLog: 1,
        });
      });
      const after = Date.now();
      // No date was picked, so fromDt is the moment Apply was clicked, as browser-local
      // 'YYYY-MM-DD HH:mm:ss' (formatDateTime with no time zone); the 30-minute look-back is
      // applied server-side (Logging::grid), not by the page.
      const { fromDt } = vi.mocked(fetchLogs).mock.lastCall![0]!;
      const sentAt = new Date(fromDt!.replace(' ', 'T')).getTime();
      expect(sentAt).toBeGreaterThanOrEqual(before);
      expect(sentAt).toBeLessThanOrEqual(after);

      await screen.findByText('Error log message');
      expect(screen.queryByText('Info log message')).not.toBeInTheDocument();
      expect(screen.queryByText('Debug log message')).not.toBeInTheDocument();
    },
  );
});
