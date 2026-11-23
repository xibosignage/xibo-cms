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

// =============================================================================
// Test type: Page test
// The ad campaign editor's layouts: what Add, Edit and Remove send. Each layout
// is assigned with its days of the week, a daypart and a geofence, and an edit
// is a remove followed by a fresh assign, so a failed assign must put the
// original back or the layout silently disappears from the campaign.
//
// Open defect, pinned below: the assign call has no way to say where the
// layout goes, and the CMS adds it at the end, so editing a layout moves it to
// the end of the play order.
//
// The geofence is drawn on a map that JSDOM can't run, so the map is replaced
// by a button that "draws" a fixed shape. The day and daypart dropdowns are
// the real components.
// =============================================================================

// The shared notify mock must be imported before the page so its notify is the mock.
// eslint-disable-next-line import/order -- must load before the page
import { mockNotifyError, mockNotifySuccess } from '@/testUtils/notifyMock';

import { QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, test, vi } from 'vitest';

import CampaignEditor from '../CampaignEditor';

import { mockAdCampaign, mockUser } from './campaignTestUtils';

import { UserProvider } from '@/context/UserContext';
import {
  assignLayoutToAdCampaign,
  fetchAdCampaign,
  removeLayoutAssignment,
} from '@/services/campaignApi';
import { fetchDaypart, fetchDaypartById } from '@/services/daypartApi';
import { fetchDisplayGroups } from '@/services/displayGroupApi';
import { fetchLayouts } from '@/services/layoutsApi';
import { testQueryClient } from '@/setupTests';
import { apiError } from '@/testUtils/apiErrors';
import { knownFailure } from '@/testUtils/knownFailure';
import type { Campaign, LayoutOnCampaign } from '@/types/campaign';
import type { Daypart } from '@/types/daypart';
import type { Layout } from '@/types/layout';

vi.mock('@/services/campaignApi');
vi.mock('@/services/daypartApi');
vi.mock('@/services/displayGroupApi');
vi.mock('@/services/layoutsApi');

// Hoisted so the map mock below and the assertions share one value.
const GEOFENCE = vi.hoisted(() => '{"type":"FeatureCollection","features":[]}');

// The real map needs a browser; this one "draws" GEOFENCE when its button is clicked.
vi.mock('@/components/ui/GeoScheduleMap', () => ({
  default: ({ onChange }: { onChange: (value: string) => void }) => (
    <button type="button" onClick={() => onChange(GEOFENCE)}>
      Draw geofence
    </button>
  ),
}));

const CAMPAIGN_ID = mockAdCampaign.campaignId;

// Alpha is already on the campaign, second in play order, on Mondays and Wednesdays in the Morning.
const alphaOnCampaign = {
  lkCampaignLayoutId: 1,
  campaignId: CAMPAIGN_ID,
  layoutId: 10,
  displayOrder: 2,
  dayPartId: 3,
  daysOfWeek: '1,3',
  geoFence: null,
  layout: 'Alpha',
  layoutCampaignId: 110,
  ownerId: 1,
  duration: 30,
  dayPart: 'Morning',
} as unknown as LayoutOnCampaign;

const renderEditor = () =>
  render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={mockUser}>
        <MemoryRouter initialEntries={[`/design/campaign/${CAMPAIGN_ID}`]}>
          <Routes>
            <Route path="/design/campaign/:id" element={<CampaignEditor />} />
          </Routes>
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );

const showCampaignWith = (layouts: LayoutOnCampaign[]) =>
  vi.mocked(fetchAdCampaign).mockResolvedValue({ ...mockAdCampaign, layouts } as Campaign);

const layoutDialog = () => screen.getByRole('dialog', { name: /^(Add|Edit) Layout$/ });

/** Keep only these days ticked in the open layout dialog. */
const keepOnlyDays = async (user: UserEvent, keep: string[]) => {
  await user.click(within(layoutDialog()).getByRole('combobox', { name: 'Days of the Week' }));
  for (const day of [
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
    'Sunday',
  ]) {
    const box = screen.getByRole('checkbox', { name: day });
    if ((box as HTMLInputElement).checked !== keep.includes(day)) {
      await user.click(box);
    }
  }
  await user.keyboard('{Escape}');
};

const saveDialog = async (user: UserEvent) =>
  user.click(within(layoutDialog()).getByRole('button', { name: 'Save' }));

