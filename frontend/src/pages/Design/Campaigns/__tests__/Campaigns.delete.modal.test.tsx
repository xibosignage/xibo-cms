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

import { screen, fireEvent, within, act } from '@testing-library/react';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { useCampaignActions } from '../hooks/useCampaignActions';

import {
  SINGLE_CAMPAIGN,
  defaultCampaignActions,
  mockCampaign,
  mockCampaignData,
  renderCampaignsPage,
} from './campaignTestUtils';

import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/campaignApi');
vi.mock('../hooks/useCampaignData', () => ({ useCampaignData: vi.fn() }));
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

// Stub the row actions dropdown so Delete is always visible.
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

const openDeleteModal = async () => {
  await screen.findByText(mockCampaign.campaign);
  fireEvent.click(screen.getByRole('button', { name: 'More actions' }));
  fireEvent.click(await screen.findByRole('button', { name: 'Delete' }));
  return screen.findByRole('dialog');
};

// =============================================================================
// Tests
// =============================================================================

describe('Campaigns page - delete modal', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(useCampaignActions).mockReturnValue(defaultCampaignActions());
    mockCampaignData(SINGLE_CAMPAIGN);
  });

  // ---------------------------------------------------------------------------
  // Opening the modal
  // ---------------------------------------------------------------------------
  test('clicking Delete on a row opens the Delete confirmation modal', async () => {
    await act(async () => {
      renderCampaignsPage();
    });

    const dialog = await openDeleteModal();

    expect(dialog).toBeInTheDocument();
    expect(within(dialog).getByText('Delete Campaign?')).toBeInTheDocument();
    expect(within(dialog).getByText(mockCampaign.campaign)).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Cancel closes the modal
  // ---------------------------------------------------------------------------
  test('clicking Cancel closes the Delete modal', async () => {
    await act(async () => {
      renderCampaignsPage();
    });

    await openDeleteModal();
    expect(screen.getByRole('dialog')).toBeInTheDocument();

    await act(async () => {
      fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));
    });

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Confirm calls confirmDelete
  // ---------------------------------------------------------------------------
  test('clicking "Yes, Delete" calls confirmDelete with the campaign', async () => {
    const confirmDelete = vi.fn();
    vi.mocked(useCampaignActions).mockReturnValue(defaultCampaignActions({ confirmDelete }));

    await act(async () => {
      renderCampaignsPage();
    });

    await openDeleteModal();

    await act(async () => {
      fireEvent.click(screen.getByRole('button', { name: 'Yes, Delete' }));
    });

    expect(confirmDelete).toHaveBeenCalledTimes(1);
    expect(confirmDelete).toHaveBeenCalledWith([mockCampaign]);
  });

  // ---------------------------------------------------------------------------
  // Error stays in modal
  // ---------------------------------------------------------------------------
  test('shows a delete error inside the modal when deletion fails', async () => {
    vi.mocked(useCampaignActions).mockReturnValue(
      defaultCampaignActions({ deleteError: 'Campaign is scheduled and cannot be deleted' }),
    );

    await act(async () => {
      renderCampaignsPage();
    });

    await openDeleteModal();

    expect(screen.getByText('Campaign is scheduled and cannot be deleted')).toBeInTheDocument();
    expect(screen.getByRole('dialog')).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Bulk delete
  // ---------------------------------------------------------------------------
  test('selecting a row and clicking "Delete Selected" opens the bulk Delete modal', async () => {
    await act(async () => {
      renderCampaignsPage();
    });

    const checkboxes = await screen.findAllByRole('checkbox', { name: /Select row/i });
    await act(async () => {
      fireEvent.click(checkboxes[0]!);
    });

    await act(async () => {
      fireEvent.click(await screen.findByRole('button', { name: 'Delete Selected' }));
    });

    expect(await screen.findByRole('dialog')).toBeInTheDocument();
  });

  test('bulk delete modal heading says "Delete Campaigns?" when multiple items are selected', async () => {
    const multipleCampaigns = {
      rows: [mockCampaign, { ...mockCampaign, campaignId: 901010, campaign: 'Second Campaign' }],
      totalCount: 2,
    };
    mockCampaignData(multipleCampaigns);

    await act(async () => {
      renderCampaignsPage();
    });

    const selectAllCheckbox = await screen.findByRole('checkbox', { name: /select all rows/i });
    await act(async () => {
      fireEvent.click(selectAllCheckbox);
    });

    await act(async () => {
      fireEvent.click(await screen.findByRole('button', { name: 'Delete Selected' }));
    });

    const dialog = await screen.findByRole('dialog');
    expect(within(dialog).getByText('Delete Campaigns?')).toBeInTheDocument();
  });
});
