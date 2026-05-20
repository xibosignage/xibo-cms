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

import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
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

import { fetchCommands } from '@/services/commandApi';
import { fetchDataset } from '@/services/datasetApi';
import { fetchLayouts } from '@/services/layoutsApi';
import { testQueryClient } from '@/setupTests';
import type { Dataset } from '@/types/dataset';
import { EventTypeId } from '@/types/event';
import type { Layout } from '@/types/layout';

// ─── Mocks ────────────────────────────────────────────────────────────────────

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key: string) => key, i18n: { changeLanguage: vi.fn() } }),
  Trans: ({ children }: { children: React.ReactNode }) => children,
}));

vi.mock('@/components/ui/modals/Modal');

// SelectDropdown as a native <select> so getByRole('combobox') works for
// both the Event Type and the per-type content dropdown.
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
// Tests
// =============================================================================

describe('ScheduleEventModal - Step 0 (Content)', () => {
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
  });

  // We verify the dropdown's option labels match what the spec calls for.
  test('the Event Type dropdown lists all the supported event types', async () => {
    renderScheduleModal({ mode: 'add' });

    const eventTypeSelect = await screen.findByRole('combobox', { name: 'Event Type' });
    // Skip the empty-value placeholder option (the "Select Event Type"
    // hint that shows when nothing is picked yet) and look at the real
    // event-type choices.
    const optionLabels = within(eventTypeSelect)
      .getAllByRole('option')
      .filter((opt) => opt.getAttribute('value') !== '')
      .map((opt) => opt.textContent);

    // The 10 supported event types - matches EVENT_TYPE_OPTIONS in
    // scheduleEventDraft.ts. Order matches the dropdown order shown to users.
    expect(optionLabels).toEqual([
      'Layout',
      'Command',
      'Overlay Layout',
      'Interrupt Layout',
      'Campaign',
      'Action',
      'Media',
      'Playlist',
      'Synchronised Event',
      'Data Connector',
    ]);
  });

  // Add mode defaults to the Layout event type. When the modal opens the
  // page should immediately fetch the user's available layouts so the
  // content dropdown is ready to be picked from.
  test('opening the modal in add mode pre-fetches layouts (the default event type)', async () => {
    renderScheduleModal({ mode: 'add' });

    // Wait for the modal to settle then assert.
    await screen.findByRole('combobox', { name: 'Event Type' });
    expect(fetchLayouts).toHaveBeenCalled();
  });

  // Switching the event type from "Layout" to "Command" should clear the
  // layout content options and trigger a fresh fetch for commands instead.
  test('changing the event type to Command triggers a fetch for commands', async () => {
    const user = userEvent.setup();
    renderScheduleModal({ mode: 'add' });

    const eventTypeSelect = await screen.findByRole('combobox', { name: 'Event Type' });

    // Sanity check: fetchCommands hasn't fired yet for the default Layout
    // event type.
    expect(fetchCommands).not.toHaveBeenCalled();

    // Switch to Command via userEvent's selectOptions.
    await user.selectOptions(eventTypeSelect, 'Command');

    expect(fetchCommands).toHaveBeenCalled();
  });

  // Pick a layout, switch to Command, verify the content dropdown's value
  // is empty (no value attribute → placeholder option selected).
  test('changing the event type clears the previously selected content', async () => {
    const user = userEvent.setup();
    // Give the Layout dropdown a real option to pick.
    vi.mocked(fetchLayouts).mockResolvedValueOnce({
      rows: [{ layoutId: 1, layout: 'Layout A', campaignId: 100 } as unknown as Layout],
      totalCount: 1,
    });

    renderScheduleModal({ mode: 'add' });

    const contentSelect = await screen.findByRole('combobox', { name: 'Layout' });
    await user.selectOptions(contentSelect, '100');
    // Sanity: the content combobox now shows the Layout A value.
    expect(contentSelect).toHaveValue('100');

    // Now switch the Event Type to Command.
    const eventTypeSelect = screen.getByRole('combobox', { name: 'Event Type' });
    await user.selectOptions(eventTypeSelect, 'Command');

    // The content dropdown is now labelled "Command" (per type) and should
    // have no value - the previous selection was wiped.
    const commandSelect = screen.getByRole('combobox', { name: 'Command' });
    expect(commandSelect).toHaveValue('');
  });

  // Open with eventTypeId=Layout + contentId pre-filled. That seeds the
  // draft's campaignId, but the modal opens on Step 1 (Displays) because
  // contentId is set. Click Back to land on Step 0 (Content) where the
  // Preview button lives.
  test('a Preview button appears when a campaign is selected for a Layout-type event', async () => {
    const user = userEvent.setup();
    renderScheduleModal({
      mode: 'add',
      eventTypeId: EventTypeId.Layout,
      contentId: 100,
    });

    await user.click(await screen.findByRole('button', { name: 'Back' }));

    expect(screen.getByRole('button', { name: /Preview/i })).toBeInTheDocument();
  });

  // Switch to Action, then verify Action Type combobox + Trigger Code
  // textbox are rendered. Selecting "Navigate to Layout" should then
  // reveal a Layout Code combobox.
  test('switching to the Action event type reveals the Action Type, Trigger Code and Layout Code fields', async () => {
    const user = userEvent.setup();
    renderScheduleModal({ mode: 'add' });

    const eventTypeSelect = await screen.findByRole('combobox', { name: 'Event Type' });
    await user.selectOptions(eventTypeSelect, 'Action');

    // Action Type combobox should appear with its two options.
    const actionTypeSelect = screen.getByRole('combobox', { name: 'Action Type' });
    expect(actionTypeSelect).toBeInTheDocument();
    // Trigger Code textbox should appear.
    expect(screen.getByRole('textbox', { name: 'Trigger Code' })).toBeInTheDocument();

    // Picking "Navigate to Layout" reveals the Layout Code combobox.
    await user.selectOptions(actionTypeSelect, 'navLayout');
    expect(screen.getByRole('combobox', { name: 'Layout Code' })).toBeInTheDocument();
  });

  // Switch to Data Connector, pick a dataset, verify the Parameters
  // textbox is rendered.
  test('selecting a dataset for a Data Connector event reveals the Parameters field', async () => {
    const user = userEvent.setup();
    // The dataset list is fetched on event-type change, so use Once to
    // provide a single dataset option.
    vi.mocked(fetchDataset).mockResolvedValueOnce({
      rows: [{ dataSetId: 7, dataSet: 'Sales Feed' } as unknown as Dataset],
      totalCount: 1,
    });

    renderScheduleModal({ mode: 'add' });

    const eventTypeSelect = await screen.findByRole('combobox', { name: 'Event Type' });
    await user.selectOptions(eventTypeSelect, 'Data Connector');

    // The content dropdown for Data Connector is labelled "DataSet".
    const datasetSelect = await screen.findByRole('combobox', { name: 'DataSet' });
    await user.selectOptions(datasetSelect, '7');

    // Parameters textbox appears once a dataset is chosen.
    expect(screen.getByRole('textbox', { name: 'Parameters' })).toBeInTheDocument();
  });
});
