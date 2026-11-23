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
// Test type: Page integration — Time Connected filters
// =============================================================================

import { screen, waitFor } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeAll, beforeEach, describe, test, expect } from 'vitest';

import { TIME_CONNECTED_RESPONSE } from '../../__tests__/fixtures/reportRows';
import { stubIntersectionObserver } from '../../__tests__/helpers/domStubs';
import {
  applyButton,
  pickOption,
  renderReportPage,
} from '../../__tests__/helpers/renderReportPage';
import { setupOptionServiceMocks } from '../../__tests__/mocks/optionServices';
import TimeConnected from '../TimeConnected';

import { fetchTimeConnected } from '@/services/timeConnectedApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/timeConnectedApi', () => ({ fetchTimeConnected: vi.fn() }));
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
// Stub range picker: one button per preset plus a custom range.
vi.mock('@/components/ui/DateRangeFilter', () => ({
  default: ({
    label,
    name,
    options,
    onChange,
  }: {
    label: string;
    name: string;
    options: Array<{ label: string; value: string }>;
    onChange: (n: string, v: string) => void;
  }) => (
    <div>
      <span>{label}</span>
      {options.map((opt) => (
        <button key={opt.value} type="button" onClick={() => onChange(name, opt.value)}>
          {opt.label}
        </button>
      ))}
      <button
        type="button"
        onClick={() => onChange(name, 'range:2027-01-01T09:30|2027-01-07T17:00')}
      >
        Custom range
      </button>
    </div>
  ),
}));
// Stub display picker: one button per change shape.
vi.mock('@/pages/Schedule/Schedule/components/DisplayGroupMultiSelect', () => ({
  DisplayGroupMultiSelect: ({
    onChange,
  }: {
    onChange: (v: { displaySpecificGroupIds: number[]; displayGroupIds: number[] }) => void;
  }) => (
    <>
      <button
        type="button"
        onClick={() => onChange({ displaySpecificGroupIds: [101], displayGroupIds: [201, 202] })}
      >
        Pick a display and two groups
      </button>
      <button
        type="button"
        onClick={() => onChange({ displaySpecificGroupIds: [], displayGroupIds: [] })}
      >
        Clear displays
      </button>
    </>
  ),
}));

// =============================================================================
// Helpers
// =============================================================================

const renderPage = () =>
  renderReportPage(TimeConnected, {
    route: '/reporting/time-connected',
    settingsKey: 'timeconnected_report_page',
  });

const apply = async (user: UserEvent) => {
  await user.click(applyButton());
  await waitFor(() => expect(fetchTimeConnected).toHaveBeenCalled());
  const calls = vi.mocked(fetchTimeConnected).mock.calls;
  return calls[calls.length - 1]![0];
};

// =============================================================================
// Tests
// =============================================================================

describe('Time Connected page - filters', () => {
  beforeAll(() => {
    stubIntersectionObserver();
  });

  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(fetchTimeConnected).mockReset().mockResolvedValue(TIME_CONNECTED_RESPONSE);
    setupOptionServiceMocks();
  });

  test('the range and Group By reach the request', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await screen.findByRole('button', { name: /^apply$/i });

    await user.click(screen.getByRole('button', { name: 'Last Month' }));
    await pickOption(user, /^group by$/i, 'Hour');

    expect(await apply(user)).toEqual(
      expect.objectContaining({ reportFilter: 'lastmonth', groupByFilter: 'byhour' }),
    );
  });

  test('a custom range is sent as day bounds', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await screen.findByRole('button', { name: /^apply$/i });

    await user.click(screen.getByRole('button', { name: 'Custom range' }));
    const request = await apply(user);

    expect(request).toEqual(
      expect.objectContaining({ fromDt: '2027-01-01 00:00:00', toDt: '2027-01-07 23:59:59' }),
    );
    expect(request.reportFilter).toBeUndefined();
  });

  // The server takes one list; displays (their own groups) come first, then groups.
  test('displays and display groups are sent as one list', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await screen.findByRole('button', { name: /^apply$/i });

    await user.click(screen.getByRole('button', { name: 'Pick a display and two groups' }));

    expect(await apply(user)).toEqual(expect.objectContaining({ displayGroupId: [101, 201, 202] }));
  });

  test('with no displays chosen, no display list is sent', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await screen.findByRole('button', { name: /^apply$/i });

    expect((await apply(user)).displayGroupId).toBeUndefined();
  });
});
