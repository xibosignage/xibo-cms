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
import userEvent, { type UserEvent } from '@testing-library/user-event';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  ALWAYS_AND_CUSTOM,
  mockDaypartRows,
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
