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

import { buildEvent, mockEvent } from '../../../fixtures/event';
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
} from '../../../mocks/api';

import { renderScheduleModal } from './helpers/renderScheduleModal';

import { fetchEventById } from '@/services/eventApi';
import { testQueryClient } from '@/setupTests';
import { EventTypeId } from '@/types/event';

// ─── Mocks ────────────────────────────────────────────────────────────────────

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key: string) => key, i18n: { changeLanguage: vi.fn() } }),
  Trans: ({ children }: { children: React.ReactNode }) => children,
}));

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

describe('ScheduleEventModal - validation step-routing', () => {
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
    mockFetchEventById(mockEvent);
    mockDaypartRows(ALWAYS_ONLY);
  });

  test('clicking Save in edit mode with a missing campaign jumps the user back to the Content step', async () => {
    const user = userEvent.setup();

    vi.mocked(fetchEventById).mockResolvedValueOnce(
      buildEvent({
        ...mockEvent,
        campaignId: undefined,
        fullScreenCampaignId: undefined,
      }),
    );

    renderScheduleModal({ mode: 'edit', event: mockEvent });

    await user.click(await screen.findByText('Optional'));

    expect(screen.getByRole('button', { name: 'General' })).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Save' }));
    expect(screen.getByRole('combobox', { name: 'Event Type' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'General' })).not.toBeInTheDocument();
  });

  test('clicking Finish in add mode with a missing daypart jumps the user to the Time step', async () => {
    const user = userEvent.setup();

    mockDaypartRows([]);

    renderScheduleModal({
      mode: 'add',
      eventTypeId: EventTypeId.Layout,
      contentId: 42,
    });

    await user.click(await screen.findByRole('button', { name: 'Pick a display group' }));
    await user.click(screen.getByRole('button', { name: 'Finish' }));

    expect(screen.getByRole('combobox', { name: 'Dayparting' })).toBeInTheDocument();

    expect(screen.queryByRole('button', { name: 'Pick a display group' })).not.toBeInTheDocument();
  });

  test('the field-level error message is exposed as the accessible description of the content input', async () => {
    const user = userEvent.setup();

    vi.mocked(fetchEventById).mockResolvedValueOnce(
      buildEvent({
        ...mockEvent,
        campaignId: undefined,
        fullScreenCampaignId: undefined,
      }),
    );

    renderScheduleModal({ mode: 'edit', event: mockEvent });

    await user.click(await screen.findByText('Optional'));
    await user.click(screen.getByRole('button', { name: 'Save' }));

    const contentCombobox = screen.getByRole('combobox', { name: 'Layout' });
    expect(contentCombobox).toHaveAccessibleDescription('Please select a Layout or Campaign');
  });
});