beforeEach(() => {
  testQueryClient.clear();
  vi.clearAllMocks();
  vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue(null);

  showCampaignWith([]);
  vi.mocked(fetchLayouts).mockResolvedValue({
    rows: [{ layoutId: 10, layout: 'Alpha' } as Layout],
    totalCount: 1,
  });
  vi.mocked(fetchDaypart).mockResolvedValue({
    rows: [{ dayPartId: 3, name: 'Morning' } as Daypart],
    totalCount: 1,
  });
  vi.mocked(fetchDaypartById).mockResolvedValue({ dayPartId: 3, name: 'Morning' } as Daypart);
  vi.mocked(fetchDisplayGroups).mockResolvedValue({ rows: [], totalCount: 0 } as never);
  vi.mocked(assignLayoutToAdCampaign).mockResolvedValue(undefined);
  vi.mocked(removeLayoutAssignment).mockResolvedValue(undefined);
});

describe('CampaignEditor - adding a layout', () => {
  /** Pick Alpha in the layout picker, which opens the Add Layout dialog. */
  const pickAlpha = async (user: UserEvent) => {
    renderEditor();
    await user.click(await screen.findByRole('combobox', { name: 'Layout' }));
    await user.click(await screen.findByRole('option', { name: 'Alpha' }));
    await screen.findByRole('dialog', { name: 'Add Layout' });
  };

  test('sends the layout with its days, daypart and geofence', async () => {
    const user = userEvent.setup();
    await pickAlpha(user);

    await keepOnlyDays(user, ['Monday', 'Wednesday']);
    await user.click(within(layoutDialog()).getByRole('combobox', { name: 'Dayparting' }));
    await user.click(await screen.findByRole('option', { name: 'Morning' }));
    await user.click(within(layoutDialog()).getByRole('button', { name: 'Draw geofence' }));
    await saveDialog(user);

    await waitFor(() => expect(assignLayoutToAdCampaign).toHaveBeenCalledTimes(1));
    expect(assignLayoutToAdCampaign).toHaveBeenCalledWith(CAMPAIGN_ID, {
      layoutId: 10,
      daysOfWeek: [1, 3],
      dayPartId: 3,
      geoFence: GEOFENCE,
    });
  });

  test('a layout with no geofence is sent without one, not with an empty one', async () => {
    const user = userEvent.setup();
    await pickAlpha(user);

    await saveDialog(user);

    await waitFor(() => expect(assignLayoutToAdCampaign).toHaveBeenCalledTimes(1));
    const [, payload] = vi.mocked(assignLayoutToAdCampaign).mock.calls[0]!;
    expect(payload.layoutId).toBe(10);
    expect(payload.daysOfWeek).toEqual([1, 2, 3, 4, 5, 6, 7]);
    expect(payload.geoFence).toBeUndefined();
  });

  test('a failed add shows the error and keeps the dialog open', async () => {
    const user = userEvent.setup();
    vi.mocked(assignLayoutToAdCampaign).mockRejectedValueOnce(apiError('Layout is retired'));
    await pickAlpha(user);

    await saveDialog(user);

    await waitFor(() => expect(mockNotifyError).toHaveBeenCalledWith('Layout is retired'));
    expect(screen.getByRole('dialog', { name: 'Add Layout' })).toBeInTheDocument();
    expect(mockNotifySuccess).not.toHaveBeenCalled();
  });
});

