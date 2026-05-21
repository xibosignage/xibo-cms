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

import { QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import type React from 'react';
import { MemoryRouter } from 'react-router-dom';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockUser } from '../../../fixtures/user';
import {
  ALWAYS_ONLY,
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
} from '../../../mocks/api';

import { renderScheduleModal } from './helpers/renderScheduleModal';

import { notify } from '@/components/ui/Notification';
import ScheduleEventModal from '@/components/ui/modals/ScheduleEventModal';
import { UserProvider } from '@/context/UserContext';
import { createEvent, updateEvent } from '@/services/eventApi';
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
// Helpers
// =============================================================================

const renderReadyToSave = async (user: UserEvent) => {
  renderScheduleModal({
    mode: 'add',
    eventTypeId: EventTypeId.Layout,
    contentId: 42,
    onSaved: vi.fn(),
  });

  await user.click(await screen.findByRole('button', { name: 'Pick a display group' }));
};

// =============================================================================
// Tests
// =============================================================================

describe('ScheduleEventModal - add mode (save & cancel)', () => {
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
  });

  test('clicking Finish with valid fields calls the createEvent API', async () => {
    const user = userEvent.setup();
    await renderReadyToSave(user);

    await user.click(screen.getByRole('button', { name: 'Finish' }));

    expect(createEvent).toHaveBeenCalledTimes(1);
  });

  // We're in add mode, so the modal should NEVER fire the update-event
  // call. (updateEvent is reserved for edit mode.)
  test('clicking Finish in add mode never calls the updateEvent API', async () => {
    const user = userEvent.setup();
    await renderReadyToSave(user);

    await user.click(screen.getByRole('button', { name: 'Finish' }));

    expect(updateEvent).not.toHaveBeenCalled();
  });

  test("shows the 'Added Event' success notification after a successful save", async () => {
    const user = userEvent.setup();
    await renderReadyToSave(user);

    await user.click(screen.getByRole('button', { name: 'Finish' }));

    expect(notify.success).toHaveBeenCalledWith('Added Event');
  });

  test('calls onSaved so the parent table can refresh after a successful save', async () => {
    const user = userEvent.setup();
    const onSaved = vi.fn();

    renderScheduleModal({
      mode: 'add',
      eventTypeId: EventTypeId.Layout,
      contentId: 42,
      onSaved,
    });

    await user.click(await screen.findByRole('button', { name: 'Pick a display group' }));
    await user.click(screen.getByRole('button', { name: 'Finish' }));

    expect(onSaved).toHaveBeenCalledTimes(1);
  });

  test('closes the modal after a successful save by calling onClose', async () => {
    const user = userEvent.setup();
    const onClose = vi.fn();

    renderScheduleModal({
      mode: 'add',
      eventTypeId: EventTypeId.Layout,
      contentId: 42,
      onClose,
    });

    await user.click(await screen.findByRole('button', { name: 'Pick a display group' }));
    await user.click(screen.getByRole('button', { name: 'Finish' }));

    expect(onClose).toHaveBeenCalledTimes(1);
  });

  test('Cancel does not fire the createEvent API call', async () => {
    const user = userEvent.setup();
    await renderReadyToSave(user);

    await user.click(screen.getByRole('button', { name: 'Cancel' }));

    expect(createEvent).not.toHaveBeenCalled();
  });

  test('pressing Escape closes the modal without saving', async () => {
    const user = userEvent.setup();
    const onClose = vi.fn();

    renderScheduleModal({
      mode: 'add',
      eventTypeId: EventTypeId.Layout,
      contentId: 42,
      onClose,
    });

    await screen.findByRole('button', { name: 'Cancel' });

    await user.keyboard('{Escape}');

    expect(onClose).toHaveBeenCalledTimes(1);
    expect(createEvent).not.toHaveBeenCalled();
  });

  test("re-opening the modal after Cancel shows the original empty form, not the user's discarded edits", async () => {
    const user = userEvent.setup();

    const tree = (isOpen: boolean) => (
      <QueryClientProvider client={testQueryClient}>
        <UserProvider initialUser={mockUser}>
          <MemoryRouter>
            <ScheduleEventModal
              isOpen={isOpen}
              onClose={() => {}}
              mode="add"
              eventTypeId={EventTypeId.Sync}
              contentId={42}
            />
          </MemoryRouter>
        </UserProvider>
      </QueryClientProvider>
    );

    const { rerender } = render(tree(true));

    await user.click(await screen.findByRole('button', { name: 'Next' }));

    const nameInput = screen.getByRole('textbox', { name: 'Name' });
    await user.type(nameInput, 'Should Be Discarded');
    expect(nameInput).toHaveValue('Should Be Discarded');

    await user.click(screen.getByRole('button', { name: 'Cancel' }));

    rerender(tree(false));
    rerender(tree(true));

    await user.click(await screen.findByRole('button', { name: 'Next' }));
    expect(screen.getByRole('textbox', { name: 'Name' })).toHaveValue('');
  });
});
