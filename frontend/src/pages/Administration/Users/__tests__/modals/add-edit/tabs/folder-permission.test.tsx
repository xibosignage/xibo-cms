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
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockFolderTree, mockUser } from '../../../fixtures/user';
import { setupAddEditUserModalMocks } from '../../../mocks/addEditUserModal';
import { renderAddEditUserModal } from '../helpers/renderAddEditUserModal';

import { fetchFolderById, fetchFolderTree } from '@/services/folderApi';
import { fetchGroupFolderPermissions } from '@/services/permissionsApi';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/userApi');

vi.mock('@/services/userGroupApi');

vi.mock('@/services/folderApi');

vi.mock('@/services/permissionsApi');

vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Helpers
// =============================================================================

const openFolderPermissionTab = async (user: ReturnType<typeof userEvent.setup>) => {
  await screen.findByRole('textbox', { name: /^username$/i });
  await user.click(screen.getByRole('tab', { name: /folder permission/i }));
  // "Marketing" may appear both as a tree row and as a permission tag —
  // findAllByText just confirms the tree has loaded.
  return screen.findAllByText('Marketing');
};

// =============================================================================
// Tests
// =============================================================================

describe('AddEditUserModal - Folder Permission tab', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    setupAddEditUserModalMocks();
    vi.mocked(fetchFolderById).mockResolvedValue({ ...mockFolderTree[0]!, text: 'Root' });
  });

  test("the home folder selector shows the draft's currently selected folder", async () => {
    const user = userEvent.setup();
    renderAddEditUserModal({ mode: 'add' });
    await openFolderPermissionTab(user);

    expect(await screen.findByRole('button', { name: 'Root' })).toBeInTheDocument();
  });

  test('previously granted folder permissions render as removable tags', async () => {
    vi.mocked(fetchGroupFolderPermissions).mockResolvedValue(
      new Map([[2, { view: 1, edit: 1, delete: 0 }]]),
    );

    const user = userEvent.setup();
    renderAddEditUserModal({ mode: 'edit', user: mockUser });
    await openFolderPermissionTab(user);

    expect(await screen.findByRole('button', { name: 'Remove Marketing' })).toBeInTheDocument();
  });

  test('removing a permission tag removes that folder from the permission set', async () => {
    vi.mocked(fetchGroupFolderPermissions).mockResolvedValue(
      new Map([[2, { view: 1, edit: 1, delete: 0 }]]),
    );

    const user = userEvent.setup();
    renderAddEditUserModal({ mode: 'edit', user: mockUser });
    await openFolderPermissionTab(user);

    await user.click(await screen.findByRole('button', { name: 'Remove Marketing' }));

    await waitFor(() => {
      expect(screen.queryByRole('button', { name: 'Remove Marketing' })).not.toBeInTheDocument();
    });
  });

  test('"Clear All" empties the permission map', async () => {
    vi.mocked(fetchGroupFolderPermissions).mockResolvedValue(
      new Map([[2, { view: 1, edit: 1, delete: 0 }]]),
    );

    const user = userEvent.setup();
    renderAddEditUserModal({ mode: 'edit', user: mockUser });
    await openFolderPermissionTab(user);
    await screen.findByRole('button', { name: 'Remove Marketing' });

    await user.click(screen.getByRole('button', { name: /clear all/i }));

    await waitFor(() => {
      expect(screen.queryByRole('button', { name: 'Remove Marketing' })).not.toBeInTheDocument();
    });
  });

  test('"Loading folders…" is shown while the folder tree is being fetched', async () => {
    let resolveFolders!: (v: typeof mockFolderTree) => void;
    vi.mocked(fetchFolderTree).mockReturnValue(
      new Promise((res) => {
        resolveFolders = res;
      }),
    );

    const user = userEvent.setup();
    renderAddEditUserModal({ mode: 'add' });
    await screen.findByRole('textbox', { name: /^username$/i });
    await user.click(screen.getByRole('tab', { name: /folder permission/i }));

    await screen.findByText(/loading folders/i);

    resolveFolders(mockFolderTree);
    await waitFor(() => expect(screen.queryByText(/loading folders/i)).not.toBeInTheDocument());
  });

  test('typing in the folder search box updates the search filter', async () => {
    const user = userEvent.setup();
    renderAddEditUserModal({ mode: 'add' });
    await openFolderPermissionTab(user);

    // Before filtering, "Root" appears twice: once as the home folder
    // selector's label, once as its own row in the (fully expanded) tree.
    expect(screen.getAllByText('Root')).toHaveLength(2);

    const searchInput = screen.getByPlaceholderText('Search Folder');
    await user.type(searchInput, 'Market');

    expect(searchInput).toHaveValue('Market');
    expect(screen.getByText('Marketing')).toBeInTheDocument();
    // The tree row for "Root" is filtered out — only the selector label remains.
    expect(screen.getAllByText('Root')).toHaveLength(1);
  });
});
