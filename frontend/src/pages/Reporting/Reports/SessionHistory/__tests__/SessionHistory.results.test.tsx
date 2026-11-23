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
// Test type: Page integration — Session History results
// =============================================================================

import { screen, waitFor, within } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeAll, beforeEach, describe, test, expect } from 'vitest';

import { buildSessionRow, SESSION_RESPONSE } from '../../__tests__/fixtures/reportRows';
import { stubIntersectionObserver } from '../../__tests__/helpers/domStubs';
import {
  applyButton,
  pickOption,
  renderReportPage,
} from '../../__tests__/helpers/renderReportPage';
import { setupOptionServiceMocks } from '../../__tests__/mocks/optionServices';
import SessionHistory from '../SessionHistory';

import { fetchSessionHistory } from '@/services/sessionHistoryApi';
import type { SessionHistoryResponse } from '@/services/sessionHistoryApi';
import { testQueryClient } from '@/setupTests';
import { knownFailure } from '@/testUtils/knownFailure';
import { getRow } from '@/testUtils/rowActions';

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
vi.mock('@/services/sessionHistoryApi', () => ({ fetchSessionHistory: vi.fn() }));

// =============================================================================
// Helpers
// =============================================================================

const renderPage = () =>
  renderReportPage(SessionHistory, {
    route: '/reporting/session-history',
    settingsKey: 'session_history_page',
  });

const runReport = async (user: UserEvent, type?: string) => {
  await screen.findByRole('button', { name: /^apply$/i });
  if (type) {
    await pickOption(user, /^report type$/i, type);
  }
  await user.click(applyButton());
  await screen.findAllByRole('cell', { name: 'bob' });
};

const headings = () => screen.getAllByRole('columnheader').map((th) => th.textContent);

const rowFor = (userName: string) => getRow(userName);

// =============================================================================
// Tests
// =============================================================================

describe('Session History - results', () => {
  beforeAll(() => {
    stubIntersectionObserver();
  });

  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(fetchSessionHistory).mockReset().mockResolvedValue(SESSION_RESPONSE);
    setupOptionServiceMocks();
  });

  test.each([
    [
      'Sessions',
      undefined,
      [
        'Start Date',
        'End Date',
        'Duration',
        'UserName',
        'User Type',
        'IP Address',
        'Session ID',
        'Browser',
      ],
    ],
    [
      'Audit',
      'Audit',
      [
        'Date',
        'User Name',
        'User ID',
        'IP Address',
        'Session ID',
        'Entity',
        'Entity ID',
        'Message',
        'Details',
      ],
    ],
    [
      'Debug',
      'Debug',
      [
        'Date',
        'UserName',
        'User ID',
        'IP Address',
        'Session ID',
        'Channel',
        'Function',
        'Level',
        'Page',
        'Details',
      ],
    ],
  ])('the %s report shows its own columns', async (_label, type, expected) => {
    const user = userEvent.setup({ delay: null });
    renderPage();

    await runReport(user, type);

    expect(headings()).toEqual(expected);
  });

  // Picking another type without Apply must not relabel rows fetched as the old type.
  test('the columns follow the type the report was run with', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await runReport(user);

    await user.click(screen.getByRole('button', { name: /filters/i }));
    await pickOption(user, /^report type$/i, 'Audit');

    expect(headings()).toContain('Start Date');
    expect(headings()).not.toContain('Entity');
  });

  test('the chosen type, user and session id reach the request', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await screen.findByRole('button', { name: /^apply$/i });

    await pickOption(user, /^user$/i, 'alice');
    await user.type(screen.getByRole('textbox', { name: /^session history id$/i }), '9');
    await runReport(user, 'Debug');

    expect(fetchSessionHistory).toHaveBeenLastCalledWith(
      expect.objectContaining({ type: 'debug', userId: 1, sessionHistoryId: '9' }),
    );
  });

  test('View Details lists each saved property and its value', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await runReport(user, 'Audit');

    await user.click(within(rowFor('alice')).getByRole('button', { name: 'View Details' }));

    const dialog = await screen.findByRole('dialog', { name: 'Details' });
    expect(within(dialog).getByRole('cell', { name: 'name' })).toBeInTheDocument();
    expect(within(dialog).getByRole('cell', { name: 'Lobby Layout' })).toBeInTheDocument();
  });

  test('details stored as an object are listed too, nested values as JSON', async () => {
    const user = userEvent.setup({ delay: null });
    vi.mocked(fetchSessionHistory).mockResolvedValueOnce({
      ...SESSION_RESPONSE,
      table: [
        buildSessionRow({
          userName: 'bob',
          objectAfter: { duration: 30, regions: { main: 1 } } as unknown as string,
        }),
      ],
    } as SessionHistoryResponse);
    renderPage();
    await runReport(user, 'Audit');

    await user.click(within(rowFor('bob')).getByRole('button', { name: 'View Details' }));

    const dialog = await screen.findByRole('dialog', { name: 'Details' });
    expect(within(dialog).getByRole('cell', { name: '30' })).toBeInTheDocument();
    expect(within(dialog).getByRole('cell', { name: '{"main":1}' })).toBeInTheDocument();
  });

  test('empty or unreadable details say "No details available."', async () => {
    const user = userEvent.setup({ delay: null });
    vi.mocked(fetchSessionHistory).mockResolvedValueOnce({
      ...SESSION_RESPONSE,
      table: [
        buildSessionRow({ userName: 'alice', objectAfter: 'not json' }),
        buildSessionRow({ userName: 'bob', objectAfter: '' }),
      ],
    });
    renderPage();
    await runReport(user, 'Audit');

    await user.click(within(rowFor('alice')).getByRole('button', { name: 'View Details' }));
    const dialog = await screen.findByRole('dialog', { name: 'Details' });
    expect(within(dialog).getByText('No details available.')).toBeInTheDocument();
    await user.keyboard('{Escape}');
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());

    await user.click(within(rowFor('bob')).getByRole('button', { name: 'View Details' }));
    await screen.findByText('No details available.');
  });

  // Known issue 11: xibosignage/xibo#4103 (Apply with unchanged filters doesn't re-run)
  describe('Pinned until https://github.com/xibosignage/xibo/issues/4103 is fixed', () => {
    knownFailure(
      'applying the same filters again runs the report again',
      async () => {
        const user = userEvent.setup({ delay: null });
        renderPage();
        await runReport(user);
        await user.click(screen.getByRole('button', { name: /filters/i }));
        await user.click(applyButton());
        // Control: the panel closed, so Apply was handled.
        await waitFor(() =>
          expect(screen.queryByRole('button', { name: /^apply$/i })).not.toBeInTheDocument(),
        );
      },
      () => expect(fetchSessionHistory).toHaveBeenCalledTimes(2),
    );
  });
});
