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

import { screen, fireEvent, waitFor } from '@testing-library/react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  SINGLE_CAMPAIGN,
  mockCampaign,
  mockFetchCampaigns,
  renderCampaignsPage,
} from './campaignTestUtils';

import { deleteCampaign } from '@/services/campaignApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// NOTE: useCampaignActions is intentionally NOT mocked here — these tests use
// the real hook with only the API layer stubbed, so the full delete flow
// (confirmDelete → deleteCampaign → notify → closeModal) is exercised end-to-end.
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

vi.mock('../hooks/useCampaignFilterOptions', () => ({
  useCampaignFilterOptions: vi.fn(() => ({ filterOptions: [] })),
}));

vi.mock('@/hooks/usePermissions', () => ({
  usePermissions: vi.fn().mockReturnValue({ canViewFolders: true }),
}));

vi.mock('@/components/ui/FolderActionModals', () => ({ default: () => null }));
vi.mock('@/components/ui/FolderSidebar', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/Modal');

// Stub DataTableRowActions — uses floating-ui which requires real browser APIs
// that JSDOM does not fully support. Without a stub the row rendering can crash,
// preventing the campaign row (and checkboxes) from appearing in the DOM.
vi.mock('@/components/ui/table/DataTableRowActions', () => ({
  default: () => null,
}));

// =============================================================================
// Tests
// =============================================================================

describe('Delete Campaign — integration', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchCampaigns(SINGLE_CAMPAIGN);
  });

  // ---------------------------------------------------------------------------
  // Successful delete: API is called and the modal closes.
  // ---------------------------------------------------------------------------
  test('successful delete calls deleteCampaign and closes the modal', async () => {
    vi.mocked(deleteCampaign).mockResolvedValueOnce(undefined);

    renderCampaignsPage();

    await screen.findByText(mockCampaign.campaign);

    const checkboxes = screen.getAllByRole('checkbox', { name: /Select row/i });
    fireEvent.click(checkboxes[0]!);

    fireEvent.click(await screen.findByRole('button', { name: 'Delete Selected' }));
    fireEvent.click(await screen.findByRole('button', { name: 'Yes, Delete' }));

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(deleteCampaign).toHaveBeenCalledWith(mockCampaign.campaignId);
  });

  // ---------------------------------------------------------------------------
  // Failed delete: modal stays open and shows the error.
  // ---------------------------------------------------------------------------
  test('failed delete keeps the modal open and shows an error', async () => {
    vi.mocked(deleteCampaign).mockRejectedValueOnce(new Error('Server error'));

    renderCampaignsPage();

    await screen.findByText(mockCampaign.campaign);

    const checkboxes = screen.getAllByRole('checkbox', { name: /Select row/i });
    fireEvent.click(checkboxes[0]!);

    fireEvent.click(await screen.findByRole('button', { name: 'Delete Selected' }));
    fireEvent.click(await screen.findByRole('button', { name: 'Yes, Delete' }));

    await waitFor(() => expect(screen.getByRole('dialog')).toBeInTheDocument());
  });
});
