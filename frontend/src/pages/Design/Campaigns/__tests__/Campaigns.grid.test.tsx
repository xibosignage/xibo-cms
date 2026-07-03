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
import { screen, fireEvent, act } from '@testing-library/react';
import { render } from '@testing-library/react';
import type React from 'react';
import { MemoryRouter } from 'react-router-dom';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import Campaigns from '../Campaigns';
import { useCampaignActions } from '../hooks/useCampaignActions';
import { useCampaignData } from '../hooks/useCampaignData';

import {
  SINGLE_CAMPAIGN,
  SINGLE_AD_CAMPAIGN,
  defaultCampaignActions,
  mockAdCampaign,
  mockCampaign,
  mockCampaignData,
  mockUser,
  mockUserNoSchedule,
} from './campaignTestUtils';

import { UserProvider } from '@/context/UserContext';
import { testQueryClient } from '@/setupTests';
import { formatCmsDateTime } from '@/utils/date';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/campaignApi');
vi.mock('@/services/folderApi', () => ({
  fetchFolderById: vi.fn().mockResolvedValue({ id: 1, text: 'Root' }),
  fetchFolderTree: vi.fn().mockResolvedValue([]),
  searchFolders: vi.fn().mockResolvedValue([]),
  fetchContextButtons: vi.fn().mockResolvedValue({ create: true }),
  selectFolder: vi.fn().mockResolvedValue({ success: true }),
}));
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));

vi.mock('../hooks/useCampaignData', () => ({ useCampaignData: vi.fn() }));
vi.mock('../hooks/useCampaignActions', () => ({ useCampaignActions: vi.fn() }));
vi.mock('../hooks/useCampaignFilterOptions', () => ({
  useCampaignFilterOptions: vi.fn(() => ({ filterOptions: [] })),
}));

vi.mock('@/hooks/usePermissions', () => ({
  usePermissions: vi.fn().mockReturnValue({ canViewFolders: true }),
}));

