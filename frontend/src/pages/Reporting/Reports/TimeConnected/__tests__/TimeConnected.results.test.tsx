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
// Test type: Page integration + component — Time Connected results
// =============================================================================

import { render, screen, waitFor } from '@testing-library/react';
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
import TimeConnectedRow from '../components/TimeConnectedRow';

import { fetchTimeConnected } from '@/services/timeConnectedApi';
import { testQueryClient } from '@/setupTests';
import { knownFailure } from '@/testUtils/knownFailure';

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

const runReport = async (user: UserEvent) => {
  renderReportPage(TimeConnected, {
    route: '/reporting/time-connected',
    settingsKey: 'timeconnected_report_page',
  });
  await screen.findByRole('button', { name: /^apply$/i });
  await user.click(applyButton());
  await screen.findByText('Back Office');
};

const displayOrder = () =>
  screen.getAllByText(/^(Lobby Screen|Back Office)$/).map((el) => el.textContent);

const bandFor = (uptime: number) => {
  const { unmount } = render(
    <TimeConnectedRow
      row={{
        displayId: 1,
        displayName: `Display ${uptime}`,
        lastAccessed: null,
        periods: [],
        uptimePercent: uptime,
      }}
    />,
  );
  const band = screen.getByText(`${uptime.toFixed(1)}%`).nextSibling?.textContent?.trim();
  unmount();
  return band;
};

// =============================================================================
// Tests
// =============================================================================

describe('Time Connected page - results', () => {
  beforeAll(() => {
    stubIntersectionObserver();
  });

  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(fetchTimeConnected).mockReset().mockResolvedValue(TIME_CONNECTED_RESPONSE);
    setupOptionServiceMocks();
  });

  // Bands: 0 offline, under 70 poor, 70–89 fair, 90+ good.
  test('uptime is announced by band at each boundary', () => {
    expect(bandFor(0)).toBe('Offline');
    expect(bandFor(50)).toBe('Poor');
    expect(bandFor(69)).toBe('Poor');
    expect(bandFor(70)).toBe('Fair');
    expect(bandFor(89)).toBe('Fair');
    expect(bandFor(90)).toBe('Good');
    expect(bandFor(100)).toBe('Good');
  });

  test('a fractional uptime is announced by the band of its shown percentage', () => {
    expect(bandFor(89.96)).toBe('Good');
    expect(bandFor(69.96)).toBe('Fair');
    expect(bandFor(0.03)).toBe('Offline');
  });

  test('the online and offline shares always add up to 100%', async () => {
    const user = userEvent.setup({ delay: null });
    render(
      <TimeConnectedRow
        row={{
          displayId: 1,
          displayName: 'Lobby Screen',
          lastAccessed: null,
          periods: [],
          uptimePercent: 33.35,
        }}
      />,
    );

    await user.click(screen.getByRole('button', { name: /lobby screen/i }));

    expect(document.body).toHaveTextContent('Online: 33.4%');
    expect(document.body).toHaveTextContent('Offline: 66.6%');
  });

  test('a missing uptime shows as fully offline, not NaN', async () => {
    const user = userEvent.setup({ delay: null });
    render(
      <TimeConnectedRow
        row={{
          displayId: 1,
          displayName: 'Lobby Screen',
          lastAccessed: null,
          periods: [],
          uptimePercent: NaN,
        }}
      />,
    );

    await user.click(screen.getByRole('button', { name: /lobby screen/i }));

    expect(document.body).toHaveTextContent('Online: 0.0%');
    expect(document.body).toHaveTextContent('Offline: 100.0%');
    expect(document.body).not.toHaveTextContent('NaN');
  });

  test('clicking a display shows its online and offline share and each period', async () => {
    const user = userEvent.setup({ delay: null });
    await runReport(user);

    await user.click(screen.getByRole('button', { name: /back office/i }));

    await screen.findByText('Connectivity by Period');
    expect(document.body).toHaveTextContent('Online: 25.0%');
    expect(document.body).toHaveTextContent('Offline: 75.0%');
    expect(screen.getByText('1 Jan')).toBeInTheDocument();
    expect(screen.getByText('50.0%')).toBeInTheDocument();
  });

  test('rows follow the applied Sort By: high to low by default, low to high when chosen', async () => {
    const user = userEvent.setup({ delay: null });
    await runReport(user);
    expect(displayOrder()).toEqual(['Lobby Screen', 'Back Office']);

    await user.click(screen.getByRole('button', { name: /filters/i }));
    await pickOption(user, /^sort by$/i, 'Uptime (low to high)');
    await user.click(applyButton());

    await waitFor(() => expect(displayOrder()).toEqual(['Back Office', 'Lobby Screen']));
  });

  // Known issue 5: xibosignage/xibo#4098 (list re-sorts before Apply)
  describe('Pinned until https://github.com/xibosignage/xibo/issues/4098 is fixed', () => {
    knownFailure(
      'changing Sort By only re-sorts after Apply',
      async () => {
        const user = userEvent.setup({ delay: null });
        await runReport(user);
        // Control: applied high to low.
        expect(displayOrder()).toEqual(['Lobby Screen', 'Back Office']);
        await user.click(screen.getByRole('button', { name: /filters/i }));
        await pickOption(user, /^sort by$/i, 'Uptime (low to high)');
      },
      () => expect(displayOrder()).toEqual(['Lobby Screen', 'Back Office']),
    );
  });

  // Fixed in xibosignage/xibo#4108.
  test('a display row can be reached with Tab and opened with Enter', async () => {
    const user = userEvent.setup({ delay: null });
    render(
      <TimeConnectedRow
        row={{
          displayId: 1,
          displayName: 'Lobby Screen',
          lastAccessed: null,
          periods: [],
          uptimePercent: 25,
        }}
      />,
    );

    await user.tab();
    await user.keyboard('{Enter}');

    expect(screen.getByRole('button', { name: /lobby screen/i, expanded: true })).toHaveFocus();
    expect(document.body).toHaveTextContent('Online: 25.0%');
  });
});
