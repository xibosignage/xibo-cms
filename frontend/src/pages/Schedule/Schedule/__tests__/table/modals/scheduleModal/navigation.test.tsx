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
} from '../../../mocks/api';

import { renderScheduleModal } from './helpers/renderScheduleModal';

import { fetchLayouts } from '@/services/layoutsApi';
import { testQueryClient } from '@/setupTests';
import { EventTypeId } from '@/types/event';
import type { Layout } from '@/types/layout';

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
// Tests
// =============================================================================

describe('ScheduleEventModal - navigation', () => {
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

  // ---------------------------------------------------------------------------
  // Step navigation buttons in the modal footer.
  // ---------------------------------------------------------------------------

  test('the Next button is disabled when no content has been chosen yet', async () => {
    renderScheduleModal({ mode: 'add' });

    const nextButton = await screen.findByRole('button', { name: 'Next' });
    expect(nextButton).toBeDisabled();
  });

  test('the Back button is not shown on the first step', async () => {
    renderScheduleModal({ mode: 'add' });

    // Drain the microtask queue via a positive-presence findBy first, then
    // assert the absence of Back.
    await screen.findByRole('button', { name: 'Cancel' });
    expect(screen.queryByRole('button', { name: 'Back' })).not.toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Cancel behaviour - clicking Cancel should close the modal without
  // saving anything.
  // ---------------------------------------------------------------------------

  test('clicking Cancel calls the parent onClose callback', async () => {
    const user = userEvent.setup();
    const onClose = vi.fn();

    renderScheduleModal({ mode: 'add', onClose });

    await user.click(await screen.findByRole('button', { name: 'Cancel' }));

    expect(onClose).toHaveBeenCalledTimes(1);
  });

  // ---------------------------------------------------------------------------
  // Advancing through the steps. Choosing valid content on Step 0 should
  // unblock the Next button and take the user to Step 1 (Displays).
  // ---------------------------------------------------------------------------

  test('completing Step 0 by choosing content enables Next to advance to Step 1', async () => {
    const user = userEvent.setup();
    // Mock fetchLayouts so the content dropdown has a real option to pick.
    vi.mocked(fetchLayouts).mockResolvedValueOnce({
      rows: [
        {
          layoutId: 1,
          layout: 'Layout A',
          campaignId: 100,
        } as unknown as Layout,
      ],
      totalCount: 1,
    });

    renderScheduleModal({ mode: 'add' });

    expect(await screen.findByRole('button', { name: 'Next' })).toBeDisabled();

    const contentSelect = await screen.findByRole('combobox', { name: 'Layout' });
    await user.selectOptions(contentSelect, '100');

    const nextButton = screen.getByRole('button', { name: 'Next' });
    expect(nextButton).toBeEnabled();

    await user.click(nextButton);

    expect(screen.getByRole('button', { name: 'Pick a display group' })).toBeInTheDocument();
  });

  // Open at Step 1 via contentId + eventTypeId, then click Back. The user
  // should land back on Step 0 with the Event Type combobox visible.
  test('clicking Back from Step 1 returns the user to Step 0', async () => {
    const user = userEvent.setup();

    renderScheduleModal({
      mode: 'add',
      eventTypeId: EventTypeId.Layout,
      contentId: 100,
    });

    await user.click(await screen.findByRole('button', { name: 'Back' }));

    expect(screen.getByRole('combobox', { name: 'Event Type' })).toBeInTheDocument();
    // And the Displays step's picker is no longer on screen.
    expect(screen.queryByRole('button', { name: 'Pick a display group' })).not.toBeInTheDocument();
  });

  test('already-visited steps in the stepper are clickable; not-yet-reached steps are not', async () => {
    const user = userEvent.setup();

    renderScheduleModal({
      mode: 'add',
      eventTypeId: EventTypeId.Layout,
      contentId: 100,
    });

    await user.click(await screen.findByText('Content'));
    expect(screen.getByRole('combobox', { name: 'Event Type' })).toBeInTheDocument();
    await user.click(screen.getByText('Optional'));
    expect(screen.getByRole('combobox', { name: 'Event Type' })).toBeInTheDocument();
  });
});
