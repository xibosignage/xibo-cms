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

import { QueryClientProvider } from '@tanstack/react-query';
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, test, vi } from 'vitest';

import CampaignEditor from '../CampaignEditor';

import { mockAdCampaign, mockUser } from './campaignTestUtils';

import { UserProvider } from '@/context/UserContext';
import { fetchAdCampaign, updateAdCampaign } from '@/services/campaignApi';
import { fetchDisplayGroups } from '@/services/displayGroupApi';
import { fetchLayouts } from '@/services/layoutsApi';
import { testQueryClient } from '@/setupTests';
import { knownFailure } from '@/testUtils/knownFailure';
import type { Campaign, LayoutOnCampaign } from '@/types/campaign';

vi.mock('@/services/campaignApi');
vi.mock('@/services/displayGroupApi');
vi.mock('@/services/layoutsApi');

// A controlled stand-in for the date picker, so a test can type a date and see
// what the field holds.
vi.mock('@/components/ui/forms/DatePickerInput', () => ({
  default: ({
    label,
    value,
    onChange,
  }: {
    label: string;
    value?: string;
    onChange: (value: string) => void;
  }) => <input aria-label={label} value={value ?? ''} onChange={(e) => onChange(e.target.value)} />,
}));
// Shows the selected display groups, and adds group 7 when its button is clicked.
vi.mock('@/pages/Schedule/Schedule/components/DisplayGroupMultiSelect', () => ({
  DisplayGroupMultiSelect: ({
    value,
    onChange,
  }: {
    value: { displaySpecificGroupIds: number[]; displayGroupIds: number[] };
    onChange: (value: { displaySpecificGroupIds: number[]; displayGroupIds: number[] }) => void;
  }) => (
    <div>
      <p>
        Selected displays: {[...value.displaySpecificGroupIds, ...value.displayGroupIds].join(', ')}
      </p>
      <button
        type="button"
        onClick={() => onChange({ ...value, displayGroupIds: [...value.displayGroupIds, 7] })}
      >
        Add display group 7
      </button>
    </div>
  ),
}));
vi.mock('@/components/ui/Notification', () => ({
  notify: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
}));

const CAMPAIGN_ID = mockAdCampaign.campaignId;

// The user's edit, not yet saved: 1 July 2025, 09:00 UTC.
const EDITED_START = '2025-07-01T09:00:00.000Z';

const assignedLayout = {
  lkCampaignLayoutId: 1,
  campaignId: CAMPAIGN_ID,
  layoutId: 31,
  displayOrder: 1,
  dayPartId: null,
  daysOfWeek: null,
  geoFence: null,
  layout: 'Summer Promo',
  layoutCampaignId: 32,
  ownerId: 1,
  duration: 30,
  dayPart: null,
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

/**
 * Fetch the campaign again, as the editor does after a layout is assigned,
 * removed or edited. The server's answer differs from the first one (it now
 * has the new layout): if it were identical, React Query would hand back the
 * same object and the editor would never notice the refetch.
 */
const refetchWithNewLayout = async (campaign: Campaign = mockAdCampaign) => {
  vi.mocked(fetchAdCampaign).mockResolvedValue({
    ...campaign,
    layouts: [assignedLayout],
  } as Campaign);

  await act(async () => {
    await testQueryClient.invalidateQueries({ queryKey: ['campaign', 'ad', CAMPAIGN_ID] });
  });
  await waitFor(() => {
    expect(fetchAdCampaign).toHaveBeenCalledTimes(2);
  });
};

// Regression cover for xibosignage/xibo#3914: before the fix, every refetch of
// the campaign rebuilt the whole General tab from the server, so assigning a
// layout threw away any dates (or name) the user had changed but not saved.
describe('CampaignEditor - unsaved edits survive a layout change', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();

    vi.mocked(fetchAdCampaign).mockResolvedValue({ ...mockAdCampaign, layouts: [] } as Campaign);
    vi.mocked(updateAdCampaign).mockResolvedValue(mockAdCampaign);
    vi.mocked(fetchDisplayGroups).mockResolvedValue({ rows: [], totalCount: 0 });
    vi.mocked(fetchLayouts).mockResolvedValue({ rows: [], totalCount: 0 });
  });

  test('an unsaved start date is still shown after the campaign is refetched', async () => {
    renderEditor();
    fireEvent.change(await screen.findByLabelText('Start Date'), {
      target: { value: EDITED_START },
    });

    await refetchWithNewLayout();

    expect(screen.getByLabelText('Start Date')).toHaveValue(EDITED_START);
  });

  test('saving after the refetch sends the unsaved start date', async () => {
    const user = userEvent.setup();
    renderEditor();
    fireEvent.change(await screen.findByLabelText('Start Date'), {
      target: { value: EDITED_START },
    });

    await refetchWithNewLayout();
    await user.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => {
      expect(updateAdCampaign).toHaveBeenCalledWith(
        CAMPAIGN_ID,
        expect.objectContaining({ startDt: '2025-07-01 09:00:00' }),
      );
    });
  });

  test('an unsaved name is still shown after the campaign is refetched', async () => {
    const user = userEvent.setup();
    renderEditor();
    const name = await screen.findByRole('textbox', { name: /Name/ });
    await user.clear(name);
    await user.type(name, 'Renamed Ad Campaign');

    await refetchWithNewLayout();

    expect(screen.getByRole('textbox', { name: /Name/ })).toHaveValue('Renamed Ad Campaign');
  });

  // The fix seeds the form once per campaign. Fields the user hasn't touched
  // still come from the server.
  test('the form starts from the campaign the server returns', async () => {
    renderEditor();

    expect(await screen.findByLabelText('End Date')).toHaveValue(
      new Date(mockAdCampaign.endDt! * 1000).toISOString(),
    );
    expect(screen.getByRole('textbox', { name: /Name/ })).toHaveValue(mockAdCampaign.campaign);
  });
});

