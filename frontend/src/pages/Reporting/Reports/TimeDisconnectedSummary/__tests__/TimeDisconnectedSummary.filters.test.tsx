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
// Test type: Page integration — Time Connected Summary filters
// =============================================================================

import { screen, waitFor } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeAll, beforeEach, describe, test, expect } from 'vitest';

import { TDS_RESPONSE } from '../../__tests__/fixtures/reportRows';
import { stubIntersectionObserver } from '../../__tests__/helpers/domStubs';
import {
  applyButton,
  pickOption,
  renderReportPage,
} from '../../__tests__/helpers/renderReportPage';
import { setupOptionServiceMocks } from '../../__tests__/mocks/optionServices';
import TimeDisconnectedSummary from '../TimeDisconnectedSummary';

import { fetchTimeDisconnectedSummary } from '@/services/timeDisconnectedSummaryApi';
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
vi.mock('@/services/tagApi', () => ({
  fetchTags: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
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
vi.mock('@/services/timeDisconnectedSummaryApi', () => ({
  fetchTimeDisconnectedSummary: vi.fn(),
}));

// =============================================================================
// Helpers
// =============================================================================

const renderPage = () =>
  renderReportPage(TimeDisconnectedSummary, {
    route: '/reporting/time-connected-summary',
    settingsKey: 'time_disconnected_summary_page',
  });

// The filter panel is open on arrival; Apply is ready once the page has hydrated.
const waitForFilters = () => screen.findByRole('button', { name: /^apply$/i });

const addTag = async (user: UserEvent, tag: string) => {
  await user.type(screen.getByRole('combobox', { name: /^tags$/i }), `${tag}{Enter}`);
};

const apply = async (user: UserEvent) => {
  await user.click(applyButton());
  await waitFor(() => expect(fetchTimeDisconnectedSummary).toHaveBeenCalled());
};

const lastRequest = () => {
  const calls = vi.mocked(fetchTimeDisconnectedSummary).mock.calls;
  return calls[calls.length - 1]![0];
};

// =============================================================================
// Tests
// =============================================================================

describe('Time Connected Summary - filters', () => {
  beforeAll(() => {
    stubIntersectionObserver();
  });

  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    // clearAllMocks keeps behaviour set by earlier tests; reset what this file overrides.
    vi.mocked(fetchTimeDisconnectedSummary).mockReset().mockResolvedValue(TDS_RESPONSE);
    setupOptionServiceMocks();
  });

  // A single display can't be grouped, so the grouping controls step aside.
  test('choosing a Display hides Group By and Display Group', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await waitForFilters();
    expect(screen.getByRole('combobox', { name: /^group by$/i })).toBeInTheDocument();
    expect(screen.getByRole('combobox', { name: /^display group$/i })).toBeInTheDocument();

    await pickOption(user, /^display$/i, 'Lobby Screen');

    await waitFor(() =>
      expect(screen.queryByRole('combobox', { name: /^group by$/i })).not.toBeInTheDocument(),
    );
    expect(screen.queryByRole('combobox', { name: /^display group$/i })).not.toBeInTheDocument();
  });

  test('a display reaches the request as its id', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await waitForFilters();

    await pickOption(user, /^display$/i, 'Lobby Screen');
    await apply(user);

    expect(lastRequest()).toEqual(expect.objectContaining({ displayId: 1 }));
  });

  test('Display Group grouping and chosen groups reach the request', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await waitForFilters();

    await pickOption(user, /^group by$/i, 'Display Group');
    await user.click(screen.getByRole('combobox', { name: /^display group$/i }));
    await user.click(await screen.findByRole('checkbox', { name: 'Ground Floor' }));
    await apply(user);

    expect(lastRequest()).toEqual(
      expect.objectContaining({ groupBy: 'displayGroup', displayGroupId: [201] }),
    );
  });

  test('tags, exact tags and Only logged in reach the request', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await waitForFilters();

    await addTag(user, 'lobby');
    await addTag(user, 'foyer');
    await user.click(screen.getByRole('button', { name: /match exact characters only/i }));
    await user.click(screen.getByRole('checkbox', { name: /only show currently logged in/i }));
    await apply(user);

    expect(lastRequest()).toEqual(
      expect.objectContaining({ tags: 'lobby,foyer', exactTags: true, onlyLoggedIn: true }),
    );
  });

  // Empty filters are left out rather than sent as empty values.
  test('with default filters only the range and grouping are sent', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await waitForFilters();

    await apply(user);

    const request = lastRequest();
    expect(request).toEqual(
      expect.objectContaining({ reportFilter: 'lastweek', groupBy: 'display', displayId: null }),
    );
    expect(request.tags).toBeUndefined();
    expect(request.displayGroupId).toBeUndefined();
  });

  // Known issue 2: xibosignage/xibo#4093 (AND/OR switch never sent)
  describe('Pinned until https://github.com/xibosignage/xibo/issues/4093 is fixed', () => {
    knownFailure(
      'the tag AND/OR choice reaches the request',
      async () => {
        const user = userEvent.setup({ delay: null });
        renderPage();
        await waitForFilters();
        await addTag(user, 'lobby');
        await user.click(screen.getByRole('button', { name: /^or$/i }));
        await user.click(await screen.findByRole('button', { name: /^and$/i }));
        await apply(user);
        // Control: the tag itself was sent.
        expect(lastRequest().tags).toBe('lobby');
        return lastRequest();
      },
      (request) => expect(request).toEqual(expect.objectContaining({ logicalOperator: 'AND' })),
    );
  });

  // Known issue 7: xibosignage/xibo#4094 (hidden grouping values still sent)
  describe('Pinned until https://github.com/xibosignage/xibo/issues/4094 is fixed', () => {
    knownFailure(
      'display groups chosen before picking a Display are not sent',
      async () => {
        const user = userEvent.setup({ delay: null });
        renderPage();
        await waitForFilters();
        await user.click(screen.getByRole('combobox', { name: /^display group$/i }));
        await user.click(await screen.findByRole('checkbox', { name: 'Ground Floor' }));
        await user.keyboard('{Escape}');
        await pickOption(user, /^display$/i, 'Lobby Screen');
        await apply(user);
        // Control: the display was sent.
        expect(lastRequest().displayId).toBe(1);
        return lastRequest();
      },
      (request) => expect(request.displayGroupId).toBeUndefined(),
    );
  });
});