describe('CampaignEditor - editing and removing a layout', () => {
  beforeEach(() => {
    showCampaignWith([alphaOnCampaign]);
  });

  const editAlpha = async (user: UserEvent) => {
    renderEditor();
    await screen.findByText('Alpha');
    await user.click(screen.getByRole('button', { name: 'Edit' }));
    await screen.findByRole('dialog', { name: 'Edit Layout' });
  };

  test('an edit removes the layout from its place, then assigns it again with the new days', async () => {
    const user = userEvent.setup();
    await editAlpha(user);

    await keepOnlyDays(user, ['Monday']);
    await saveDialog(user);

    await waitFor(() => expect(assignLayoutToAdCampaign).toHaveBeenCalledTimes(1));
    expect(removeLayoutAssignment).toHaveBeenCalledWith(CAMPAIGN_ID, 10, 2);
    // objectContaining: the fix for the pinned play-order defect adds the layout's place here.
    expect(assignLayoutToAdCampaign).toHaveBeenCalledWith(
      CAMPAIGN_ID,
      expect.objectContaining({ layoutId: 10, daysOfWeek: [1], dayPartId: 3 }),
    );
    expect(vi.mocked(assignLayoutToAdCampaign).mock.calls[0]![1].geoFence).toBeUndefined();
    expect(vi.mocked(removeLayoutAssignment).mock.invocationCallOrder[0]).toBeLessThan(
      vi.mocked(assignLayoutToAdCampaign).mock.invocationCallOrder[0]!,
    );
  });

  test('if removing the old assignment fails, the edit stops there: nothing is assigned', async () => {
    const user = userEvent.setup();
    vi.mocked(removeLayoutAssignment).mockRejectedValueOnce(apiError('Campaign is locked', 409));
    await editAlpha(user);

    await keepOnlyDays(user, ['Monday']);
    await saveDialog(user);

    await waitFor(() => expect(mockNotifyError).toHaveBeenCalledWith('Campaign is locked'));
    // Assigning anyway would leave the layout on the campaign twice.
    expect(assignLayoutToAdCampaign).not.toHaveBeenCalled();
    expect(screen.getByRole('dialog', { name: 'Edit Layout' })).toBeInTheDocument();
    expect(mockNotifySuccess).not.toHaveBeenCalled();
  });

  // The original's place in the play order is not restored either: see the open defect below.
  test('if the new assignment fails, the original days and daypart are put back and the error is shown', async () => {
    const user = userEvent.setup();
    vi.mocked(assignLayoutToAdCampaign)
      .mockRejectedValueOnce(apiError('Layout is locked', 409))
      .mockResolvedValueOnce(undefined);
    await editAlpha(user);

    await keepOnlyDays(user, ['Monday']);
    await saveDialog(user);

    await waitFor(() => expect(assignLayoutToAdCampaign).toHaveBeenCalledTimes(2));
    expect(vi.mocked(assignLayoutToAdCampaign).mock.calls[1]).toEqual([
      CAMPAIGN_ID,
      expect.objectContaining({ layoutId: 10, daysOfWeek: [1, 3], dayPartId: 3 }),
    ]);
    await waitFor(() => expect(mockNotifyError).toHaveBeenCalledWith('Layout is locked'));
    expect(mockNotifySuccess).not.toHaveBeenCalled();
  });

  // The layout is then off the campaign, and the only message is the first error.
  test('if putting the original back fails too, it is still attempted and the first error is shown', async () => {
    const user = userEvent.setup();
    vi.mocked(assignLayoutToAdCampaign)
      .mockRejectedValueOnce(apiError('Layout is locked', 409))
      .mockRejectedValueOnce(apiError('Campaign is locked', 409));
    await editAlpha(user);

    await keepOnlyDays(user, ['Monday']);
    await saveDialog(user);

    await waitFor(() => expect(mockNotifyError).toHaveBeenCalledWith('Layout is locked'));
    expect(vi.mocked(assignLayoutToAdCampaign).mock.calls[1]![1]).toEqual(
      expect.objectContaining({ layoutId: 10, daysOfWeek: [1, 3], dayPartId: 3 }),
    );
    expect(mockNotifySuccess).not.toHaveBeenCalled();
  });

  // https://github.com/xibosignage/xibo/issues/4116 (open). An edit removes the layout first, so
  // when the new assignment and the restore both fail it is off the campaign, but nothing refreshes
  // the campaign after a failure and the table still lists it. Remove checks only the campaign and
  // assign also checks the layout, so a user who can no longer view a layout hits this on every edit.
  knownFailure(
    'a layout a failed edit could not put back leaves the table',
    async () => {
      const user = userEvent.setup();
      // The campaign, as the CMS returns it once the layout is off it.
      vi.mocked(removeLayoutAssignment).mockImplementationOnce(async () => {
        showCampaignWith([]);
      });
      vi.mocked(assignLayoutToAdCampaign)
        .mockRejectedValueOnce(
          apiError('You do not have permission to assign the provided Layout', 403),
        )
        .mockRejectedValueOnce(
          apiError('You do not have permission to assign the provided Layout', 403),
        );
      await editAlpha(user);
      await keepOnlyDays(user, ['Monday']);
      await saveDialog(user);
      await waitFor(() => expect(mockNotifyError).toHaveBeenCalled());
      await user.keyboard('{Escape}');
      await waitFor(() =>
        expect(screen.queryByRole('dialog', { name: 'Edit Layout' })).not.toBeInTheDocument(),
      );
    },
    () => waitFor(() => expect(screen.queryByText('Alpha')).not.toBeInTheDocument()),
  );

  // https://github.com/xibosignage/xibo/issues/4114 (open). Editing a layout removes it from its
  // place and assigns it again, and the assign call carries no place, so the CMS appends it: on a
  // campaign playing A, B, C, editing B's days leaves A, C, B (checked against a running CMS).
  // The fix needs the CMS as well as this page: the assign endpoint passes no place to the
  // campaign whatever it is sent, so a change that only makes this page send `displayOrder` turns
  // this test green while the play order is still wrong. The fix should also send the place when
  // a failed edit puts the original back (the second assign call).
  knownFailure(
    'editing a layout keeps it in its place in the play order',
    async () => {
      const user = userEvent.setup();
      await editAlpha(user);
      await keepOnlyDays(user, ['Monday']);
      await saveDialog(user);
      await waitFor(() => expect(assignLayoutToAdCampaign).toHaveBeenCalledTimes(1));
      return vi.mocked(assignLayoutToAdCampaign).mock.calls[0]![1];
    },
    (reassigned) => expect(reassigned).toHaveProperty('displayOrder', 2),
  );

  // A geofence comes back from the API as a JSON string or as a parsed object, and the editor has
  // a branch for each. Either way, an edit (and a failed edit's restore) must send it back as the
  // same JSON string, not lose it or encode it twice.
  describe.each([
    ['as a JSON string', GEOFENCE],
    ['as a parsed object', JSON.parse(GEOFENCE) as object],
  ])('a layout whose geofence is stored %s', (_, storedGeoFence) => {
    beforeEach(() => {
      showCampaignWith([{ ...alphaOnCampaign, geoFence: storedGeoFence } as LayoutOnCampaign]);
    });

    test('an edit sends the geofence back unchanged', async () => {
      const user = userEvent.setup();
      await editAlpha(user);

      await keepOnlyDays(user, ['Monday']);
      await saveDialog(user);

      await waitFor(() => expect(assignLayoutToAdCampaign).toHaveBeenCalledTimes(1));
      expect(vi.mocked(assignLayoutToAdCampaign).mock.calls[0]![1].geoFence).toBe(GEOFENCE);
    });

    test('a failed edit puts the geofence back unchanged', async () => {
      const user = userEvent.setup();
      vi.mocked(assignLayoutToAdCampaign)
        .mockRejectedValueOnce(apiError('Layout is locked', 409))
        .mockResolvedValueOnce(undefined);
      await editAlpha(user);

      await keepOnlyDays(user, ['Monday']);
      await saveDialog(user);

      await waitFor(() => expect(assignLayoutToAdCampaign).toHaveBeenCalledTimes(2));
      expect(vi.mocked(assignLayoutToAdCampaign).mock.calls[1]![1].geoFence).toBe(GEOFENCE);
    });
  });

  test('Remove sends the layout and its place in the play order', async () => {
    const user = userEvent.setup();
    renderEditor();
    await screen.findByText('Alpha');

    await user.click(screen.getByRole('button', { name: 'More actions' }));
    await user.click(await screen.findByRole('button', { name: 'Remove' }));

    await waitFor(() => expect(removeLayoutAssignment).toHaveBeenCalledWith(CAMPAIGN_ID, 10, 2));
    expect(assignLayoutToAdCampaign).not.toHaveBeenCalled();
  });

  test('a removed layout leaves the table, and the removal is confirmed', async () => {
    const user = userEvent.setup();
    // The campaign, as the CMS returns it once the layout is off it.
    vi.mocked(removeLayoutAssignment).mockImplementationOnce(async () => {
      showCampaignWith([]);
    });
    renderEditor();
    await screen.findByText('Alpha');

    await user.click(screen.getByRole('button', { name: 'More actions' }));
    await user.click(await screen.findByRole('button', { name: 'Remove' }));

    await waitFor(() => expect(screen.queryByText('Alpha')).not.toBeInTheDocument());
    expect(mockNotifySuccess).toHaveBeenCalledWith('Layout removed');
  });

  test('a failed remove shows the error and leaves the layout in the table', async () => {
    const user = userEvent.setup();
    vi.mocked(removeLayoutAssignment).mockRejectedValueOnce(apiError('Campaign is locked', 409));
    renderEditor();
    await screen.findByText('Alpha');

    await user.click(screen.getByRole('button', { name: 'More actions' }));
    await user.click(await screen.findByRole('button', { name: 'Remove' }));

    await waitFor(() => expect(mockNotifyError).toHaveBeenCalledWith('Campaign is locked'));
    expect(screen.getByText('Alpha')).toBeInTheDocument();
    expect(mockNotifySuccess).not.toHaveBeenCalled();
  });
});
