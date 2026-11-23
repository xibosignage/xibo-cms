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
// Test type: Page integration — Display Alerts filters
// =============================================================================

import { screen, waitFor } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeAll, beforeEach, describe, test, expect } from 'vitest';

import { ALERTS_RESPONSE } from '../../__tests__/fixtures/reportRows';
import { stubIntersectionObserver } from '../../__tests__/helpers/domStubs';
import {
  applyButton,
  pickOption,
  renderReportPage,
} from '../../__tests__/helpers/renderReportPage';
import { setupOptionServiceMocks } from '../../__tests__/mocks/optionServices';
import DisplayAlerts from '../DisplayAlerts';

import { fetchDisplayAlerts } from '@/services/displayAlertsApi';
import { testQueryClient } from '@/setupTests';

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
vi.mock('@/services/displayAlertsApi', () => ({ fetchDisplayAlerts: vi.fn() }));

// =============================================================================
// Helpers
// =============================================================================

const renderPage = () =>
  renderReportPage(DisplayAlerts, {
    route: '/reporting/display-alerts',
    settingsKey: 'display_alerts_page',
  });

const waitForFilters = () => screen.findByRole('button', { name: /^apply$/i });

const apply = async (user: UserEvent) => {
  await user.click(applyButton());
  await waitFor(() => expect(fetchDisplayAlerts).toHaveBeenCalled());
};

const lastRequest = () => {
  const calls = vi.mocked(fetchDisplayAlerts).mock.calls;
  return calls[calls.length - 1]![0];
};

// =============================================================================
// Tests
// =============================================================================

describe('Display Alerts - filters', () => {
  beforeAll(() => {
    stubIntersectionObserver();
  });

  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(fetchDisplayAlerts).mockReset().mockResolvedValue(ALERTS_RESPONSE);
    setupOptionServiceMocks();
  });

  test.each([
    ['Display Up/down', '1'],
    ['App Start', '2'],
    ['Power Cycle', '3'],
    ['Network Cycle', '4'],
    ['TV Monitoring', '5'],
    ['Player Fault', '6'],
    ['Command', '7'],
    ['Other', '8'],
  ])('choosing the "%s" event type sends %s', async (label, value) => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await waitForFilters();

    await pickOption(user, /^event type$/i, label);
    await apply(user);

    expect(lastRequest().eventType).toBe(value);
  });

  // The page leaves the event type out; the service then asks for all (-1).
  test('with no event type chosen, none is passed on', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await waitForFilters();

    await apply(user);

    expect(lastRequest().eventType).toBeUndefined();
  });

  test('tags, exact tags and the AND/OR choice reach the request', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await waitForFilters();

    await user.type(screen.getByRole('combobox', { name: /^tags$/i }), 'lobby{Enter}');
    await user.click(screen.getByRole('button', { name: /match exact characters only/i }));
    await user.click(screen.getByRole('button', { name: /^or$/i }));
    await user.click(await screen.findByRole('button', { name: /^and$/i }));
    await apply(user);

    expect(lastRequest()).toEqual(
      expect.objectContaining({ tags: 'lobby', exactTags: true, logicalOperator: 'AND' }),
    );
  });

  // Unlike Time Connected Summary, Display Alerts keeps Display Group visible with a Display.
  test('a display and display groups can be chosen together and are both sent', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await waitForFilters();

    await pickOption(user, /^display$/i, 'Lobby Screen');
    await user.click(screen.getByRole('combobox', { name: /^display group$/i }));
    await user.click(await screen.findByRole('checkbox', { name: 'Ground Floor' }));
    await user.keyboard('{Escape}');
    await user.click(screen.getByRole('checkbox', { name: /only show currently logged in/i }));
    await apply(user);

    expect(lastRequest()).toEqual(
      expect.objectContaining({ displayId: 1, displayGroupId: [201], onlyLoggedIn: true }),
    );
  });
});