// Report 272, open. The Displays selection is rebuilt from the saved displays
// every time the campaign is refetched (CampaignEditor.tsx, the effect on
// `campaign`), so a display picked but not yet saved is dropped when a layout
// is added, edited or removed, and the next Save stores the old set.
describe('CampaignEditor - unsaved displays and a layout change', () => {
  // A campaign already showing on display group 5.
  const campaignOnGroup5 = { ...mockAdCampaign, displayGroupIds: [5] } as Campaign;

  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();

    vi.mocked(fetchAdCampaign).mockResolvedValue({ ...campaignOnGroup5, layouts: [] } as Campaign);
    vi.mocked(updateAdCampaign).mockResolvedValue(campaignOnGroup5);
    vi.mocked(fetchLayouts).mockResolvedValue({ rows: [], totalCount: 0 });
    vi.mocked(fetchDisplayGroups).mockResolvedValue({
      rows: [{ displayGroupId: 5, displayGroup: 'Lobby Screens', isDisplaySpecific: 0 }],
      totalCount: 1,
    } as unknown as Awaited<ReturnType<typeof fetchDisplayGroups>>);
  });

  /** Wait for the saved displays to load, then pick display group 7 as well. */
  const pickAnotherDisplay = async (user: ReturnType<typeof userEvent.setup>) => {
    renderEditor();
    await screen.findByText('Selected displays: 5');
    await user.click(screen.getByRole('button', { name: 'Add display group 7' }));
    await screen.findByText('Selected displays: 5, 7');
  };

  const saveAndReadDisplays = async (user: ReturnType<typeof userEvent.setup>) => {
    await user.click(screen.getByRole('button', { name: 'Save' }));
    await waitFor(() => {
      expect(updateAdCampaign).toHaveBeenCalledTimes(1);
    });
    return vi.mocked(updateAdCampaign).mock.calls[0]![1].displayGroupIds;
  };

  test('the campaign opens with the displays it is saved on', async () => {
    renderEditor();

    expect(await screen.findByText('Selected displays: 5')).toBeInTheDocument();
  });

  test('a display picked and saved straight away is saved', async () => {
    const user = userEvent.setup();
    await pickAnotherDisplay(user);

    expect(await saveAndReadDisplays(user)).toEqual([5, 7]);
  });

  // https://github.com/xibosignage/xibo/issues/4052
  knownFailure(
    'a display picked before a layout change is still saved',
    async () => {
      const user = userEvent.setup();
      await pickAnotherDisplay(user);
      await refetchWithNewLayout(campaignOnGroup5);
      return saveAndReadDisplays(user);
    },
    (displayGroupIds) => expect(displayGroupIds).toContain(7),
  );
});
