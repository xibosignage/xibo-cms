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

import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { ALWAYS_AND_CUSTOM, mockDaypartRows, setupScheduleModalMocks } from '../../../../mocks/api';
import { renderScheduleModal } from '../helpers/renderScheduleModal';

import { createEvent } from '@/services/eventApi';
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

vi.mock('@/components/ui/forms/DatePickerInput', () => ({
  default: ({ label }: { label?: string }) => <input aria-label={label} readOnly />,
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

// Walk: Content (pre-filled via contentId) -> Displays (pick one) -> Time
// (switch to Custom, use relative time so Next unblocks without a working
// date picker) -> Optional (set a Weekly repeat) -> Back to Time (flip the
// daypart back to Always, which is meant to hide/disable repeats) ->
// Optional again. Lands with the Repeats tab supposedly no longer relevant.
const walkToOptionalWithStaleWeeklyRepeat = async (user: ReturnType<typeof userEvent.setup>) => {
  renderScheduleModal({
    mode: 'add',
    eventTypeId: EventTypeId.Layout,
    contentId: 42,
  });

  // Step 0 (pre-filled) -> Step 1 (Displays)
  await user.click(await screen.findByRole('button', { name: 'Pick a display group' }));
  // Step 1 -> Step 2 (Time)
  await user.click(screen.getByRole('button', { name: 'Next' }));

  // Switch to the Custom daypart so Repeats/Reminder become relevant.
  await user.selectOptions(screen.getByRole('combobox', { name: 'Dayparting' }), '2');
  // Use relative time so isTimeStepValid is satisfied without a working
  // (stubbed, read-only) date picker.
  await user.click(screen.getByRole('checkbox', { name: 'Use Relative Time' }));
  const hours = screen.getByRole('spinbutton', { name: 'Hours' });
  await user.clear(hours);
  await user.type(hours, '1');

  // Step 2 -> Step 3 (Optional)
  await user.click(screen.getByRole('button', { name: 'Next' }));

  // Configure a weekly repeat.
  await user.click(screen.getByRole('button', { name: 'Repeats' }));
  await user.selectOptions(screen.getByRole('combobox', { name: 'Repeats' }), 'Week');

  // Go back to Time and flip the daypart to Always - this is supposed to
  // make the repeat irrelevant.
  await user.click(screen.getByRole('button', { name: 'Back' }));
  await user.selectOptions(screen.getByRole('combobox', { name: 'Dayparting' }), '1');

  // Forward again to Optional.
  await user.click(screen.getByRole('button', { name: 'Next' }));
};

// Same walk, but via the Reminder tab instead of Repeats - the Reminder
// tab button is gated by the exact same `showRepeatReminder && canSetReminders`
// condition as Repeats, so it's expected to exhibit the identical bug.
const walkToOptionalWithStaleReminder = async (user: ReturnType<typeof userEvent.setup>) => {
  renderScheduleModal({
    mode: 'add',
    eventTypeId: EventTypeId.Layout,
    contentId: 42,
  });

  await user.click(await screen.findByRole('button', { name: 'Pick a display group' }));
  await user.click(screen.getByRole('button', { name: 'Next' }));

  await user.selectOptions(screen.getByRole('combobox', { name: 'Dayparting' }), '2');
  await user.click(screen.getByRole('checkbox', { name: 'Use Relative Time' }));
  const hours = screen.getByRole('spinbutton', { name: 'Hours' });
  await user.clear(hours);
  await user.type(hours, '1');

  await user.click(screen.getByRole('button', { name: 'Next' }));

  // Configure a reminder (the reminder-value NumberInput has no accessible
  // label - it's rendered inline - so it's the only spinbutton on this tab).
  await user.click(screen.getByRole('button', { name: 'Reminder' }));
  const reminderValue = screen.getByRole('spinbutton');
  await user.clear(reminderValue);
  await user.type(reminderValue, '15');

  await user.click(screen.getByRole('button', { name: 'Back' }));
  await user.selectOptions(screen.getByRole('combobox', { name: 'Dayparting' }), '1');

  await user.click(screen.getByRole('button', { name: 'Next' }));
};

// =============================================================================
// Tests
// =============================================================================

describe('ScheduleEventModal - Optional tab state after switching daypart back to Always', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();

    setupScheduleModalMocks();

    // Always (1) + Custom (2) + a named daypart (3), matching the fixture
    // used elsewhere in this suite (steps/time.test.tsx).
    mockDaypartRows(ALWAYS_AND_CUSTOM);
  });

  // This half of the tab-visibility gate already works correctly today -
  // confirmed by running this assertion on its own (see the sibling
  // test.fails below for the half that's actually broken). Kept as a normal
  // test: it documents real, already-correct behavior and stays true
  // whether or not the bug below ever gets fixed.
  test('the Repeats tab button disappears once the daypart is switched back to Always', async () => {
    const user = userEvent.setup();
    await walkToOptionalWithStaleWeeklyRepeat(user);

    expect(screen.queryByRole('button', { name: 'Repeats' })).not.toBeInTheDocument();
  });

  // The Repeats tab button correctly disappears (see test above) - but
  // nothing ever resets `optionalTab` away from 'repeats', so the Repeats
  // tab's own form controls are left rendered with no active tab button
  // pointing at them. See
  // bugs-found-merged/schedule-modal-stale-repeat-reminder-survives-daypart-switch-to-always.md.
  // Kept as test.fails (not test.skip) so a real fix surfaces here immediately.
  test.fails(
    'the Repeats tab body should not remain rendered once its tab button is hidden',
    async () => {
      const user = userEvent.setup();
      await walkToOptionalWithStaleWeeklyRepeat(user);

      expect(screen.queryByRole('combobox', { name: 'Repeats' })).not.toBeInTheDocument();
    },
  );

  // The stale weekly repeat set while on the Custom daypart should not
  // survive switching back to Always - the user has no way left in the UI
  // to see or clear it, so it must not be part of the saved event. See
  // bugs-found-merged/schedule-modal-stale-repeat-reminder-survives-daypart-switch-to-always.md.
  // Kept as test.fails (not test.skip) so a real fix surfaces here immediately.
  test.fails(
    'a repeat configured before switching back to Always must not be sent to createEvent',
    async () => {
      const user = userEvent.setup();
      await walkToOptionalWithStaleWeeklyRepeat(user);

      await user.click(screen.getByRole('button', { name: 'Finish' }));

      expect(createEvent).toHaveBeenCalledTimes(1);
      const payload = vi.mocked(createEvent).mock.calls[0]![0];

      expect(payload.recurrenceType).toBeUndefined();
      expect(payload.recurrenceDetail).toBeUndefined();
    },
  );

  // Same already-correct half of the gate as the Repeats button test above,
  // for the Reminder tab. Kept as a normal test for the same reason.
  test('the Reminder tab button disappears once the daypart is switched back to Always', async () => {
    const user = userEvent.setup();
    await walkToOptionalWithStaleReminder(user);

    expect(screen.queryByRole('button', { name: 'Reminder' })).not.toBeInTheDocument();
  });

  // The Reminder tab is gated by the same condition as Repeats, so it has
  // the identical bug: its body keeps rendering with no active tab button.
  // See bugs-found-merged/schedule-modal-stale-repeat-reminder-survives-daypart-switch-to-always.md.
  // Kept as test.fails (not test.skip) so a real fix surfaces here immediately.
  test.fails(
    'the Reminder tab body should not remain rendered once its tab button is hidden',
    async () => {
      const user = userEvent.setup();
      await walkToOptionalWithStaleReminder(user);

      expect(screen.queryByRole('spinbutton')).not.toBeInTheDocument();
    },
  );

  // A reminder configured while on the Custom daypart should not survive
  // switching back to Always - same data-integrity issue as the repeat. See
  // bugs-found-merged/schedule-modal-stale-repeat-reminder-survives-daypart-switch-to-always.md.
  // Kept as test.fails (not test.skip) so a real fix surfaces here immediately.
  test.fails(
    'a reminder configured before switching back to Always must not be sent to createEvent',
    async () => {
      const user = userEvent.setup();
      await walkToOptionalWithStaleReminder(user);

      await user.click(screen.getByRole('button', { name: 'Finish' }));

      expect(createEvent).toHaveBeenCalledTimes(1);
      const payload = vi.mocked(createEvent).mock.calls[0]![0];

      expect(payload.scheduleReminders).toBeUndefined();
    },
  );
});
