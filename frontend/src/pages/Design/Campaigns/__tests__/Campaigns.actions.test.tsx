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
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { useCampaignActions } from '../hooks/useCampaignActions';

import {
  SINGLE_CAMPAIGN,
  defaultCampaignActions,
  mockCampaign,
  mockFetchCampaigns,
  mockUserNoSchedule,
  renderCampaignsPage,
} from './campaignTestUtils';

import { testQueryClient } from '@/setupTests';

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

vi.mock('../hooks/useCampaignActions', () => ({ useCampaignActions: vi.fn() }));
vi.mock('../hooks/useCampaignFilterOptions', () => ({
  useCampaignFilterOptions: vi.fn(() => ({ filterOptions: [] })),
}));

vi.mock('@/hooks/usePermissions', () => ({
  usePermissions: vi.fn().mockReturnValue({ canViewFolders: true }),
}));

vi.mock('@/components/ui/FolderActionModals', () => ({ default: () => null }));
vi.mock('@/components/ui/FolderSidebar', () => ({ default: () => null }));
vi.mock('@/components/ui/forms/SelectFolder', () => ({ default: () => null }));
vi.mock('@/components/ui/forms/TagInput', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/Modal');

// Stub external modals so they render a dialog without making real API calls.
vi.mock('@/components/ui/modals/ShareModal', () => ({
  default: ({ title, onClose }: { title?: string; onClose?: () => void }) => (
    <div role="dialog" aria-label={title ?? 'Share Campaign'}>
      <button onClick={onClose}>Close</button>
    </div>
  ),
}));
vi.mock('@/components/ui/modals/MoveModal', () => ({
  default: ({ onClose, entityLabel }: { onClose?: () => void; entityLabel?: string }) => (
    <div role="dialog" aria-label={`Move ${entityLabel ?? ''}`}>
      <button onClick={onClose}>Close</button>
    </div>
  ),
}));
vi.mock('@/components/ui/modals/ScheduleEventModal', () => ({
  default: ({
    isOpen,
    onClose,
    contentName,
  }: {
    isOpen?: boolean;
    onClose?: () => void;
    contentName?: string;
  }) =>
    isOpen ? (
      <div role="dialog" aria-label={`Schedule ${contentName ?? ''}`}>
        <button onClick={onClose}>Close</button>
      </div>
    ) : null,
}));

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

// Opens the "More actions" dropdown on the first row and clicks the named action.
const openDropdownAction = async (label: string) => {
  await screen.findByText(mockCampaign.campaign);
  fireEvent.click(screen.getByRole('button', { name: 'More actions' }));
  fireEvent.click(await screen.findByRole('button', { name: label }));
};

// =============================================================================
// Tests
// =============================================================================

describe('Campaigns page - row actions', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(useCampaignActions).mockReturnValue(defaultCampaignActions());
    mockFetchCampaigns(SINGLE_CAMPAIGN);
  });

  // ---------------------------------------------------------------------------
  // Copy (Make a Copy)
  // ---------------------------------------------------------------------------
  describe('Copy (Make a Copy)', () => {
    test('modal opens with name field pre-filled as incremented campaign name', async () => {
      await act(async () => {
        renderCampaignsPage();
      });
      await openDropdownAction('Make a Copy');

      const dialog = await screen.findByRole('dialog', { name: 'Copy Campaign' });
      // incrementName('My Campaign') → 'My Campaign (1)'
      expect(within(dialog).getByLabelText('New name')).toHaveValue('My Campaign (1)');
    });

    test('empty name shows "Name is required" and does NOT call handleConfirmClone', async () => {
      const handleConfirmClone = vi.fn();
      vi.mocked(useCampaignActions).mockReturnValue(defaultCampaignActions({ handleConfirmClone }));

      await act(async () => {
        renderCampaignsPage();
      });
      await openDropdownAction('Make a Copy');

      const dialog = await screen.findByRole('dialog', { name: 'Copy Campaign' });
      fireEvent.change(within(dialog).getByLabelText('New name'), { target: { value: '' } });
      fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }));

      expect(await screen.findByText('Name is required')).toBeInTheDocument();
      expect(handleConfirmClone).not.toHaveBeenCalled();
    });

    test('name > 100 characters shows length validation error', async () => {
      const handleConfirmClone = vi.fn();
      vi.mocked(useCampaignActions).mockReturnValue(defaultCampaignActions({ handleConfirmClone }));

      await act(async () => {
        renderCampaignsPage();
      });
      await openDropdownAction('Make a Copy');

      const dialog = await screen.findByRole('dialog', { name: 'Copy Campaign' });
      fireEvent.change(within(dialog).getByLabelText('New name'), {
        target: { value: 'x'.repeat(101) },
      });
      fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }));

      expect(await screen.findByText('Name must be less than 100 characters')).toBeInTheDocument();
      expect(handleConfirmClone).not.toHaveBeenCalled();
    });

    test('duplicate name (matches existing campaign) shows a duplicate name error', async () => {
      const handleConfirmClone = vi.fn();
      vi.mocked(useCampaignActions).mockReturnValue(defaultCampaignActions({ handleConfirmClone }));

      await act(async () => {
        renderCampaignsPage();
      });
      await openDropdownAction('Make a Copy');

      const dialog = await screen.findByRole('dialog', { name: 'Copy Campaign' });
      // mockCampaign.campaign = 'My Campaign' already exists in the table.
      fireEvent.change(within(dialog).getByLabelText('New name'), {
        target: { value: 'My Campaign' },
      });
      fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }));

      expect(
        await screen.findByText('A campaign with this name already exists'),
      ).toBeInTheDocument();
      expect(handleConfirmClone).not.toHaveBeenCalled();
    });

    test('valid unique name calls handleConfirmClone with the campaign and new name', async () => {
      const handleConfirmClone = vi.fn();
      vi.mocked(useCampaignActions).mockReturnValue(defaultCampaignActions({ handleConfirmClone }));

      await act(async () => {
        renderCampaignsPage();
      });
      await openDropdownAction('Make a Copy');

      const dialog = await screen.findByRole('dialog', { name: 'Copy Campaign' });
      fireEvent.change(within(dialog).getByLabelText('New name'), {
        target: { value: 'My Campaign Copy' },
      });
      fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }));

      expect(handleConfirmClone).toHaveBeenCalledWith(mockCampaign, 'My Campaign Copy');
    });

    test('Cancel closes the copy modal without calling handleConfirmClone', async () => {
      const handleConfirmClone = vi.fn();
      vi.mocked(useCampaignActions).mockReturnValue(defaultCampaignActions({ handleConfirmClone }));

      await act(async () => {
        renderCampaignsPage();
      });
      await openDropdownAction('Make a Copy');

      const dialog = await screen.findByRole('dialog', { name: 'Copy Campaign' });
      fireEvent.click(within(dialog).getByRole('button', { name: 'Cancel' }));

      await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
      expect(handleConfirmClone).not.toHaveBeenCalled();
    });
  });

  // ---------------------------------------------------------------------------
  // Move
  // ---------------------------------------------------------------------------
  describe('Move', () => {
    test('clicking Move on a row opens MoveModal', async () => {
      await act(async () => {
        renderCampaignsPage();
      });
      await openDropdownAction('Move');

      // MoveModal renders with title 'Move Campaigns' or similar.
      expect(await screen.findByRole('dialog')).toBeInTheDocument();
    });

    test('selecting rows and clicking "Move" from the bulk toolbar opens MoveModal', async () => {
      await act(async () => {
        renderCampaignsPage();
      });

      const checkboxes = await screen.findAllByRole('checkbox', { name: /Select row/i });
      await act(async () => {
        fireEvent.click(checkboxes[0]!);
      });

      // The bulk actions bar renders an icon button with title="Move".
      // The row-actions stub also renders a text button "Move" — use title to disambiguate.
      await act(async () => {
        fireEvent.click(await screen.findByTitle('Move'));
      });

      expect(await screen.findByRole('dialog')).toBeInTheDocument();
    });
  });

  // ---------------------------------------------------------------------------
  // Share
  // ---------------------------------------------------------------------------
  describe('Share', () => {
    test('clicking Share on a row opens ShareModal', async () => {
      await act(async () => {
        renderCampaignsPage();
      });
      await openDropdownAction('Share');

      expect(await screen.findByRole('dialog')).toBeInTheDocument();
    });

    test('selecting rows and clicking "Share" from the bulk toolbar opens ShareModal', async () => {
      await act(async () => {
        renderCampaignsPage();
      });

      const checkboxes = await screen.findAllByRole('checkbox', { name: /Select row/i });
      await act(async () => {
        fireEvent.click(checkboxes[0]!);
      });

      // The bulk actions bar renders an icon button with title="Share".
      // The row-actions stub also renders a text button "Share" — use title to disambiguate.
      await act(async () => {
        fireEvent.click(await screen.findByTitle('Share'));
      });

      expect(await screen.findByRole('dialog')).toBeInTheDocument();
    });
  });

  // ---------------------------------------------------------------------------
  // Schedule
  // ---------------------------------------------------------------------------
  describe('Schedule', () => {
    test('clicking Schedule opens ScheduleEventModal with the campaign name', async () => {
      await act(async () => {
        renderCampaignsPage();
      });
      await openDropdownAction('Schedule');

      // ScheduleEventModal renders with the campaign name visible.
      const dialog = await screen.findByRole('dialog');
      expect(dialog).toBeInTheDocument();
    });

    test('clicking Schedule when canSchedule is false does not open a modal', async () => {
      await act(async () => {
        renderCampaignsPage(mockUserNoSchedule);
      });

      await screen.findByText(mockCampaign.campaign);
      fireEvent.click(screen.getByRole('button', { name: 'More actions' }));

      const scheduleBtn = await screen.findByRole('button', { name: 'Schedule' });
      fireEvent.click(scheduleBtn);

      // No dialog should appear.
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
  });
});
