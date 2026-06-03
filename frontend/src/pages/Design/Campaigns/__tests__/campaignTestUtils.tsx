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
import { render } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { vi } from 'vitest';

import Campaigns from '../Campaigns';
import type { useCampaignActions } from '../hooks/useCampaignActions';
import { useCampaignData } from '../hooks/useCampaignData';

import { UserProvider } from '@/context/UserContext';
import { fetchCampaigns } from '@/services/campaignApi';
import type { FetchCampaignResponse } from '@/services/campaignApi';
import { testQueryClient } from '@/setupTests';
import type { Campaign } from '@/types/campaign';
import { UserType } from '@/types/user';
import type { User } from '@/types/user';

// -----------------------------------------------------------------------------
// Mock campaign fixtures
// campaignId 901001 is a standard list campaign — used in most tests.
// -----------------------------------------------------------------------------
export const mockCampaign: Campaign = {
  campaignId: 901001,
  ownerId: 1,
  type: 'list',
  campaign: 'My Campaign',
  isLayoutSpecific: 0,
  numberLayouts: 2,
  totalDuration: 60,
  tags: [],
  folderId: 1,
  permissionsFolderId: 1,
  cyclePlaybackEnabled: 0,
  playCount: 1,
  listPlayOrder: 'round',
  targetType: null,
  target: 0,
  startDt: 0,
  endDt: 0,
  plays: 0,
  spend: 0,
  impressions: 0,
  lastPopId: null,
  ref1: null,
  ref2: null,
  ref3: null,
  ref4: null,
  ref5: null,
  createdAt: '2026-01-01 00:00:00',
  modifiedAt: '2026-03-01 10:00:00',
  modifiedBy: 1,
  modifiedByName: 'TestUser',
  displayGroupIds: [],
  retired: 0,
};

// Ad campaign — has targetType/target, non-zero startDt/endDt.
// Unix timestamps chosen to be clearly non-zero for date rendering tests.
export const mockAdCampaign: Campaign = {
  ...mockCampaign,
  campaignId: 901002,
  type: 'ad',
  campaign: 'My Ad Campaign',
  targetType: 'plays',
  target: 1000,
  startDt: 1748736000,
  endDt: 1751328000,
};

// Cycle playback campaign — used in edit modal tests.
export const mockCycleCampaign: Campaign = {
  ...mockCampaign,
  campaignId: 901003,
  campaign: 'My Cycle Campaign',
  cyclePlaybackEnabled: 1,
  playCount: 3,
};

// Campaign with reference fields — used in edit modal reference tab tests.
export const mockCampaignWithRefs: Campaign = {
  ...mockCampaign,
  campaignId: 901004,
  campaign: 'Campaign With Refs',
  ref1: 'ref-one',
  ref2: 'ref-two',
  ref3: '',
  ref4: null,
  ref5: null,
};

// -----------------------------------------------------------------------------
// Mock user fixtures
// -----------------------------------------------------------------------------
export const mockUser: User = {
  userId: 1,
  userName: 'TestUser',
  userTypeId: UserType.SuperAdmin,
  groupId: 1,
  homeFolderId: 1,
  features: {
    'folder.view': true,
    'schedule.add': true,
  },
  settings: {
    defaultTimezone: 'UTC',
    defaultLanguage: 'en',
    DATE_FORMAT_JS: 'DD/MM/YYYY',
    TIME_FORMAT_JS: 'HH:mm',
  },
};

// User without schedule permission — used to test Schedule action gating.
export const mockUserNoSchedule: User = {
  ...mockUser,
  features: {
    'folder.view': true,
  },
};

// -----------------------------------------------------------------------------
// Table data fixtures
// -----------------------------------------------------------------------------
export const SINGLE_CAMPAIGN: FetchCampaignResponse = {
  rows: [mockCampaign],
  totalCount: 1,
};

export const SINGLE_AD_CAMPAIGN: FetchCampaignResponse = {
  rows: [mockAdCampaign],
  totalCount: 1,
};

export const EMPTY_CAMPAIGN_TABLE: FetchCampaignResponse = {
  rows: [],
  totalCount: 0,
};

// 10 rows with totalCount: 25 so pagination controls render.
export const PAGINATED_CAMPAIGNS: FetchCampaignResponse = {
  rows: Array.from({ length: 10 }).map((_, i) => ({
    ...mockCampaign,
    campaignId: i + 1,
    campaign: `Campaign ${i + 1}`,
  })),
  totalCount: 25,
};

// -----------------------------------------------------------------------------
// Typed mock helpers
// -----------------------------------------------------------------------------
export type UseCampaignDataReturn = ReturnType<typeof useCampaignData>;
export type UseCampaignActionsReturn = ReturnType<typeof useCampaignActions>;

// Stubs useCampaignData to return data directly (bypasses React Query + fetchCampaigns).
// Use in search / pagination tests where you want immediate, synchronous data.
export const mockCampaignData = (rawData: FetchCampaignResponse) => {
  vi.mocked(useCampaignData).mockReturnValue({
    data: rawData,
    isFetching: false,
    isError: false,
    error: null,
  } as UseCampaignDataReturn);
};

// Stubs fetchCampaigns so the real useCampaignData + React Query can run.
// Use in integration-style tests (delete, actions) where you want the real query pipeline.
export const mockFetchCampaigns = (rawData: FetchCampaignResponse) => {
  vi.mocked(fetchCampaigns).mockResolvedValue(rawData);
};

// Returns a fresh useCampaignActions mock value — call in beforeEach to reset state.
export const defaultCampaignActions = (
  overrides: Partial<UseCampaignActionsReturn> = {},
): UseCampaignActionsReturn =>
  ({
    isDeleting: false,
    deleteError: null,
    setDeleteError: vi.fn(),
    isCloning: false,
    confirmDelete: vi.fn(),
    handleConfirmClone: vi.fn(),
    handleConfirmMove: vi.fn(),
    ...overrides,
  }) as UseCampaignActionsReturn;

// -----------------------------------------------------------------------------
// Render wrapper — provides all required context providers.
// Pass `user` to override the default mockUser (e.g. for schedule-gate tests).
// -----------------------------------------------------------------------------
export const renderCampaignsPage = (user: User = mockUser) => {
  testQueryClient.setQueryData(['userPref', 'campaign_page'], null);
  return render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={user}>
        <MemoryRouter>
          <Campaigns />
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );
};
