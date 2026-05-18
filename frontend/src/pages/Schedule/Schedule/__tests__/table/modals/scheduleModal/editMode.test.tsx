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

import { notify } from '@/components/ui/Notification';
import { createEvent, fetchEventById, updateEvent } from '@/services/eventApi';
import { testQueryClient } from '@/setupTests';

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

describe('ScheduleEventModal - edit mode (pre-fill & save)', () => {
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

  test('fetches the full event details from the API on open', async () => {
    renderScheduleModal({ mode: 'edit', event: mockEvent });

    await screen.findByRole('dialog', { name: 'Edit Event' });

    expect(fetchEventById).toHaveBeenCalledWith(mockEvent.eventId);
  });

  test('edit mode unlocks all steps so the user can jump straight to Optional', async () => {
    const user = userEvent.setup();
    renderScheduleModal({ mode: 'edit', event: mockEvent });

    await user.click(await screen.findByText('Optional'));

    expect(screen.getByRole('button', { name: 'General' })).toBeInTheDocument();
  });

  test('the footer button reads "Save" (not "Finish") in edit mode', async () => {
    renderScheduleModal({ mode: 'edit', event: mockEvent });

    expect(await screen.findByRole('button', { name: 'Save' })).toBeEnabled();
    expect(screen.queryByRole('button', { name: 'Finish' })).not.toBeInTheDocument();
  });

  // mockEvent has name "Morning Promo", so when the user lands on the
  // Optional step's General tab the Name field should already show
  // that value.
  test('pre-fills the Name field on the Optional step with the event name', async () => {
    const user = userEvent.setup();
    renderScheduleModal({ mode: 'edit', event: mockEvent });

    await user.click(await screen.findByText('Optional'));

    expect(screen.getByDisplayValue('Morning Promo')).toBeInTheDocument();
  });

  test('uses values fetched from the API for fields not in the table row', async () => {
    const user = userEvent.setup();
    const enrichedEvent = buildEvent({
      eventId: mockEvent.eventId,
      campaignId: mockEvent.campaignId,
      name: 'Name From API',
      dayPartId: 1,
      displayGroups: mockEvent.displayGroups,
    });
    vi.mocked(fetchEventById).mockResolvedValueOnce(enrichedEvent);

    renderScheduleModal({ mode: 'edit', event: mockEvent });

    await user.click(await screen.findByText('Optional'));
    await screen.findByDisplayValue('Name From API');
  });

  // Edit mode hits the updateEvent endpoint, not createEvent. The first
  // argument has to be the existing event's id so the server knows
  // which row to update.
  test("clicking Save calls updateEvent with the event's id", async () => {
    const user = userEvent.setup();
    renderScheduleModal({ mode: 'edit', event: mockEvent });

    await user.click(await screen.findByRole('button', { name: 'Save' }));

    expect(updateEvent).toHaveBeenCalledTimes(1);
    expect(updateEvent).toHaveBeenCalledWith(
      mockEvent.eventId,
      expect.objectContaining({ eventTypeId: mockEvent.eventTypeId }),
      expect.any(String),
    );
  });

  // Edit mode should NEVER fire createEvent — that would result in a
  // duplicate row on the server instead of an update.
  test('clicking Save in edit mode never calls the createEvent API', async () => {
    const user = userEvent.setup();
    renderScheduleModal({ mode: 'edit', event: mockEvent });

    await user.click(await screen.findByRole('button', { name: 'Save' }));

    expect(createEvent).not.toHaveBeenCalled();
  });

  // After a successful update, the user should see an "Updated Event"
  // toast so they know their changes landed.
  test("shows the 'Updated Event' success notification after a successful save", async () => {
    const user = userEvent.setup();
    renderScheduleModal({ mode: 'edit', event: mockEvent });

    await user.click(await screen.findByRole('button', { name: 'Save' }));

    expect(notify.success).toHaveBeenCalledWith('Updated Event');
  });

  // The parent events table needs to refetch to show the updated row,
  // and the modal needs to close. Both are signalled to the parent via
  // the onSaved and onClose callbacks.
  test('calls onSaved and onClose after a successful update', async () => {
    const user = userEvent.setup();
    const onSaved = vi.fn();
    const onClose = vi.fn();

    renderScheduleModal({ mode: 'edit', event: mockEvent, onSaved, onClose });

    await user.click(await screen.findByRole('button', { name: 'Save' }));

    expect(onSaved).toHaveBeenCalledTimes(1);
    expect(onClose).toHaveBeenCalledTimes(1);
  });
});
