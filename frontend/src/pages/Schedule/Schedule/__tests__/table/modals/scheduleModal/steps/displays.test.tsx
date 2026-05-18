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

describe('ScheduleEventModal - Step 1 (Displays)', () => {
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

  // Until the user picks at least one display, the Finish button shouldn't
  // appear in the modal footer - there's nothing valid to save yet.
  test('the Finish button is hidden until a display is selected', async () => {
    renderScheduleModal({ mode: 'add', contentId: 42 });

    await screen.findByRole('button', { name: 'Pick a display group' });
    expect(screen.queryByRole('button', { name: 'Finish' })).not.toBeInTheDocument();
  });

  // Once the user picks at least one display, the Finish button should
  // appear so they can save an "always-on" event without having to walk
  // through the remaining steps.
  test('selecting a display reveals the Finish button', async () => {
    const user = userEvent.setup();
    renderScheduleModal({ mode: 'add', contentId: 42 });

    await user.click(await screen.findByRole('button', { name: 'Pick a display group' }));

    expect(screen.getByRole('button', { name: 'Finish' })).toBeInTheDocument();
  });

  // Open the modal in Sync mode with a sync group already chosen. The
  // display-group picker should NOT render at all - Sync events go straight
  // from Content to Time.
  test('Sync event type skips the Displays step entirely', async () => {
    renderScheduleModal({
      mode: 'add',
      eventTypeId: EventTypeId.Sync,
      contentId: 42,
    });

    // Drain the queue by waiting for the dialog itself, then assert
    // absence of the displays-step picker.
    await screen.findByRole('dialog');
    expect(screen.queryByRole('button', { name: 'Pick a display group' })).not.toBeInTheDocument();
  });

  // The banner is gated on the `showDisplayBanner` state, which the modal
  // sets to true when the user picks a display from the multi-select.
  test('picking a display reveals the "you\'re all set" info banner', async () => {
    const user = userEvent.setup();
    renderScheduleModal({ mode: 'add', contentId: 42 });

    await user.click(await screen.findByRole('button', { name: 'Pick a display group' }));

    expect(screen.getByText(/you're all set/i)).toBeInTheDocument();
  });

  // Without displayGroupIds pre-filled, the Finish button at Step 1 stays
  // hidden until the user clicks the display picker. With displayGroupIds
  // pre-filled, hasDisplays is true on open - Finish appears immediately
  // and is enabled without any user interaction.
  test('opening the modal with displayGroupIds pre-filled (e.g. from the Displays page) skips the manual display-picking step', async () => {
    renderScheduleModal({
      mode: 'add',
      eventTypeId: EventTypeId.Layout,
      contentId: 100,
      displayGroupIds: [5],
    });

    expect(await screen.findByRole('button', { name: 'Finish' })).toBeEnabled();
  });
});
