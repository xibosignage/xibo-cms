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

import { screen, fireEvent, waitFor, within, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { useCampaignData } from '../hooks/useCampaignData';

import {
  EMPTY_CAMPAIGN_TABLE,
  PAGINATED_CAMPAIGNS,
  mockCampaignData,
  renderCampaignsPage,
} from './campaignTestUtils';

import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key: string) => key, i18n: { changeLanguage: vi.fn() } }),
  Trans: ({ children }: { children: React.ReactNode }) => children,
}));

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

// Bypass the 500ms debounce delay so tests don't wait on real setTimeout.
vi.mock('@/hooks/useDebounce');
vi.mock('../hooks/useCampaignData', () => ({ useCampaignData: vi.fn() }));

vi.mock('../hooks/useCampaignFilterOptions', () => ({
  useCampaignFilterOptions: vi.fn(() => ({
    filterOptions: [
      {
        label: 'Type',
        name: 'type',
        options: [
          { label: 'All', value: '' },
          { label: 'Layout List', value: 'list' },
          { label: 'Ad Campaign', value: 'ad' },
        ],
      },
      {
        label: 'Layout',
        name: 'hasLayouts',
        options: [
          { label: 'All', value: '' },
          { label: 'Yes', value: '1' },
          { label: 'No', value: '0' },
        ],
      },
      {
        label: 'Cycle Based',
        name: 'cyclePlaybackEnabled',
        options: [
          { label: 'All', value: '' },
          { label: 'Enabled', value: '1' },
          { label: 'Disabled', value: '0' },
        ],
      },
      {
        label: 'Retired',
        name: 'retired',
        options: [
          { label: 'Any', value: null },
          { label: 'No', value: 0 },
          { label: 'Yes', value: 1 },
        ],
      },
    ],
  })),
}));

vi.mock('@/hooks/usePermissions', () => ({
  usePermissions: vi.fn().mockReturnValue({ canViewFolders: true }),
}));

