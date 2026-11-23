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

// The Repeats and Reminder tabs as a scheduler who isn't an admin. The Repeats
// tab used to sit inside the Reminders feature check, so a user without
// Reminders couldn't see it, and a repeat error was sent to a tab they couldn't
// open (fixed in xibo-cms PR #3934).
//
// This file deliberately does NOT mock @/utils/permissions: the other modal
// files make hasFeature always true, which is why none of them could catch it.

import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { buildEvent, mockEvent } from '../../../fixtures/event';
import { mockUser } from '../../../fixtures/user';
import { mockDaypartRows, mockFetchEventById, setupScheduleModalMocks } from '../../../mocks/api';

import { renderScheduleModal } from './helpers/renderScheduleModal';

import { updateEvent } from '@/services/eventApi';
import { testQueryClient } from '@/setupTests';
import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';
import type { Event } from '@/types/event';
import type { User } from '@/types/user';

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
vi.mock('@/components/ui/Notification', () => ({
  notify: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
}));

// =============================================================================
// Users and helpers
// =============================================================================

// A scheduler who can add and edit events but has no Reminders feature.
const schedulerWithoutReminders = buildCurrentUser(PERSONAS.scheduleManager, {
  settings: mockUser.settings,
});
const schedulerWithReminders = buildCurrentUser(PERSONAS.scheduleManager, {
  settings: mockUser.settings,
  features: { 'schedule.reminders': true },
});

// Opens the modal in edit mode as the given user. The repeat tabs only apply to
// a custom (not Always) daypart, so every event here maps to the Custom row.
// The background fetch returns the stored event on purpose: the repeat fields
// under test come from the `event` prop, as in validation.test.tsx.
const renderEditAs = (asUser: User, event: Event = mockEvent) => {
  mockDaypartRows([{ dayPartId: 1, name: 'Custom', isAlways: 0, isCustom: 1 }]);
  mockFetchEventById(mockEvent);

  renderScheduleModal({ mode: 'edit', event }, { user: asUser });
};

const openEditOnOptionalStep = async (user: ReturnType<typeof userEvent.setup>, asUser: User) => {
  renderEditAs(asUser);

  await user.click(await screen.findByText('Optional'));
  // The General tab is always there; waiting for it means the tab row is drawn.
  await screen.findByRole('button', { name: 'General' });
};

// =============================================================================
// Tests
// =============================================================================

describe('ScheduleEventModal - a scheduler who is not an admin', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();

    setupScheduleModalMocks();
  });

  test('a scheduler without the Reminders feature still sees the Repeats tab', async () => {
    const user = userEvent.setup();
    await openEditOnOptionalStep(user, schedulerWithoutReminders);

    expect(screen.getByRole('button', { name: 'Repeats' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Reminder' })).not.toBeInTheDocument();
  });

  test('a scheduler with the Reminders feature sees both Repeats and Reminder', async () => {
    const user = userEvent.setup();
    await openEditOnOptionalStep(user, schedulerWithReminders);

    expect(screen.getByRole('button', { name: 'Repeats' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Reminder' })).toBeInTheDocument();
  });

  test('a repeat error takes a scheduler without Reminders to the Repeats tab', async () => {
    const user = userEvent.setup();
    const eventWithBadRepeat = buildEvent({
      ...mockEvent,
      recurrenceType: 'Week',
      recurrenceDetail: 0,
    });
    renderEditAs(schedulerWithoutReminders, eventWithBadRepeat);

    await user.click(await screen.findByRole('button', { name: 'Save' }));

    expect(await screen.findByRole('alert')).toHaveTextContent(/Repeat every must be at least 1/i);
    // The Repeats tab's own field is what is on screen, so the user can fix it.
    expect(await screen.findByRole('combobox', { name: 'Repeats' })).toHaveValue('Week');
    expect(updateEvent).not.toHaveBeenCalled();
  });
});
