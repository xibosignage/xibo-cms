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

import { screen } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { buildEvent } from '../../../../fixtures/event';
import {
  ALWAYS_AND_CUSTOM,
  mockDaypartRows,
  mockFetchEventById,
  setupScheduleModalMocks,
} from '../../../../mocks/api';
import { renderScheduleModal } from '../helpers/renderScheduleModal';

import { testQueryClient } from '@/setupTests';
import { EventTypeId } from '@/types/event';

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

// Renders the value it's given, so a test can see what a date field holds.
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
vi.mock('@/pages/Schedule/Schedule/components/DisplayGroupMultiSelect', () => ({
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
vi.mock('@/components/ui/Notification', () => ({
  notify: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
}));
vi.mock('@/utils/permissions', () => ({
  hasFeature: vi.fn().mockReturnValue(true),
}));

// =============================================================================
// Helpers
// =============================================================================

const renderOnTimeStepViaSync = async () => {
  renderScheduleModal({
    mode: 'add',
    eventTypeId: EventTypeId.Sync,
    contentId: 42, // syncGroupId
  });
  await screen.findByRole('combobox', { name: 'Dayparting' });
};

const renderAndAdvanceToTimeStep = async (user: UserEvent, eventTypeId: EventTypeId) => {
  renderScheduleModal({ mode: 'add', eventTypeId, contentId: 42 });

  await user.click(await screen.findByRole('button', { name: 'Pick a display group' }));
  await user.click(screen.getByRole('button', { name: 'Next' }));
};

// =============================================================================
// Tests
// =============================================================================

describe('ScheduleEventModal - Step 2 (Time)', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();

    setupScheduleModalMocks();

    // Realistic daypart list: Always + Custom + a regular named daypart.
    mockDaypartRows(ALWAYS_AND_CUSTOM);
  });

  test('with the Always daypart selected, no date or time fields are shown', async () => {
    await renderOnTimeStepViaSync();

    expect(screen.queryByLabelText('Start Time')).not.toBeInTheDocument();
    expect(screen.queryByLabelText('End Time')).not.toBeInTheDocument();
  });

  test('switching to the Custom daypart reveals both Start Time and End Time fields', async () => {
    const user = userEvent.setup();
    await renderOnTimeStepViaSync();

    const daypart = screen.getByRole('combobox', { name: 'Dayparting' });

    await user.selectOptions(daypart, '2');

    expect(screen.getByLabelText('Start Time')).toBeInTheDocument();
    expect(screen.getByLabelText('End Time')).toBeInTheDocument();
  });

  test('Command event type hides the daypart selector entirely', async () => {
    const user = userEvent.setup();
    await renderAndAdvanceToTimeStep(user, EventTypeId.Command);

    expect(screen.queryByRole('combobox', { name: 'Dayparting' })).not.toBeInTheDocument();
  });

  test('Command event type shows only a Start Time field, no End Time', async () => {
    const user = userEvent.setup();
    await renderAndAdvanceToTimeStep(user, EventTypeId.Command);

    expect(screen.getByLabelText('Start Time')).toBeInTheDocument();
    expect(screen.queryByLabelText('End Time')).not.toBeInTheDocument();
  });

  test('selecting a named (non-Always, non-Custom) daypart shows only a Start Time field', async () => {
    const user = userEvent.setup();
    await renderOnTimeStepViaSync();

    const daypart = screen.getByRole('combobox', { name: 'Dayparting' });
    await user.selectOptions(daypart, '3'); // Morning Slot

    expect(screen.getByLabelText('Start Time')).toBeInTheDocument();
    expect(screen.queryByLabelText('End Time')).not.toBeInTheDocument();
  });

  test('switching on "Use Relative Time" for a Custom daypart reveals Hours/Minutes/Seconds inputs and the live preview', async () => {
    const user = userEvent.setup();
    await renderOnTimeStepViaSync();

    const daypart = screen.getByRole('combobox', { name: 'Dayparting' });
    await user.selectOptions(daypart, '2');
    await user.click(screen.getByRole('checkbox', { name: 'Use Relative Time' }));

    const hours = screen.getByRole('spinbutton', { name: 'Hours' });
    expect(hours).toBeInTheDocument();
    expect(screen.getByRole('spinbutton', { name: 'Minutes' })).toBeInTheDocument();
    expect(screen.getByRole('spinbutton', { name: 'Seconds' })).toBeInTheDocument();

    await user.clear(hours);
    await user.type(hours, '1');
    expect(screen.getByText(/Event Schedule:/i)).toBeInTheDocument();
  });

  test('Interrupt events show a Share of Voice field with an "As a percentage" sibling', async () => {
    const user = userEvent.setup();
    await renderAndAdvanceToTimeStep(user, EventTypeId.Interrupt);

    expect(screen.getByRole('spinbutton', { name: 'Share of Voice' })).toBeInTheDocument();
    expect(screen.getByText(/As a percentage/i)).toBeInTheDocument();
  });
});

// =============================================================================
// Leaving Always - xibosignage/xibo#3940
// =============================================================================

// The CMS stores an Always event as running from 0 to 2147483647, so editing
// one loads an End Time of 19 January 2038. Before #3940, switching such an
// event to another daypart kept that date, and the user was shown a
// pre-filled end date they never chose.

/** Open an existing event and go to the Time step. */
const editOnTimeStep = async (user: UserEvent, event: ReturnType<typeof buildEvent>) => {
  mockFetchEventById(event);
  renderScheduleModal({ mode: 'edit', event });

  await user.click(await screen.findByText('Time'));
  await screen.findByRole('combobox', { name: 'Dayparting' });
};

describe('ScheduleEventModal - Time step, leaving Always', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();

    setupScheduleModalMocks();
    mockDaypartRows(ALWAYS_AND_CUSTOM);
  });

  test('switching an Always event to Custom leaves Start Time and End Time empty', async () => {
    const user = userEvent.setup();
    await editOnTimeStep(
      user,
      buildEvent({ campaignId: 42, dayPartId: 1, isAlways: 1, fromDt: 0, toDt: 2147483647 }),
    );

    await user.selectOptions(screen.getByRole('combobox', { name: 'Dayparting' }), '2');

    expect(screen.getByLabelText('Start Time')).toHaveValue('');
    expect(screen.getByLabelText('End Time')).toHaveValue('');
  });

  // Only leaving Always clears the dates: a Custom event's own dates are real
  // and must survive a change of daypart.
  test('switching a Custom event to a named daypart keeps its Start Time', async () => {
    const user = userEvent.setup();
    const event = buildEvent({ campaignId: 42, dayPartId: 2, isCustom: 1 });
    await editOnTimeStep(user, event);

    const startTime = (screen.getByLabelText('Start Time') as HTMLInputElement).value;
    expect(startTime).not.toBe('');

    await user.selectOptions(screen.getByRole('combobox', { name: 'Dayparting' }), '3');

    expect(screen.getByLabelText('Start Time')).toHaveValue(startTime);
  });
});
