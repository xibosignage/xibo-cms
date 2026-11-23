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
// Test type: Page integration — Proof of Play filters
// =============================================================================

import { screen, waitFor, within } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeAll, beforeEach, describe, test, expect } from 'vitest';

import { POP_RESPONSE } from '../../__tests__/fixtures/reportRows';
import { stubIntersectionObserver } from '../../__tests__/helpers/domStubs';
import {
  applyButton,
  pickOption,
  renderReportPage,
} from '../../__tests__/helpers/renderReportPage';
import { setupOptionServiceMocks } from '../../__tests__/mocks/optionServices';
import ProofOfPlay from '../ProofOfPlay';

import { fetchProofOfPlay } from '@/services/proofOfPlayApi';
import { testQueryClient } from '@/setupTests';

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

const renderPage = () =>
  renderReportPage(ProofOfPlay, {
    route: '/reporting/proof-of-play',
    settingsKey: 'proof_of_play_page',
  });

const lastRequest = () => {
  const calls = vi.mocked(fetchProofOfPlay).mock.calls;
  return calls[calls.length - 1]![0];
};

const apply = async (user: UserEvent) => {
  await user.click(applyButton());
  await waitFor(() => expect(fetchProofOfPlay).toHaveBeenCalled());
  return lastRequest();
};

const tickOptions = async (user: UserEvent, comboboxName: RegExp, labels: string[]) => {
  await user.click(screen.getByRole('combobox', { name: comboboxName }));
  for (const label of labels) {
    await user.click(await screen.findByRole('checkbox', { name: label }));
  }
  await user.keyboard('{Escape}');
};

// =============================================================================
// Tests
// =============================================================================

describe('Proof of Play page - filters', () => {
  beforeAll(() => {
    stubIntersectionObserver();
  });

  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(fetchProofOfPlay).mockReset().mockResolvedValue(POP_RESPONSE);
    setupOptionServiceMocks();
  });

  test.each([
    { name: 'Type', filter: /^type$/i, option: 'Media', sent: { type: 'media' } },
    { name: 'Group By', filter: /^group by$/i, option: 'Display', sent: { groupBy: 'display' } },
    { name: 'Display', filter: /^display$/i, option: 'Lobby Screen', sent: { displayId: 1 } },
    {
      name: 'Campaign',
      filter: /^campaign$/i,
      option: 'Spring Sale',
      sent: { parentCampaignId: 501 },
    },
    { name: 'Tags From', filter: /^tags from$/i, option: 'Layout', sent: { tagsType: 'layout' } },
  ])('the $name filter reaches the request', async ({ filter, option, sent }) => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await screen.findByRole('button', { name: /^apply$/i });

    await pickOption(user, filter, option);

    expect(await apply(user)).toEqual(expect.objectContaining(sent));
  });

  test('a preset date range reaches the request', { tags: ['smoke'] }, async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await screen.findByRole('button', { name: /^apply$/i });

    await user.click(screen.getByRole('button', { name: 'Last Month' }));

    expect(await apply(user)).toEqual(expect.objectContaining({ reportFilter: 'lastmonth' }));
  });

  test.each([
    {
      name: 'Layout',
      filter: /^layout$/i,
      options: ['Lobby Layout', 'Layout 2'],
      sent: { layoutId: [301, 302] },
    },
    { name: 'Media', filter: /^media$/i, options: ['Promo Video'], sent: { mediaId: [401] } },
    {
      name: 'Display Group',
      filter: /^display group$/i,
      options: ['Ground Floor'],
      sent: { displayGroupId: [201] },
    },
  ])('multi-selected $name items are sent as a list', async ({ filter, options, sent }) => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await screen.findByRole('button', { name: /^apply$/i });

    await tickOptions(user, filter, options);

    expect(await apply(user)).toEqual(expect.objectContaining(sent));
  });

  // Chip remove buttons have no accessible name (Known issue 19).
  test("a chip's remove button unticks that item", async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await screen.findByRole('button', { name: /^apply$/i });
    await tickOptions(user, /^layout$/i, ['Lobby Layout', 'Layout 2']);

    const layoutField = screen.getByRole('combobox', { name: /^layout$/i });
    expect(layoutField).toHaveTextContent('Lobby Layout');
    await user.click(within(layoutField).getAllByRole('button')[0]!);

    expect(layoutField).not.toHaveTextContent('Lobby Layout');
    expect(await apply(user)).toEqual(expect.objectContaining({ layoutId: [302] }));
  });

  test('exact tags is sent as false when untouched', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await screen.findByRole('button', { name: /^apply$/i });

    expect(await apply(user)).toEqual(expect.objectContaining({ exactTags: false }));
  });

  test('tags and exact tags reach the request', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await screen.findByRole('button', { name: /^apply$/i });

    await user.type(screen.getByRole('combobox', { name: 'Tags' }), 'lobby{Enter}');
    await user.click(screen.getByRole('button', { name: 'Match exact characters only' }));

    expect(await apply(user)).toEqual(expect.objectContaining({ tags: 'lobby', exactTags: true }));
  });

  test('the tags AND/OR choice reaches the request', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await screen.findByRole('button', { name: /^apply$/i });

    await user.type(screen.getByRole('combobox', { name: 'Tags' }), 'lobby{Enter}');
    await user.click(screen.getByRole('button', { name: /^or$/i }));
    await user.click(await screen.findByRole('button', { name: /^and$/i }));

    expect(await apply(user)).toEqual(
      expect.objectContaining({ tags: 'lobby', logicalOperator: 'AND' }),
    );
  });

  // A custom range is sent as day bounds plus the picked times; the preset is dropped.
  test('a custom range with times sends both dates and both times', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await screen.findByRole('button', { name: /^apply$/i });

    await user.click(screen.getByRole('button', { name: 'Custom range' }));
    const request = await apply(user);

    expect(request).toEqual(
      expect.objectContaining({
        statsFromDt: '2027-01-01 00:00:00',
        statsToDt: '2027-01-07 23:59:59',
        statsFromDtTime: '09:30',
        statsToDtTime: '17:00',
      }),
    );
    expect(request.reportFilter).toBeUndefined();
  });
});
