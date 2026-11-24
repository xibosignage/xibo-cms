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

import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  SINGLE_CAMPAIGN,
  mockCampaign,
  mockContentManager,
  mockFetchCampaigns,
  renderCampaignsPage,
} from './campaignTestUtils';

import { createCampaign } from '@/services/campaignApi';
import { fetchLayouts } from '@/services/layoutsApi';
import { testQueryClient } from '@/setupTests';
import { findRow } from '@/testUtils/rowActions';
import type { Campaign } from '@/types/campaign';

// =============================================================================
// Module mocks
// The page, its hooks and the Add Campaign modal are all real; only the API
// layer and the folder chrome are stubbed.
// =============================================================================

vi.mock('@/services/campaignApi');
vi.mock('@/services/layoutsApi');
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
vi.mock('@/components/ui/forms/SelectFolder', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/Modal');

// Row actions use floating-ui, which JSDOM can't lay out (see Campaigns.delete.test.tsx).
vi.mock('@/components/ui/table/DataTableRowActions', () => ({
  default: () => null,
}));

// =============================================================================
// Fixtures
// =============================================================================

// A content manager (who has campaign.add) working in their own home folder
// rather than the root, so the new campaign must land there and not in the
// page's folder-1 fallback.
const HOME_FOLDER_ID = 5;
const campaignEditor = { ...mockContentManager, homeFolderId: HOME_FOLDER_ID };

const createdCampaign: Campaign = {
  ...mockCampaign,
  campaignId: 901010,
  campaign: 'Spring Promo',
  listPlayOrder: 'block',
  numberLayouts: 0,
  folderId: HOME_FOLDER_ID,
  permissionsFolderId: HOME_FOLDER_ID,
};

// =============================================================================
// Tests
// =============================================================================

describe('Adding a campaign from the Campaigns page', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchCampaigns(SINGLE_CAMPAIGN);
    vi.mocked(fetchLayouts).mockResolvedValue({ rows: [], totalCount: 0 });
  });

  test(
    'adding a campaign from the page sends the entered values and shows the new campaign',
    { tags: ['smoke'] },
    async () => {
      const user = userEvent.setup();
      vi.mocked(createCampaign).mockResolvedValueOnce(createdCampaign);

      renderCampaignsPage(campaignEditor);
      await findRow(mockCampaign.campaign);

      await user.click(screen.getByRole('button', { name: 'Add Campaign' }));
      const dialog = await screen.findByRole('dialog', { name: 'Add Campaign' });

      await user.type(screen.getByRole('textbox', { name: 'Name' }), 'Spring Promo');
      await user.click(screen.getByRole('combobox', { name: 'List play order' }));
      await user.click(await screen.findByRole('option', { name: 'Block' }));

      // Only a refetch after the save can return the new campaign.
      mockFetchCampaigns({ rows: [mockCampaign, createdCampaign], totalCount: 2 });
      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => {
        expect(createCampaign).toHaveBeenCalledWith({
          name: 'Spring Promo',
          type: 'list',
          folderId: HOME_FOLDER_ID,
          cyclePlaybackEnabled: false,
          listPlayOrder: 'block',
        });
      });
      expect(createCampaign).toHaveBeenCalledTimes(1);

      // A new list campaign hands straight over to its Edit modal, and the grid refreshes.
      await waitFor(() => expect(dialog).not.toBeInTheDocument());
      await screen.findByRole('dialog', { name: 'Edit Campaign' });
      expect(screen.getByRole('textbox', { name: 'Name' })).toHaveValue('Spring Promo');
      await findRow('Spring Promo');
    },
  );
});
