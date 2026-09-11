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

import { screen, waitFor } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { ALWAYS_ONLY, mockDaypartRows, setupScheduleModalMocks } from '../../../mocks/api';

import { renderScheduleModal } from './helpers/renderScheduleModal';

import { createEvent } from '@/services/eventApi';
import { fetchSyncGroupDisplays } from '@/services/syncGroupApi';
import { testQueryClient } from '@/setupTests';
import { EventTypeId } from '@/types/event';
import type { SyncGroupDisplay } from '@/types/syncGroup';

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
// Fixtures
// =============================================================================

// The members of a Sync Group, as GET /syncgroup/{id}/displays returns them.
// `displayGroupId` is the field that matters here: it's the display-specific
// display group the CMS keeps for each individual display, and it's what a Sync
// Event has to be scheduled against for the event to reach that player at all.
const SYNC_GROUP_ID = 7;

const SYNC_MEMBERS: SyncGroupDisplay[] = [
  {
    displayId: 501,
    display: 'Foyer Screen',
    syncGroupId: SYNC_GROUP_ID,
    leadDisplayId: 501,
    displayGroupId: 101,
    layoutId: null,
  },
  {
    displayId: 502,
    display: 'Atrium Screen',
    syncGroupId: SYNC_GROUP_ID,
    leadDisplayId: 501,
    displayGroupId: 102,
    layoutId: null,
  },
];

const SYNC_MEMBER_DISPLAY_GROUP_IDS = [101, 102];

// =============================================================================
// Helpers
// =============================================================================

/**
 * Open the modal on a Sync event with the sync group already chosen, and wait
 * until the modal has loaded the group's members. Sync events have no Displays
 * step, so passing contentId lands us straight on the Time step with Finish
 * already available.
 */
const renderSyncEventReadyToSave = async () => {
  renderScheduleModal({
    mode: 'add',
    eventTypeId: EventTypeId.Sync,
    contentId: SYNC_GROUP_ID,
  });

  await screen.findByRole('combobox', { name: 'Dayparting' });
  await waitFor(() => {
    expect(fetchSyncGroupDisplays).toHaveBeenCalled();
  });
};

/** Open the modal on a plain Layout event and pick a display, ready to save. */
const renderLayoutEventReadyToSave = async (user: UserEvent) => {
  renderScheduleModal({
    mode: 'add',
    eventTypeId: EventTypeId.Layout,
    contentId: 42,
  });

  await user.click(await screen.findByRole('button', { name: 'Pick a display group' }));
};

/** The payload object the modal handed to createEvent. */
const capturedPayload = () => {
  const calls = vi.mocked(createEvent).mock.calls;
  return calls[calls.length - 1]![0];
};

// =============================================================================
// Tests
// =============================================================================

// Regression cover for xibosignage/xibo#3927.
//
// Every Schedule Event is delivered to players via the rows in
// `lkscheduledisplaygroup`, which the CMS writes from the `displayGroupIds` the
// save request carries. In 4.5.1 the modal hardcoded that array to [] for Sync
// events, so a Sync Event was saved with no display groups at all -
// ScheduleFactory::getForXmds() then never returned it and the event reached no
// player. The event looked perfectly fine in the Events grid, which is why this
// shipped: nothing in the UI showed the problem, and no test in this suite
// asserted what the modal actually sends.
describe('ScheduleEventModal - what the save request carries', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();

    setupScheduleModalMocks();

    mockDaypartRows(ALWAYS_ONLY);
    vi.mocked(fetchSyncGroupDisplays).mockResolvedValue(SYNC_MEMBERS);
  });

  // The specific fix: a Sync event is scheduled against the display group of
  // each display in the sync group, so the event is delivered to every screen
  // in the group.
  test('saving a Sync event sends the display group of every display in the sync group', async () => {
    const user = userEvent.setup();
    await renderSyncEventReadyToSave();

    await user.click(screen.getByRole('button', { name: 'Finish' }));

    await waitFor(() => {
      expect(createEvent).toHaveBeenCalledWith(
        expect.objectContaining({
          eventTypeId: EventTypeId.Sync,
          syncGroupId: SYNC_GROUP_ID,
          displayGroupIds: SYNC_MEMBER_DISPLAY_GROUP_IDS,
        }),
        expect.any(String),
      );
    });
  });

  // The same requirement stated as an invariant rather than as an exact list:
  // whatever the modal decides to send, a Sync event must never be saved with
  // an empty display group list, because that is silently undeliverable.
  test('a Sync event is never saved with an empty list of display groups', async () => {
    const user = userEvent.setup();
    await renderSyncEventReadyToSave();

    await user.click(screen.getByRole('button', { name: 'Finish' }));

    await waitFor(() => {
      expect(createEvent).toHaveBeenCalledTimes(1);
    });

    const payload = capturedPayload();
    expect(payload.displayGroupIds).not.toHaveLength(0);
  });

  // The Sync branch and the normal branch are two arms of the same ternary, so
  // guard the other arm too - a fix on one side must not break the other. A
  // Layout event has to send the displays and groups the user actually picked,
  // NOT the sync group's members.
  test('saving a Layout event sends the displays and groups the user picked', async () => {
    const user = userEvent.setup();
    await renderLayoutEventReadyToSave(user);

    await user.click(screen.getByRole('button', { name: 'Finish' }));

    await waitFor(() => {
      expect(createEvent).toHaveBeenCalledWith(
        expect.objectContaining({
          eventTypeId: EventTypeId.Layout,
          displayGroupIds: [10],
        }),
        expect.any(String),
      );
    });
  });
});
