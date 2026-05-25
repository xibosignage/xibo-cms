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

import { buildEvent, mockEvent } from '../../../../fixtures/event';
import {
  ALWAYS_ONLY,
  mockDaypartRows,
  mockFetchEventById,
  setupCampaignMocks,
  setupCommandMocks,
  setupDatasetMocks,
  setupDaypartMocks,
  setupEventMocks,
  setupLayoutsMocks,
  setupMediaMocks,
  setupPlaylistMocks,
  setupResolutionMocks,
  setupSyncGroupMocks,
} from '../../../../mocks/api';
import { renderScheduleModal } from '../helpers/renderScheduleModal';

import { testQueryClient } from '@/setupTests';
import { EventTypeId } from '@/types/event';
import { hasFeature } from '@/utils/permissions';

// ─── Mocks ────────────────────────────────────────────────────────────────────

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key: string) => key, i18n: { changeLanguage: vi.fn() } }),
  Trans: ({ children }: { children: React.ReactNode }) => children,
}));

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

// Walk the Sync-event flow: Step 0 (Content - sync group pre-filled) →
// Step 1 (Time - default Always daypart) → Step 2 (Optional). Sync skips
// Displays, which is the easiest path to land on the Optional step.
const advanceToOptionalStep = async (user: ReturnType<typeof userEvent.setup>) => {
  renderScheduleModal({
    mode: 'add',
    eventTypeId: EventTypeId.Sync,
    contentId: 42, // syncGroupId
  });

  await user.click(await screen.findByRole('button', { name: 'Next' }));
};

// Walk a non-Sync event-type flow: Step 0 (Content - pre-filled by
// contentId) → Step 1 (Displays - pick one) → Step 2 (Time - Always
// auto-selected) → Step 3 (Optional). Used for Media/Playlist tests
// where the event type determines the Optional fields.
const advanceToOptionalStepWithType = async (
  user: ReturnType<typeof userEvent.setup>,
  eventTypeId: EventTypeId,
) => {
  renderScheduleModal({ mode: 'add', eventTypeId, contentId: 42 });

  // Step 0 → Step 1 (Displays); contentId already advanced us here.
  await user.click(await screen.findByRole('button', { name: 'Pick a display group' }));
  // Step 1 → Step 2 (Time).
  await user.click(screen.getByRole('button', { name: 'Next' }));
  // Step 2 → Step 3 (Optional); Always auto-selected satisfies time step.
  await user.click(screen.getByRole('button', { name: 'Next' }));
};

// =============================================================================
// Tests
// =============================================================================

