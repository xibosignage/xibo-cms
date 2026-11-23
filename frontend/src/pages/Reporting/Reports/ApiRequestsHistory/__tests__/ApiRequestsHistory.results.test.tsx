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
// Test type: Page integration — API Request History results
// =============================================================================

import { screen, within } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeAll, beforeEach, describe, test, expect } from 'vitest';

import { API_RESPONSE } from '../../__tests__/fixtures/reportRows';
import { stubIntersectionObserver } from '../../__tests__/helpers/domStubs';
import {
  applyButton,
  pickOption,
  renderReportPage,
} from '../../__tests__/helpers/renderReportPage';
import { setupOptionServiceMocks } from '../../__tests__/mocks/optionServices';
import ApiRequestsHistory from '../ApiRequestsHistory';

import { fetchApiRequests } from '@/services/apiRequestsApi';
import { testQueryClient } from '@/setupTests';
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
vi.mock('@/services/apiRequestsApi', () => ({ fetchApiRequests: vi.fn() }));

// =============================================================================
// Helpers
// =============================================================================

const renderPage = () =>
  renderReportPage(ApiRequestsHistory, {
    route: '/reporting/api-requests',
    settingsKey: 'api_requests_history_page',
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

// =============================================================================
// Tests
// =============================================================================

describe('API Request History - results', () => {
  beforeAll(() => {
    stubIntersectionObserver();
  });

  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(fetchApiRequests).mockReset().mockResolvedValue(API_RESPONSE);
    setupOptionServiceMocks();
  });

  test.each([
    [
      'Audit',
      undefined,
      [
        'Date',
        'User Name',
        'User ID',
        'Application',
        'Request ID',
        'Method',
        'URL',
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
        'Application',
        'Request ID',
        'Method',
        'URL',
        'Level',
        'Details',
      ],
    ],
    [
      'Requests',
      'Requests',
      ['Date', 'UserName', 'User ID', 'Application', 'Request ID', 'Method', 'URL'],
    ],
  ])('the %s report shows its own columns', async (_label, type, expected) => {
    const user = userEvent.setup({ delay: null });
    renderPage();

    await runReport(user, type);

    expect(headings()).toEqual(expected);
  });

  test('the columns follow the type the report was run with', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await runReport(user);

    await user.click(screen.getByRole('button', { name: /filters/i }));
    await pickOption(user, /^report type$/i, 'Requests');

    expect(headings()).toContain('Entity');
  });

  test('the chosen type, user and request id reach the request', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await screen.findByRole('button', { name: /^apply$/i });

    await pickOption(user, /^user$/i, 'alice');
    await user.type(screen.getByRole('textbox', { name: /^request id$/i }), '77');
    await runReport(user, 'Requests');

    expect(fetchApiRequests).toHaveBeenLastCalledWith(
      expect.objectContaining({ type: 'requests', userId: 1, requestId: '77' }),
    );
  });

  test('View Details lists each saved property and its value', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await runReport(user);
    const row = getRow('alice');

    await user.click(within(row).getByRole('button', { name: 'View Details' }));

    const dialog = await screen.findByRole('dialog', { name: 'Details' });
    expect(within(dialog).getByRole('cell', { name: 'name' })).toBeInTheDocument();
    expect(within(dialog).getByRole('cell', { name: 'Lobby Layout' })).toBeInTheDocument();
  });

  test('a row with no details says "No details available."', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await runReport(user);
    const row = getRow('bob');

    await user.click(within(row).getByRole('button', { name: 'View Details' }));

    const dialog = await screen.findByRole('dialog', { name: 'Details' });
    expect(within(dialog).getByText('No details available.')).toBeInTheDocument();
  });
});