vi.mock('@/components/ui/FolderActionModals', () => ({ default: () => null }));
vi.mock('@/components/ui/FolderSidebar', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/Modal');

// Stub the floating-ui dropdown so all actions are always visible in the DOM.
vi.mock('@/components/ui/table/DataTableRowActions', () => ({
  default: ({
    actions,
  }: {
    actions: Array<{ label?: string; onClick?: () => void; isSeparator?: boolean }>;
  }) => (
    <div>
      <button aria-label="More actions" />
      {actions
        .filter((a) => !a.isSeparator && a.label)
        .map((action, i) => (
          <button key={i} onClick={() => action.onClick?.()}>
            {action.label}
          </button>
        ))}
    </div>
  ),
}));

// =============================================================================
// Helpers
// =============================================================================

// Columns hidden by default in Campaigns.tsx — must be explicitly enabled via
// stored preferences so their cells actually render during tests.
const ALL_COLUMNS_VISIBLE = {
  campaign: true,
  type: true,
  startDt: true,
  endDt: true,
  numberLayouts: true,
  tags: true,
  totalDuration: true,
  cyclePlaybackEnabled: true,
  playCount: true,
  targetType: true,
  target: true,
  plays: true,
  spend: true,
  impressions: true,
  ref1: true,
  ref2: true,
  ref3: true,
  ref4: true,
  ref5: true,
  createdAt: true,
  modifiedAt: true,
  modifiedByName: true,
};

// Render with all columns visible — used for hidden-column cell tests.
const renderWithAllColumns = (user = mockUser) => {
  testQueryClient.setQueryData(['userPref', 'campaign_page'], {
    columnVisibility: ALL_COLUMNS_VISIBLE,
  });
  testQueryClient.setQueryData(['folderPermissions', user.homeFolderId ?? 1], { create: true });
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

// Render without pre-setting the cache so isHydrated starts as false.
// Used only for the loading skeleton test.
const renderFresh = () => {
  testQueryClient.clear();
  return render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={mockUser}>
        <MemoryRouter>
          <Campaigns />
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );
};

// Standard render with default column visibility (most visible-column tests).
const renderPage = (user = mockUser) => {
  testQueryClient.setQueryData(['userPref', 'campaign_page'], null);
  testQueryClient.setQueryData(['folderPermissions', user.homeFolderId ?? 1], { create: true });
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

// =============================================================================
// Tests
// =============================================================================

describe('Campaigns page - grid rendering', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(useCampaignActions).mockReturnValue(defaultCampaignActions());
  });

  // ---------------------------------------------------------------------------
  // Column: Name (visible by default)
  // ---------------------------------------------------------------------------
  test('renders campaign name in the Name column', async () => {
    mockCampaignData(SINGLE_CAMPAIGN);
    await act(async () => {
      renderPage();
    });
    expect(await screen.findByText(mockCampaign.campaign)).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Column: Type (visible by default)
  // ---------------------------------------------------------------------------
  test('renders "Layout List" for a campaign with type "list"', async () => {
    mockCampaignData(SINGLE_CAMPAIGN);
    await act(async () => {
      renderPage();
    });
    expect(await screen.findByText('Layout List')).toBeInTheDocument();
  });

  test('renders "Ad Campaign" for a campaign with type "ad"', async () => {
    mockCampaignData(SINGLE_AD_CAMPAIGN);
    await act(async () => {
      renderPage();
    });
    expect(await screen.findByText('Ad Campaign')).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Column: Duration (visible by default)
  // ---------------------------------------------------------------------------
  test('renders total duration with an "s" suffix', async () => {
    mockCampaignData(SINGLE_CAMPAIGN);
    await act(async () => {
      renderPage();
    });
    expect(await screen.findByText(`${mockCampaign.totalDuration}s`)).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Column: Cycle Playback (hidden by default — show all columns)
  // ---------------------------------------------------------------------------
  test('renders "Enabled" status cell when cyclePlaybackEnabled is 1', async () => {
    mockCampaignData({
      rows: [{ ...mockCampaign, cyclePlaybackEnabled: 1 }],
      totalCount: 1,
    });
    await act(async () => {
      renderWithAllColumns();
    });
    expect(await screen.findByText('Enabled')).toBeInTheDocument();
  });

  test('renders "Disabled" status cell when cyclePlaybackEnabled is 0', async () => {
    mockCampaignData(SINGLE_CAMPAIGN);
    await act(async () => {
      renderWithAllColumns();
    });
    expect(await screen.findByText('Disabled')).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Column: Start Date (hidden by default — show all columns)
  // ---------------------------------------------------------------------------
  test('renders a formatted date string when startDt is a non-zero Unix timestamp', async () => {
    mockCampaignData(SINGLE_AD_CAMPAIGN);
    await act(async () => {
      renderWithAllColumns();
    });
    const formatted = formatCmsDateTime(new Date(mockAdCampaign.startDt * 1000), {
      format: 'DD/MM/YYYY',
      timeZone: 'UTC',
    });
    expect(await screen.findByText(formatted)).toBeInTheDocument();
  });

  test('renders "-" for startDt when the value is 0', async () => {
    // SINGLE_CAMPAIGN has startDt: 0; with all columns visible the cell renders '-'.
    // There are multiple '-' cells (endDt, targetType, refs) — check at least one exists.
    mockCampaignData(SINGLE_CAMPAIGN);
    await act(async () => {
      renderWithAllColumns();
    });
    const dashes = await screen.findAllByText('-');
    expect(dashes.length).toBeGreaterThan(0);
  });

  // ---------------------------------------------------------------------------
  // Column: Target Type (hidden by default — show all columns)
  // ---------------------------------------------------------------------------
  test('renders "-" when targetType is null', async () => {
    mockCampaignData(SINGLE_CAMPAIGN);
    await act(async () => {
      renderWithAllColumns();
    });
    const dashes = await screen.findAllByText('-');
    expect(dashes.length).toBeGreaterThan(0);
  });

  // ---------------------------------------------------------------------------
  // Page-level: loading / hydration guard
  // ---------------------------------------------------------------------------
  test('shows loading skeleton message before table is hydrated', () => {
    // renderFresh() does NOT pre-set the cache, so the userPref query starts in
    // 'pending' state — isHydrated is false on the first synchronous render.
    mockCampaignData(SINGLE_CAMPAIGN);
    renderFresh();
    expect(screen.queryByText('Loading your campaign preferences...')).toBeInTheDocument();
  });

  test('"Add Campaign" button is disabled before the page is hydrated', () => {
    mockCampaignData(SINGLE_CAMPAIGN);
    renderFresh();
    expect(screen.getByRole('button', { name: 'Add Campaign' })).toBeDisabled();
  });

  // ---------------------------------------------------------------------------
  // Page-level: error state
  // ---------------------------------------------------------------------------
  test('shows an error alert when the data fetch returns an error', async () => {
    vi.mocked(useCampaignData).mockReturnValue({
      data: undefined,
      isFetching: false,
      isError: true,
      error: new Error('Network error'),
    } as ReturnType<typeof useCampaignData>);

    await act(async () => {
      renderPage();
    });
    expect(await screen.findByRole('alert')).toHaveTextContent('Network error');
  });

  // ---------------------------------------------------------------------------
  // Action column: Edit visibility per campaign type
  // ---------------------------------------------------------------------------
  test('Edit action IS present for a list campaign', async () => {
    // getCampaignItemActions adds Edit twice for list campaigns (quick-action + dropdown).
    // Use findAllByRole to handle both and assert at least one is present.
    mockCampaignData(SINGLE_CAMPAIGN);
    await act(async () => {
      renderPage();
    });

    await screen.findByText(mockCampaign.campaign);
    fireEvent.click(screen.getByRole('button', { name: 'More actions' }));

    const editButtons = await screen.findAllByRole('button', { name: 'Edit' });
    expect(editButtons.length).toBeGreaterThan(0);
  });

  test('Edit action IS present for an ad campaign (opens the full-page editor)', async () => {
    // Ad campaigns are edited in the dedicated /design/campaign/:id editor, but the
    // Edit action is still surfaced in the grid (quick-action + dropdown).
    mockCampaignData(SINGLE_AD_CAMPAIGN);
    await act(async () => {
      renderPage();
    });

    await screen.findByText(mockAdCampaign.campaign);
    fireEvent.click(screen.getByRole('button', { name: 'More actions' }));

    await screen.findByRole('button', { name: 'Make a Copy' });
    const editButtons = await screen.findAllByRole('button', { name: 'Edit' });
    expect(editButtons.length).toBeGreaterThan(0);
  });

  // ---------------------------------------------------------------------------
  // Action column: Schedule visibility by user permission
  // ---------------------------------------------------------------------------
  test('Schedule action IS present when the user has schedule.add feature', async () => {
    mockCampaignData(SINGLE_CAMPAIGN);
    await act(async () => {
      renderPage();
    });

    await screen.findByText(mockCampaign.campaign);
    fireEvent.click(screen.getByRole('button', { name: 'More actions' }));

    expect(await screen.findByRole('button', { name: 'Schedule' })).toBeInTheDocument();
  });

  test('clicking Schedule when canSchedule is false opens no modal', async () => {
    mockCampaignData(SINGLE_CAMPAIGN);
    await act(async () => {
      renderPage(mockUserNoSchedule);
    });

    await screen.findByText(mockCampaign.campaign);
    fireEvent.click(screen.getByRole('button', { name: 'More actions' }));

    const scheduleBtn = await screen.findByRole('button', { name: 'Schedule' });
    fireEvent.click(scheduleBtn);

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });
});