vi.mock('@/components/ui/FolderActionModals', () => ({ default: () => null }));
vi.mock('@/components/ui/FolderSidebar', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/Modal');

// Stub FolderBreadcrumb with a single clickable folder link for folder navigation tests.
vi.mock('@/components/ui/FolderBreadCrumb', () => ({
  default: ({ onNavigate }: { onNavigate?: (folder: { id: number; text: string }) => void }) => (
    <button onClick={() => onNavigate?.({ id: 5, text: 'Sub Folder' })}>Sub Folder</button>
  ),
}));

// =============================================================================
// Tests
// =============================================================================

describe('Campaigns page - search, filters, pagination, and folder navigation', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockCampaignData(EMPTY_CAMPAIGN_TABLE);
  });

  // ---------------------------------------------------------------------------
  // Global search input
  // ---------------------------------------------------------------------------
  test('search input accepts typed text', async () => {
    const user = userEvent.setup({ delay: null });
    renderCampaignsPage();

    const searchInput = await screen.findByPlaceholderText('Search campaign...');
    await user.type(searchInput, 'summer');

    expect(searchInput).toHaveValue('summer');
  });

  test('clearing the search resets the input to empty', async () => {
    const user = userEvent.setup({ delay: null });
    renderCampaignsPage();

    const searchInput = await screen.findByPlaceholderText('Search campaign...');
    await user.type(searchInput, 'summer');
    expect(searchInput).toHaveValue('summer');

    await user.clear(searchInput);
    expect(searchInput).toHaveValue('');
  });

  test('typing in search passes the filter param to useCampaignData', async () => {
    const user = userEvent.setup({ delay: null });
    renderCampaignsPage();

    await user.type(await screen.findByPlaceholderText('Search campaign...'), 'summer');

    await waitFor(() => {
      expect(useCampaignData).toHaveBeenLastCalledWith(
        expect.objectContaining({ filter: 'summer' }),
      );
    });
  });

  // ---------------------------------------------------------------------------
  // Pagination
  // ---------------------------------------------------------------------------
  test('pagination controls are visible when totalCount exceeds the page size', async () => {
    mockCampaignData(PAGINATED_CAMPAIGNS);
    renderCampaignsPage();

    expect(await screen.findByRole('button', { name: /Next/i })).toBeInTheDocument();
  });

  test('clicking Next increments pageIndex passed to useCampaignData', async () => {
    mockCampaignData(PAGINATED_CAMPAIGNS);
    renderCampaignsPage();

    fireEvent.click(await screen.findByRole('button', { name: /Next/i }));

    await waitFor(() => {
      expect(useCampaignData).toHaveBeenLastCalledWith(
        expect.objectContaining({
          pagination: expect.objectContaining({ pageIndex: 1 }),
        }),
      );
    });
  });

  // ---------------------------------------------------------------------------
  // Folder navigation
  // ---------------------------------------------------------------------------
  test('navigating into a subfolder via the breadcrumb passes folderId to useCampaignData', async () => {
    // The stubbed breadcrumb fires onNavigate with id: 5 when clicked.
    renderCampaignsPage();

    fireEvent.click(await screen.findByRole('button', { name: 'Sub Folder' }));

    await waitFor(() => {
      expect(useCampaignData).toHaveBeenLastCalledWith(expect.objectContaining({ folderId: 5 }));
    });
  });

  // ---------------------------------------------------------------------------
  // Advanced filters panel
  // ---------------------------------------------------------------------------
  test('clicking the Filters button opens the filter panel (Reset button becomes accessible)', async () => {
    renderCampaignsPage();

    expect(screen.queryByRole('button', { name: 'Reset' })).not.toBeInTheDocument();

    await act(async () => {
      fireEvent.click(await screen.findByRole('button', { name: 'Filters' }));
    });

    expect(screen.getByRole('button', { name: 'Reset' })).toBeInTheDocument();
  });

  // "Type" also appears as a DataTable column header — use getAllByText and
  // pick the one that is NOT inside a <th> (the column header lives in a <th>).
  const getTypeFilterContainer = () => {
    const typeLabel = screen.getAllByText('Type').find((el) => el.closest('th') === null)!;
    return typeLabel.closest('div')!;
  };

  test('selecting Type = "Layout List" passes type: "list" to useCampaignData', async () => {
    renderCampaignsPage();

    await act(async () => {
      fireEvent.click(await screen.findByRole('button', { name: 'Filters' }));
    });

    const typeContainer = getTypeFilterContainer();
    await act(async () => {
      fireEvent.click(within(typeContainer).getByRole('combobox'));
    });
    fireEvent.click(await screen.findByRole('option', { name: 'Layout List' }));

    await waitFor(() => {
      expect(useCampaignData).toHaveBeenLastCalledWith(
        expect.objectContaining({
          advancedFilters: expect.objectContaining({ type: 'list' }),
        }),
      );
    });
  });

  test('selecting Type = "Ad Campaign" passes type: "ad" to useCampaignData', async () => {
    renderCampaignsPage();

    await act(async () => {
      fireEvent.click(await screen.findByRole('button', { name: 'Filters' }));
    });

    const typeContainer = getTypeFilterContainer();
    await act(async () => {
      fireEvent.click(within(typeContainer).getByRole('combobox'));
    });
    fireEvent.click(await screen.findByRole('option', { name: 'Ad Campaign' }));

    await waitFor(() => {
      expect(useCampaignData).toHaveBeenLastCalledWith(
        expect.objectContaining({
          advancedFilters: expect.objectContaining({ type: 'ad' }),
        }),
      );
    });
  });

  test('selecting Has Layouts = "Yes" passes hasLayouts: "1" to useCampaignData', async () => {
    renderCampaignsPage();

    await act(async () => {
      fireEvent.click(await screen.findByRole('button', { name: 'Filters' }));
    });

    const layoutLabel = screen.getByText('Layout');
    const layoutContainer = layoutLabel.closest('div')!;
    await act(async () => {
      fireEvent.click(within(layoutContainer).getByRole('combobox'));
    });
    fireEvent.click(await screen.findByRole('option', { name: 'Yes' }));

    await waitFor(() => {
      expect(useCampaignData).toHaveBeenLastCalledWith(
        expect.objectContaining({
          advancedFilters: expect.objectContaining({ hasLayouts: '1' }),
        }),
      );
    });
  });

  test('selecting Has Layouts = "No" passes hasLayouts: "0" to useCampaignData', async () => {
    renderCampaignsPage();

    await act(async () => {
      fireEvent.click(await screen.findByRole('button', { name: 'Filters' }));
    });

    const layoutLabel = screen.getByText('Layout');
    const layoutContainer = layoutLabel.closest('div')!;
    await act(async () => {
      fireEvent.click(within(layoutContainer).getByRole('combobox'));
    });
    fireEvent.click(await screen.findByRole('option', { name: 'No' }));

    await waitFor(() => {
      expect(useCampaignData).toHaveBeenLastCalledWith(
        expect.objectContaining({
          advancedFilters: expect.objectContaining({ hasLayouts: '0' }),
        }),
      );
    });
  });

  test('selecting Cycle Based = "Enabled" passes cyclePlaybackEnabled: "1" to useCampaignData', async () => {
    renderCampaignsPage();

    await act(async () => {
      fireEvent.click(await screen.findByRole('button', { name: 'Filters' }));
    });

    const cycleLabel = screen.getByText('Cycle Based');
    const cycleContainer = cycleLabel.closest('div')!;
    await act(async () => {
      fireEvent.click(within(cycleContainer).getByRole('combobox'));
    });
    fireEvent.click(await screen.findByRole('option', { name: 'Enabled' }));

    await waitFor(() => {
      expect(useCampaignData).toHaveBeenLastCalledWith(
        expect.objectContaining({
          advancedFilters: expect.objectContaining({ cyclePlaybackEnabled: '1' }),
        }),
      );
    });
  });

  test('selecting Retired = "Yes" passes retired: 1 to useCampaignData', async () => {
    renderCampaignsPage();

    await act(async () => {
      fireEvent.click(await screen.findByRole('button', { name: 'Filters' }));
    });

    const retiredLabel = screen.getByText('Retired');
    const retiredContainer = retiredLabel.closest('div')!;
    await act(async () => {
      fireEvent.click(within(retiredContainer).getByRole('combobox'));
    });
    fireEvent.click(await screen.findByRole('option', { name: 'Yes' }));

    await waitFor(() => {
      expect(useCampaignData).toHaveBeenLastCalledWith(
        expect.objectContaining({
          advancedFilters: expect.objectContaining({ retired: 1 }),
        }),
      );
    });
  });

  test('clicking Reset clears all advanced filter values back to defaults', async () => {
    renderCampaignsPage();

    await act(async () => {
      fireEvent.click(await screen.findByRole('button', { name: 'Filters' }));
    });

    // Set a filter value first — pick the Type label that is NOT in a <th>.
    const typeLabel = screen.getAllByText('Type').find((el) => el.closest('th') === null)!;
    const typeContainer = typeLabel.closest('div')!;
    await act(async () => {
      fireEvent.click(within(typeContainer).getByRole('combobox'));
    });
    fireEvent.click(await screen.findByRole('option', { name: 'Ad Campaign' }));

    // Now reset — filter values should return to the initial empty state.
    await act(async () => {
      fireEvent.click(screen.getByRole('button', { name: 'Reset' }));
    });

    await waitFor(() => {
      expect(useCampaignData).toHaveBeenLastCalledWith(
        expect.objectContaining({
          advancedFilters: expect.objectContaining({ type: '' }),
        }),
      );
    });
  });
});
