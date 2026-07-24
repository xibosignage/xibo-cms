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

import { mockSuperAdmin, mockFolderTree, mockUser, mockUserGroup } from '../../../fixtures/user';
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

const openNotificationsTab = async (user: ReturnType<typeof userEvent.setup>) => {
  await screen.findByRole('textbox', { name: /^username$/i });
  await user.click(screen.getByRole('tab', { name: /^notifications$/i }));
  return screen.findByRole('switch', { name: /system notifications/i });
};

// =============================================================================
// Tests
// =============================================================================

describe('AddEditUserModal - Notifications tab', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(fetchFolderTree).mockResolvedValue(mockFolderTree);
    vi.mocked(fetchGroupFolderPermissions).mockResolvedValue(new Map());
    vi.mocked(fetchUserGroups).mockResolvedValue({ rows: [mockUserGroup], totalCount: 1 });
    vi.mocked(fetchHomepages).mockResolvedValue([]);
  });

  test("all 7 notification switches reflect the draft's saved values", async () => {
    const user = userEvent.setup();
    renderAddEditUserModal({
      mode: 'edit',
      user: {
        ...mockUser,
        isSystemNotification: 1,
        isDisplayNotification: 0,
        isDataSetNotification: 1,
        isLayoutNotification: 0,
        isLibraryNotification: 1,
        isReportNotification: 0,
        isScheduleNotification: 1,
      },
      currentUser: mockSuperAdmin,
    });
    await openNotificationsTab(user);

    expect(screen.getByRole('switch', { name: /system notifications/i })).toHaveAttribute(
      'aria-checked',
      'true',
    );
    expect(screen.getByRole('switch', { name: /display notifications/i })).toHaveAttribute(
      'aria-checked',
      'false',
    );
    expect(screen.getByRole('switch', { name: /dataset notifications/i })).toHaveAttribute(
      'aria-checked',
      'true',
    );
    expect(screen.getByRole('switch', { name: /layout notifications/i })).toHaveAttribute(
      'aria-checked',
      'false',
    );
    expect(screen.getByRole('switch', { name: /library notifications/i })).toHaveAttribute(
      'aria-checked',
      'true',
    );
    expect(screen.getByRole('switch', { name: /report notifications/i })).toHaveAttribute(
      'aria-checked',
      'false',
    );
    expect(screen.getByRole('switch', { name: /schedule notifications/i })).toHaveAttribute(
      'aria-checked',
      'true',
    );
  });

  test('toggling System Notifications changes only that flag', async () => {
    const user = userEvent.setup();
    renderAddEditUserModal({
      mode: 'edit',
      user: { ...mockUser, isSystemNotification: 0, isDisplayNotification: 0 },
      currentUser: mockSuperAdmin,
    });
    await openNotificationsTab(user);

    await user.click(screen.getByRole('switch', { name: /system notifications/i }));

    expect(screen.getByRole('switch', { name: /system notifications/i })).toHaveAttribute(
      'aria-checked',
      'true',
    );
    expect(screen.getByRole('switch', { name: /display notifications/i })).toHaveAttribute(
      'aria-checked',
      'false',
    );
  });
});
