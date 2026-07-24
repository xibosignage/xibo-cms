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

import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  mockSuperAdmin,
  mockFolderTree,
  mockGroupAdmin,
  mockUser,
  mockUserGroup,
} from '../../../fixtures/user';
import { renderAddEditUserModal } from '../helpers/renderAddEditUserModal';

import { fetchFolderTree } from '@/services/folderApi';
import { fetchGroupFolderPermissions } from '@/services/permissionsApi';
import { fetchHomepages } from '@/services/userApi';
import { fetchUserGroups } from '@/services/userGroupApi';

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

const openOptionsTab = async (user: ReturnType<typeof userEvent.setup>) => {
  await screen.findByRole('textbox', { name: /^username$/i });
  await user.click(screen.getByRole('tab', { name: /^options$/i }));
  return screen.findByRole('switch', { name: /hide navigation/i });
};

// =============================================================================
// Tests
// =============================================================================

describe('AddEditUserModal - Options tab', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(fetchFolderTree).mockResolvedValue(mockFolderTree);
    vi.mocked(fetchGroupFolderPermissions).mockResolvedValue(new Map());
    vi.mocked(fetchUserGroups).mockResolvedValue({ rows: [mockUserGroup], totalCount: 1 });
    vi.mocked(fetchHomepages).mockResolvedValue([]);
  });

  test("Hide User Guide and Force Password Change reflect the draft's values", async () => {
    const user = userEvent.setup();
    renderAddEditUserModal({
      mode: 'edit',
      user: {
        ...mockUser,
        newUserWizard: 0,
        isPasswordChangeRequired: 1,
      },
      currentUser: mockSuperAdmin,
    });
    await openOptionsTab(user);

    expect(screen.getByRole('switch', { name: /hide user guide/i })).toHaveAttribute(
      'aria-checked',
      'false',
    );
    expect(screen.getByRole('switch', { name: /force password change/i })).toHaveAttribute(
      'aria-checked',
      'true',
    );
  });

  // The edit-mode load effect in AddEditUserModal hardcodes hideNavigation: 0
  // regardless of the user prop — `User` has no `hideNavigation` field, so
  // there is nothing to pre-fill from. This is intentional, not a bug.
  test('Hide Navigation always starts unchecked in edit mode', async () => {
    const user = userEvent.setup();
    renderAddEditUserModal({ mode: 'edit', user: mockUser, currentUser: mockSuperAdmin });
    await openOptionsTab(user);

    expect(screen.getByRole('switch', { name: /hide navigation/i })).toHaveAttribute(
      'aria-checked',
      'false',
    );
  });

  test('Disable Two Factor switch is shown only in edit mode for a super admin', async () => {
    const user = userEvent.setup();
    renderAddEditUserModal({ mode: 'edit', user: mockUser, currentUser: mockSuperAdmin });
    await openOptionsTab(user);

    expect(
      screen.getByRole('switch', { name: /disable two factor authentication/i }),
    ).toBeInTheDocument();
  });

  test('Disable Two Factor switch is hidden in add mode', async () => {
    const user = userEvent.setup();
    renderAddEditUserModal({ mode: 'add', currentUser: mockSuperAdmin });
    await screen.findByRole('textbox', { name: /^username$/i });
    await user.click(screen.getByRole('tab', { name: /^options$/i }));
    await screen.findByRole('switch', { name: /hide navigation/i });

    expect(
      screen.queryByRole('switch', { name: /disable two factor authentication/i }),
    ).not.toBeInTheDocument();
  });

  test('Disable Two Factor switch is hidden for a non-superAdmin', async () => {
    const user = userEvent.setup();
    renderAddEditUserModal({ mode: 'edit', user: mockUser, currentUser: mockGroupAdmin });
    await openOptionsTab(user);

    expect(
      screen.queryByRole('switch', { name: /disable two factor authentication/i }),
    ).not.toBeInTheDocument();
  });

  test('toggling Force Password Change changes only that flag', async () => {
    const user = userEvent.setup();
    renderAddEditUserModal({
      mode: 'edit',
      user: { ...mockUser, isPasswordChangeRequired: 0 },
      currentUser: mockSuperAdmin,
    });
    await openOptionsTab(user);

    await user.click(screen.getByRole('switch', { name: /force password change/i }));

    expect(screen.getByRole('switch', { name: /force password change/i })).toHaveAttribute(
      'aria-checked',
      'true',
    );
    expect(screen.getByRole('switch', { name: /hide navigation/i })).toHaveAttribute(
      'aria-checked',
      'false',
    );
  });
});
