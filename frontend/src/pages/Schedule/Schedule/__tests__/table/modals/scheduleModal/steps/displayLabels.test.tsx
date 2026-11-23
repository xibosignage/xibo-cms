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

import { screen, waitFor, within } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockEvent } from '../../../../fixtures/event';
import {
  ALWAYS_AND_CUSTOM,
  mockDaypartRows,
  mockFetchEventById,
  setupScheduleModalMocks,
} from '../../../../mocks/api';
import { renderScheduleModal } from '../helpers/renderScheduleModal';

import { fetchDisplayGroups } from '@/services/displayGroupApi';
import { fetchDisplays } from '@/services/displaysApi';
import { testQueryClient } from '@/setupTests';

// ─── Mocks ────────────────────────────────────────────────────────────────────

vi.mock('@/components/ui/modals/Modal');

vi.mock('@/components/ui/forms/SelectDropdown', () => ({
  default: ({
    label,
    value,
    options,
    onSelect,
    placeholder,
  }: {
    label?: string;
    value?: string;
    options?: Array<{ value: string; label: string }>;
    onSelect?: (value: string) => void;
    placeholder?: string;
  }) => (
    <select aria-label={label} value={value ?? ''} onChange={(e) => onSelect?.(e.target.value)}>
      {placeholder !== undefined && <option value="">{placeholder}</option>}
      {options?.map((o) => (
        <option key={o.value} value={o.value}>
          {o.label}
        </option>
      ))}
    </select>
  ),
}));

vi.mock('@/services/eventApi');
vi.mock('@/services/daypartApi');
vi.mock('@/services/resolutionApi');
vi.mock('@/services/layoutsApi');
vi.mock('@/services/campaignApi');
vi.mock('@/services/commandApi');
vi.mock('@/services/mediaApi');
vi.mock('@/services/playlistApi');
vi.mock('@/services/syncGroupApi');
vi.mock('@/services/datasetApi');
vi.mock('@/services/scheduleCriteriaApi');

vi.mock('@/components/ui/forms/DatePickerInput', () => ({
  default: ({ label, value }: { label?: string; value?: string }) => (
    <input aria-label={label} value={value ?? ''} readOnly />
  ),
}));
vi.mock('@/components/ui/GeoScheduleMap', () => ({
  default: () => <div role="img" aria-label="Geo schedule map" />,
}));
vi.mock('@/components/ui/table/DataTable', () => ({
  DataTable: () => <table aria-label="Sync display table" />,
}));
vi.mock('@/services/displaysApi');
vi.mock('@/services/displayGroupApi');
vi.mock('@/components/ui/Notification', () => ({
  notify: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
}));
vi.mock('@/utils/permissions', () => ({
  hasFeature: vi.fn().mockReturnValue(true),
}));

// =============================================================================
// Tests
// =============================================================================

// Regression cover for xibosignage/xibo#3950. The event row already carries
// the names of its displays and groups, but the Displays picker only knew ids:
// it showed "Loading..." and then looked each one up again. Whatever that
// lookup didn't return was shown as its bare id, so an event opened with "5"
// where it should say "Lobby Screens".
//
// The picker here is the real one. Its lookups return nothing, which is what
// made the bug visible: if they returned the group, the old code would also
// end up showing its name.
describe('ScheduleEventModal - Displays step, editing an event', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();

    setupScheduleModalMocks();
    mockDaypartRows(ALWAYS_AND_CUSTOM);
    mockFetchEventById(mockEvent);
    vi.mocked(fetchDisplays).mockResolvedValue({ rows: [], totalCount: 0 });
    vi.mocked(fetchDisplayGroups).mockResolvedValue({ rows: [], totalCount: 0 });
  });

  const openDisplaysStep = async (user: UserEvent) => {
    renderScheduleModal({ mode: 'edit', event: mockEvent });
    await user.click(await screen.findByText('Displays'));
  };

  // Until a lookup comes back the picker says "Loading...".
  const waitForPickerToSettle = async () => {
    await waitFor(() => {
      expect(screen.queryByText('Loading...')).not.toBeInTheDocument();
    });
  };

  test("an edited event's display group is shown by name", async () => {
    const user = userEvent.setup();
    await openDisplaysStep(user);

    expect(await screen.findByText('Lobby Screens')).toBeInTheDocument();
  });

  test("an edited event's display group is never shown as its id", async () => {
    const user = userEvent.setup();
    await openDisplaysStep(user);
    await waitForPickerToSettle();

    const displayField = screen.getByText('Display', { selector: 'label' }).parentElement!;
    expect(within(displayField).queryByText(/(^|\D)5(\D|$)/)).not.toBeInTheDocument();
  });

  // The name comes from the event itself, so the picker has nothing to look up.
  test("the picker doesn't look up a group the event already names", async () => {
    const user = userEvent.setup();
    await openDisplaysStep(user);
    await waitForPickerToSettle();

    expect(fetchDisplayGroups).not.toHaveBeenCalledWith(
      expect.objectContaining({ displayGroupIds: [5] }),
    );
  });
});
