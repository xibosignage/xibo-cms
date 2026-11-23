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
// Test type: Page integration — Bandwidth filters
// =============================================================================

import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, afterAll, afterEach, beforeAll, beforeEach, describe, test, expect } from 'vitest';

import { BANDWIDTH_RESPONSE } from '../../__tests__/fixtures/reportRows';
import { stubIntersectionObserver } from '../../__tests__/helpers/domStubs';
import {
  applyButton,
  pickOption,
  renderReportPage,
} from '../../__tests__/helpers/renderReportPage';
import { setupOptionServiceMocks } from '../../__tests__/mocks/optionServices';
import Bandwidth from '../Bandwidth';

import { fetchBandwidthReport } from '@/services/bandwidthReportApi';
import { testQueryClient } from '@/setupTests';
import { knownFailure } from '@/testUtils/knownFailure';
import { setTimezone } from '@/testUtils/timezone';

// Default months are set at module load, so pin the clock and TZ before any import
const { originalTz } = vi.hoisted(() => {
  const tz = process.env.TZ;
  process.env.TZ = 'UTC';
  vi.setSystemTime(new Date('2027-03-15T10:00:00Z'));
  return { originalTz: tz };
});

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/bandwidthReportApi', () => ({ fetchBandwidthReport: vi.fn() }));
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
// Stub month picker: plain input plus a Clear button.
vi.mock('@/components/ui/forms/MonthPickerInput', () => ({
  default: ({
    label,
    value,
    onChange,
  }: {
    label: string;
    value: string;
    onChange: (v: string) => void;
  }) => (
    <div>
      <input aria-label={label} value={value} onChange={(e) => onChange(e.target.value)} />
      <button type="button" onClick={() => onChange('')}>
        {`Clear ${label}`}
      </button>
    </div>
  ),
}));

// =============================================================================
// Helpers
// =============================================================================

const MARCH_2027 = new Date(2027, 2, 1).toISOString();

const renderPage = () =>
  renderReportPage(Bandwidth, {
    route: '/reporting/bandwidth',
    settingsKey: 'bandwidth_report_page',
  });

const lastRequest = () => {
  const calls = vi.mocked(fetchBandwidthReport).mock.calls;
  return calls[calls.length - 1]![0];
};

// =============================================================================
// Tests
// =============================================================================

describe('Bandwidth page - filters', () => {
  beforeAll(() => {
    stubIntersectionObserver();
  });
  afterAll(() => {
    vi.useRealTimers();
    setTimezone(originalTz);
  });

  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(fetchBandwidthReport).mockReset().mockResolvedValue(BANDWIDTH_RESPONSE);
    setupOptionServiceMocks();
  });

  test('both months default to the current month', async () => {
    renderPage();

    expect(await screen.findByRole('textbox', { name: 'From Date' })).toHaveValue(MARCH_2027);
    expect(screen.getByRole('textbox', { name: 'To Date' })).toHaveValue(MARCH_2027);
  });

  test('Apply stays disabled until both months are chosen', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await screen.findByRole('textbox', { name: 'From Date' });
    expect(applyButton()).toBeEnabled();

    await user.click(screen.getByRole('button', { name: 'Clear To Date' }));
    expect(applyButton()).toBeDisabled();

    await user.click(screen.getByRole('button', { name: 'Clear From Date' }));
    await user.click(screen.getByRole('textbox', { name: 'To Date' }));
    await user.paste(MARCH_2027);
    expect(applyButton()).toBeDisabled();
  });

  test('the months and display reach the request', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await screen.findByRole('textbox', { name: 'From Date' });

    await pickOption(user, /^display$/i, 'Lobby Screen');
    await user.click(applyButton());

    await waitFor(() => expect(fetchBandwidthReport).toHaveBeenCalled());
    expect(lastRequest()).toEqual(
      expect.objectContaining({
        fromDt: '2027-03-01 00:00:00',
        toDt: '2027-03-01 00:00:00',
        displayId: 1,
      }),
    );
  });

  // Known issue 8: xibosignage/xibo#4100 (default month fixed at module load)
  describe('Pinned until https://github.com/xibosignage/xibo/issues/4100 is fixed', () => {
    // The pin moves the clock to April; later tests expect March.
    afterEach(() => {
      vi.setSystemTime(new Date('2027-03-15T10:00:00Z'));
    });

    knownFailure(
      'Reset restores the month it is now, not the month the page was opened in',
      async () => {
        const user = userEvent.setup({ delay: null });
        renderPage();
        await screen.findByRole('textbox', { name: 'From Date' });
        vi.setSystemTime(new Date('2027-04-02T10:00:00Z'));
        await user.click(screen.getByRole('button', { name: /^reset$/i }));
        return screen.getByRole('textbox', { name: 'From Date' });
      },
      (fromDate) => expect(fromDate).toHaveValue(new Date(2027, 3, 1).toISOString()),
    );
  });
});
