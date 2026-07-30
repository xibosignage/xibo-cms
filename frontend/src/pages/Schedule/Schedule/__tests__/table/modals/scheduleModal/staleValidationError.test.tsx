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

import { buildEvent } from '../../../fixtures/event';
import {
  ALWAYS_ONLY,
  mockDaypartRows,
  mockFetchEventById,
  setupScheduleModalMocks,
} from '../../../mocks/api';

import { renderScheduleModal } from './helpers/renderScheduleModal';

import { testQueryClient } from '@/setupTests';
import { EventTypeId } from '@/types/event';

// ─── Mocks ────────────────────────────────────────────────────────────────────

vi.mock('@/components/ui/modals/Modal');

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

// SelectDropdown rendered as a native <select>, with the `error` prop wired
// up to an accessible description - matches validationRouting.test.tsx's
// override so we can assert on the "Please select a Command" message.
vi.mock('@/components/ui/forms/SelectDropdown', () => ({
  default: ({
    label,
    value,
    options,
    onSelect,
    placeholder,
    error,
  }: {
    label?: string;
    value?: string;
    options?: Array<{ value: string; label: string }>;
    onSelect?: (value: string) => void;
    placeholder?: string;
    error?: string;
  }) => {
    const errorId = error ? `${label}-error` : undefined;
    return (
      <>
        <select
          aria-label={label}
          aria-describedby={errorId}
          value={value ?? ''}
          onChange={(e) => onSelect?.(e.target.value)}
        >
          {placeholder !== undefined && <option value="">{placeholder}</option>}
          {options?.map((o) => (
            <option key={o.value} value={o.value}>
              {o.label}
            </option>
          ))}
        </select>
        {error && (
          <span id={errorId} role="status">
            {error}
          </span>
        )}
      </>
    );
  },
}));

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
// Tests
// =============================================================================

describe('ScheduleEventModal - stale formErrors after switching Event Type', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();

    setupScheduleModalMocks();
    mockDaypartRows(ALWAYS_ONLY);
  });

  // formErrors is only ever cleared at the start of handleFinish or in
  // handleClose - never when the user changes Event Type. Both the
  // Command-type content dropdown and the Action-type "Command" sub-field
  // read the exact same `formErrors.commandId` key, so a validation error
  // earned in one context bleeds into the other.
  test('a "Please select a Command" error from a Command-type event should not resurface on the unrelated Action-type Command field', async () => {
    const user = userEvent.setup();

    // Edit an existing Command-type event that already has a display and a
    // valid daypart, but clear its Command selection so Save fails
    // validation on commandId specifically (not on some other field).
    const commandEvent = buildEvent({
      eventTypeId: EventTypeId.Command,
      commandId: 5,
      dayPartId: 1,
      isCustom: 1,
    });
    mockFetchEventById(commandEvent);

    renderScheduleModal({ mode: 'edit', event: commandEvent });

    const commandContentSelect = await screen.findByRole('combobox', { name: 'Command' });
    expect(commandContentSelect).toHaveValue('5');

    // Clear the selection. This also makes `hasContent` false, which would
    // disable the Save button while we're still sitting on Step 0 (Save is
    // disabled by `!isStepValid`, and Step 0's validity is `hasContent`).
    // Navigate to a different step first - edit mode unlocks every step, and
    // any step other than 0 has its own, unrelated validity check, so Save
    // becomes clickable again while commandId is still empty.
    await user.selectOptions(commandContentSelect, '');
    await user.click(await screen.findByText('Optional'));
    await user.click(screen.getByRole('button', { name: 'Save' }));

    expect(screen.getByRole('combobox', { name: 'Command' })).toHaveAccessibleDescription(
      'Please select a Command',
    );

    // Now pivot to a completely different event type/context: Action, with
    // its Action Type set to "Command". This renders its own, unrelated
    // "Command" dropdown (ScheduleEventModal.tsx:1309-1322) that also reads
    // formErrors.commandId.
    await user.selectOptions(screen.getByRole('combobox', { name: 'Event Type' }), 'Action');
    await user.selectOptions(screen.getByRole('combobox', { name: 'Action Type' }), 'command');

    const actionCommandSelect = screen.getByRole('combobox', { name: 'Command' });

    // The user hasn't attempted to save in this new Action context yet, so
    // there should be no error shown against this freshly-revealed field.
    expect(actionCommandSelect).not.toHaveAccessibleDescription('Please select a Command');
  });
});
