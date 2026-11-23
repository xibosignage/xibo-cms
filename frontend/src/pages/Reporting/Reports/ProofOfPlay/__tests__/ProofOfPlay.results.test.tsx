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
// Test type: Page integration — Proof of Play results table
// =============================================================================

import { screen, waitFor, within } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeAll, beforeEach, describe, test, expect } from 'vitest';

import { buildPopRow, POP_RESPONSE } from '../../__tests__/fixtures/reportRows';
import { stubIntersectionObserver } from '../../__tests__/helpers/domStubs';
import { applyButton, renderReportPage } from '../../__tests__/helpers/renderReportPage';
import { setupOptionServiceMocks } from '../../__tests__/mocks/optionServices';
import ProofOfPlay from '../ProofOfPlay';

import { fetchProofOfPlay } from '@/services/proofOfPlayApi';
import { testQueryClient } from '@/setupTests';
import { knownFailure } from '@/testUtils/knownFailure';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/proofOfPlayApi', () => ({ fetchProofOfPlay: vi.fn() }));
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
  fetchExportStatsCount: vi.fn().mockResolvedValue(0),
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

// =============================================================================
// Helpers
// =============================================================================

const runReport = async (user: UserEvent) => {
  renderReportPage(ProofOfPlay, {
    route: '/reporting/proof-of-play',
    settingsKey: 'proof_of_play_page',
  });
  await screen.findByRole('button', { name: /^apply$/i });
  await user.click(applyButton());
  return screen.findByRole('table');
};

const displayColumn = () =>
  screen
    .getAllByRole('row')
    .slice(1)
    .map((row) => within(row).getAllByRole('cell')[1]!.textContent);

const clickSort = async (user: UserEvent, header: RegExp) => {
  await user.click(within(screen.getByRole('columnheader', { name: header })).getByRole('button'));
};

// Short duration first: Back Office (59 s) should sort before Lobby (90061 s).
const DURATION_ROWS = {
  ...POP_RESPONSE,
  table: [buildPopRow(), buildPopRow({ displayId: 2, display: 'Back Office', duration: 59 })],
};

// =============================================================================
// Tests
// =============================================================================

describe('Proof of Play page - results', () => {
  beforeAll(() => {
    stubIntersectionObserver();
  });

  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(fetchProofOfPlay).mockReset().mockResolvedValue(POP_RESPONSE);
    setupOptionServiceMocks();
  });

  test('seven columns are hidden by default', async () => {
    const user = userEvent.setup({ delay: null });
    await runReport(user);

    for (const hidden of [
      /^display id$/i,
      /^display group id$/i,
      /^display group$/i,
      /^tag id$/i,
      /^tag name$/i,
      /^layout id$/i,
      /^widget id$/i,
    ]) {
      expect(screen.queryByRole('columnheader', { name: hidden })).not.toBeInTheDocument();
    }
    expect(screen.getByRole('columnheader', { name: /^display$/i })).toBeInTheDocument();
  });

  test('a hidden column can be switched on', async () => {
    const user = userEvent.setup({ delay: null });
    await runReport(user);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));
    await user.click(await screen.findByRole('checkbox', { name: /^widget id$/i }));

    await screen.findByRole('columnheader', { name: /^widget id$/i });
  });

  // 90061 s exercises every unit: 1 day, 1 hour, 1 minute, 1 second.
  test('total duration shows spelled out and in seconds', async () => {
    const user = userEvent.setup({ delay: null });
    await runReport(user);

    const lobby = screen.getByRole('row', { name: /lobby screen/i });
    expect(within(lobby).getByRole('cell', { name: '1day 1hr 1min 1sec' })).toBeInTheDocument();
    expect(within(lobby).getByRole('cell', { name: '90061' })).toBeInTheDocument();
  });

  test('the type is shown capitalised', async () => {
    const user = userEvent.setup({ delay: null });
    await runReport(user);

    expect(
      within(screen.getByRole('row', { name: /lobby screen/i })).getByRole('cell', {
        name: 'Layout',
      }),
    ).toBeInTheDocument();
    expect(
      within(screen.getByRole('row', { name: /back office/i })).getByRole('cell', {
        name: 'Media',
      }),
    ).toBeInTheDocument();
  });

  test('sorting by Number of Plays reorders the rows numerically', async () => {
    const user = userEvent.setup({ delay: null });
    await runReport(user);
    expect(displayColumn()).toEqual(['Lobby Screen', 'Back Office']);

    await clickSort(user, /^number of plays$/i);
    await waitFor(() => expect(displayColumn()).toEqual(['Lobby Screen', 'Back Office']));
    await clickSort(user, /^number of plays$/i);

    await waitFor(() => expect(displayColumn()).toEqual(['Back Office', 'Lobby Screen']));
  });

  // Known issue 4: xibosignage/xibo#4096 (duration column ids aren't row fields)
  describe('Pinned until https://github.com/xibosignage/xibo/issues/4096 is fixed', () => {
    knownFailure(
      'sorting by Total Duration reorders the rows',
      async () => {
        const user = userEvent.setup({ delay: null });
        vi.mocked(fetchProofOfPlay).mockResolvedValue(DURATION_ROWS);
        await runReport(user);
        // Control: unsorted, the long-running Lobby row comes first.
        expect(displayColumn()).toEqual(['Lobby Screen', 'Back Office']);
        await clickSort(user, /^total duration$/i);
      },
      () => expect(displayColumn()).toEqual(['Back Office', 'Lobby Screen']),
    );
  });
});
