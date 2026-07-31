/*
 * Copyright (C) 2026 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

import { screen, within, act, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { buildEvent } from '../../fixtures/event';
import {
  ALWAYS_ONLY,
  mockDaypartRows,
  setupCampaignMocks,
  setupCommandMocks,
  setupDatasetMocks,
  setupDaypartMocks,
  setupMediaMocks,
  setupPlaylistMocks,
  setupResolutionMocks,
  setupScheduleCriteriaMocks,
  setupSyncGroupMocks,
} from '../../mocks/api';
import { renderEventsPage } from '../helpers/renderEventsPage';

import { fetchLayouts } from '@/services/layoutsApi';
import { fetchUserPreference } from '@/services/userApi';
import { testQueryClient } from '@/setupTests';
import type { Event } from '@/types/event';
import type { Layout } from '@/types/layout';
import type * as PermissionsModule from '@/utils/permissions';

// =============================================================================
// SCENARIO TEST — this is deliberately different from the rest of the suite.
//
// Every other file in this tree mocks `useEventData`/`useEventActions` (or
// the modal components) so it can test ONE behaviour in isolation. This file
// mocks nothing above the network boundary: the real Events page, the real
// hooks, the real ScheduleEventModal and DeleteEventModal all run. Only leaf
// UI primitives that don't work in jsdom (popup menus, native date pickers,
// the Sync display table) are stubbed, same as every other modal test.
//
// The service layer is backed by a tiny in-memory store instead of static
// resolved values, so create/update/delete actually mutate what the next
// fetchEvent() call returns — the same way the real backend would. That's
// what makes this a "journey" test instead of a wiring test: after Finish,
// the table re-fetches for real and has to show the row that was actually
// created.
// =============================================================================

vi.mock('@/components/ui/modals/Modal');

vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));

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

// The fake backend: one array, mutated by the same calls the real page
// makes. `let store` is reset in beforeEach so tests don't leak state.
let store: Event[] = [];

vi.mock('@/services/eventApi', () => ({
  fetchEvent: vi.fn(async () => ({ rows: store, totalCount: store.length })),
  fetchEventById: vi.fn(async (eventId: number) => store.find((e) => e.eventId === eventId)!),
  createEvent: vi.fn(async (data: Partial<Event>) => {
    // buildEvent fills in every field the table/edit-form need (displayGroups,
    // recurrence*, etc.) so the row renders exactly like a real API response.
    const created = buildEvent({ ...data, eventId: 9001, name: 'Welcome Layout' });
    store = [...store, created];
    return created;
  }),
  updateEvent: vi.fn(async (eventId: number, data: Partial<Event>) => {
    store = store.map((e) => (e.eventId === eventId ? { ...e, ...data } : e));
    return store.find((e) => e.eventId === eventId)!;
  }),
  deleteEvent: vi.fn(async (eventId: number) => {
    store = store.filter((e) => e.eventId !== eventId);
  }),
}));

// SelectDropdown as a native <select> — same trick as steps/content.test.tsx
// — so the Layout content picker is queryable as a combobox.
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

vi.mock('@/components/ui/forms/DatePickerInput', () => ({
  default: ({ label }: { label?: string }) => <input aria-label={label} readOnly />,
}));
vi.mock('@/components/ui/GeoScheduleMap', () => ({
  default: () => <div role="img" aria-label="Geo schedule map" />,
}));
// Deliberately NOT mocking @/components/ui/table/DataTable — the modal
// tests do (to stub the Sync step's tiny display-picker table), but that
// import path is shared with the page's own events table, so doing it here
// would wipe out the real table this scenario needs to assert against.
// Safe to skip because this scenario never uses the Sync event type.
vi.mock('../../../components/DisplayGroupMultiSelect', () => ({
  DisplayGroupMultiSelect: ({
    onChange,
  }: {
    onChange: (v: { displaySpecificGroupIds: number[]; displayGroupIds: number[] }) => void;
  }) => (
    <button onClick={() => onChange({ displaySpecificGroupIds: [10], displayGroupIds: [] })}>
      Pick a display group
    </button>
  ),
}));
vi.mock('../../../components/EventCalendar', () => ({
  EventCalendar: () => <div data-testid="event-calendar" />,
}));
vi.mock('@/utils/permissions', async (importOriginal) => ({
  ...(await importOriginal<typeof PermissionsModule>()),
  hasFeature: vi.fn().mockReturnValue(true),
}));

// Same plain-button swap every other page-level test uses — the real
// dropdown menu library doesn't open in jsdom.
vi.mock('@/components/ui/table/DataTableRowActions', () => ({
  default: ({
    row,
    actions,
  }: {
    row: unknown;
    actions: Array<{ label?: string; onClick?: (r: unknown) => void; isSeparator?: boolean }>;
  }) => (
    <div>
      <button aria-label="More actions" />
      {actions
        .filter((a) => !a.isSeparator && a.label)
        .map((action, i) => (
          <button key={i} onClick={() => action.onClick?.(row)}>
            {action.label}
          </button>
        ))}
    </div>
  ),
}));

// =============================================================================
// Test
// =============================================================================

describe('Events page — create, edit, then delete (scenario)', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    store = [];

    setupDaypartMocks();
    setupResolutionMocks();
    setupCampaignMocks();
    setupCommandMocks();
    setupMediaMocks();
    setupPlaylistMocks();
    setupSyncGroupMocks();
    setupDatasetMocks();
    setupScheduleCriteriaMocks();
    mockDaypartRows(ALWAYS_ONLY);

    vi.mocked(fetchLayouts).mockResolvedValue({
      rows: [{ layoutId: 55, layout: 'Welcome Layout', campaignId: 100 } as unknown as Layout],
      totalCount: 1,
    });
    vi.mocked(fetchUserPreference).mockResolvedValue(null);
  });

  test('a user creates a layout event, renames it, then deletes it — each step reflects in the table', async () => {
    const user = userEvent.setup();

    await act(async () => {
      renderEventsPage();
    });
    await screen.findByRole('columnheader', { name: 'Name' });
    expect(screen.queryByRole('cell', { name: 'Welcome Layout' })).not.toBeInTheDocument();

    // ── Create ────────────────────────────────────────────────────────────
    await user.click(screen.getByRole('button', { name: /Add Event/ }));

    const addDialog = await screen.findByRole('dialog');
    const contentSelect = within(addDialog).getByRole('combobox', { name: 'Layout' });
    await user.selectOptions(contentSelect, '100'); // option value is campaignId, not layoutId
    await user.click(within(addDialog).getByRole('button', { name: 'Next' }));

    await user.click(
      await within(addDialog).findByRole('button', { name: 'Pick a display group' }),
    );
    await user.click(await within(addDialog).findByRole('button', { name: 'Finish' }));

    // The page re-fetched for real and the row we just created is in it.
    expect(await screen.findByRole('cell', { name: 'Welcome Layout' })).toBeInTheDocument();

    // ── Edit ──────────────────────────────────────────────────────────────
    await user.click(screen.getAllByRole('button', { name: 'Edit' })[0]!);
    const editDialog = await screen.findByRole('dialog');

    await user.click(await screen.findByText('Optional'));
    const nameInput = within(editDialog).getByRole('textbox', { name: 'Name' });
    await user.clear(nameInput);
    await user.type(nameInput, 'Welcome Layout (renamed)');
    await user.click(within(editDialog).getByRole('button', { name: 'Save' }));

    expect(
      await screen.findByRole('cell', { name: 'Welcome Layout (renamed)' }),
    ).toBeInTheDocument();
    expect(screen.queryByRole('cell', { name: 'Welcome Layout' })).not.toBeInTheDocument();

    // ── Delete ────────────────────────────────────────────────────────────
    await user.click(screen.getByRole('button', { name: 'More actions' }));
    await user.click(await screen.findByRole('button', { name: 'Delete' }));
    const deleteDialog = await screen.findByRole('dialog');
    await user.click(within(deleteDialog).getByRole('button', { name: /Yes, delete/ }));

    await waitFor(() => {
      expect(
        screen.queryByRole('cell', { name: 'Welcome Layout (renamed)' }),
      ).not.toBeInTheDocument();
    });
  });
});