describe('ScheduleEventModal - Step 3 (Optional)', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();

    setupEventMocks();
    setupDaypartMocks();
    setupResolutionMocks();
    setupLayoutsMocks();
    setupCampaignMocks();
    setupCommandMocks();
    setupMediaMocks();
    setupPlaylistMocks();
    setupSyncGroupMocks();
    setupDatasetMocks();
    mockDaypartRows(ALWAYS_ONLY);
    vi.mocked(hasFeature).mockReturnValue(true);
  });

  // With the default Always daypart, the Repeats and Reminder sub-tabs
  // should not be present in the sub-tab nav.
  test('Repeats and Reminder tabs are hidden when the daypart is Always', async () => {
    const user = userEvent.setup();
    await advanceToOptionalStep(user);

    expect(screen.queryByRole('button', { name: 'Repeats' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Reminder' })).not.toBeInTheDocument();
  });

  // When the user lacks the Geo Location and Criteria features, those
  // sub-tabs shouldn't be in the sub-tab nav.
  test('Geo Location and Criteria tabs are hidden when their feature flags are off', async () => {
    const user = userEvent.setup();
    vi.mocked(hasFeature).mockReturnValue(false);

    await advanceToOptionalStep(user);

    expect(screen.queryByRole('button', { name: 'Geo Location' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Criteria' })).not.toBeInTheDocument();
  });

  // When the user lands on the Optional step, the General tab is the
  // active sub-tab and its body content (the Name input) should be on
  // screen.
  test('the General sub-tab body shows the Name input by default', async () => {
    const user = userEvent.setup();
    await advanceToOptionalStep(user);

    expect(screen.getByRole('textbox', { name: 'Name' })).toBeInTheDocument();
  });

  // For a Sync event (default Always daypart, all flags on), the General
  // tab should render the full default field set.
  test('General sub-tab renders the full default field set (Display Order, Priority, Max plays per hour, Sync Timezone)', async () => {
    const user = userEvent.setup();
    await advanceToOptionalStep(user);

    expect(screen.getByRole('spinbutton', { name: 'Display Order' })).toBeInTheDocument();
    expect(screen.getByRole('spinbutton', { name: 'Priority' })).toBeInTheDocument();
    expect(screen.getByRole('spinbutton', { name: 'Maximum plays/hour' })).toBeInTheDocument();
    // The Sync Timezone checkbox is titled "Run at CMS Time?" (becomes
    // its accessible name).
    expect(screen.getByRole('checkbox', { name: 'Run at CMS Time?' })).toBeInTheDocument();
  });

  // Walk through Media-event flow to land on the Optional step, verify
  // the media-specific fields render alongside the General defaults.
  test('Media-type events show Duration in loop, Resolution and Background Colour on the General tab', async () => {
    const user = userEvent.setup();
    await advanceToOptionalStepWithType(user, EventTypeId.Media);

    expect(screen.getByRole('spinbutton', { name: 'Duration in loop' })).toBeInTheDocument();
    expect(screen.getByRole('combobox', { name: 'Resolution' })).toBeInTheDocument();
    expect(screen.getByText('Background Colour')).toBeInTheDocument();
  });

  test('Playlist-type events show Resolution and Background Colour on the General tab', async () => {
    const user = userEvent.setup();
    await advanceToOptionalStepWithType(user, EventTypeId.Playlist);

    expect(screen.getByRole('combobox', { name: 'Resolution' })).toBeInTheDocument();
    expect(screen.getByText('Background Colour')).toBeInTheDocument();
  });

  // Use edit mode with a pre-built action event so the modal lets us jump
  // to Optional via the stepper without having to walk the Action-specific
  // Content step.
  test('Action-type events hide Display Order and Max plays per hour on the General tab', async () => {
    const user = userEvent.setup();
    const actionEvent = buildEvent({
      eventTypeId: EventTypeId.Action,
      actionType: 'navLayout',
      actionTriggerCode: 'TRIGGER_A',
      actionLayoutCode: 'LAYOUT_CODE_A',
      dayPartId: 1,
    });
    mockFetchEventById(actionEvent);

    renderScheduleModal({ mode: 'edit', event: actionEvent });

    // Jump to Optional via the stepper (edit mode unlocks every step).
    await user.click(await screen.findByText('Optional'));

    // Name should still render (it's not Action-gated), but the two
    // hidden-for-Action fields should be absent.
    expect(screen.getByRole('textbox', { name: 'Name' })).toBeInTheDocument();
    expect(screen.queryByRole('spinbutton', { name: 'Display Order' })).not.toBeInTheDocument();
    expect(
      screen.queryByRole('spinbutton', { name: 'Maximum plays/hour' }),
    ).not.toBeInTheDocument();
  });

  // Helper for the Repeats-tab tests: open an edit-mode event whose
  // daypart maps to the Custom row, navigate to Optional, click Repeats.
  const advanceToRepeatsTab = async (user: ReturnType<typeof userEvent.setup>) => {
    mockDaypartRows([{ dayPartId: 1, name: 'Custom', isAlways: 0, isCustom: 1 }]);
    mockFetchEventById(mockEvent);

    renderScheduleModal({ mode: 'edit', event: mockEvent });

    await user.click(await screen.findByText('Optional'));
    await user.click(screen.getByRole('button', { name: 'Repeats' }));
  };

  // Selecting "Week" should reveal a strip of weekday toggle buttons
  // labelled with the short day names from WEEKDAYS.
  test('selecting "Week" as the repeat type reveals the weekday toggle buttons', async () => {
    const user = userEvent.setup();
    await advanceToRepeatsTab(user);

    // The recurrence-type combobox is labelled "Repeats" in production.
    await user.selectOptions(screen.getByRole('combobox', { name: 'Repeats' }), 'Week');

    // WEEKDAYS = ['Mon', 'Tue', ..., 'Sun']. Spot-check Mon and Sun.
    expect(screen.getByRole('button', { name: 'Mon' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Sun' })).toBeInTheDocument();
  });

  // Selecting "Month" should reveal the "Day of month" / "Day of week"
  // radio pair so the user can pick which monthly cadence applies.
  test('selecting "Month" as the repeat type reveals the day-of-month / day-of-week radios', async () => {
    const user = userEvent.setup();
    await advanceToRepeatsTab(user);

    await user.selectOptions(screen.getByRole('combobox', { name: 'Repeats' }), 'Month');

    expect(screen.getByRole('radio', { name: 'Day of month' })).toBeInTheDocument();
    expect(screen.getByRole('radio', { name: 'Day of week' })).toBeInTheDocument();
  });

  // Any non-empty repeat type unlocks the "Until" date picker so the
  // user can bound the recurrence.
  test('an Until date picker appears on the Repeats tab once a repeat type is chosen', async () => {
    const user = userEvent.setup();
    await advanceToRepeatsTab(user);

    await user.selectOptions(screen.getByRole('combobox', { name: 'Repeats' }), 'Week');

    expect(screen.getByLabelText('Until')).toBeInTheDocument();
  });

  test('enabling the Geo Location checkbox reveals the map; unchecking it hides the map', async () => {
    const user = userEvent.setup();
    await advanceToOptionalStep(user);

    // Open the Geo Location tab.
    await user.click(screen.getByRole('button', { name: 'Geo Location' }));

    // The Geo Schedule checkbox (title becomes accessible name).
    const geoCheckbox = screen.getByRole('checkbox', { name: 'Geo Schedule' });

    // Sanity: map is hidden by default.
    expect(screen.queryByRole('img', { name: /geo schedule map/i })).not.toBeInTheDocument();

    // Tick the checkbox - map appears.
    await user.click(geoCheckbox);
    expect(screen.getByRole('img', { name: /geo schedule map/i })).toBeInTheDocument();

    // Untick - map disappears.
    await user.click(geoCheckbox);
    expect(screen.queryByRole('img', { name: /geo schedule map/i })).not.toBeInTheDocument();
  });
});
