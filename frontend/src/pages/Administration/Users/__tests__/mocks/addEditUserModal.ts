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

import { vi } from 'vitest';

import { mockFolderTree, mockUserGroup } from '../fixtures/user';

import { fetchFolderTree } from '@/services/folderApi';
import { fetchGroupFolderPermissions, saveMultiPermissions } from '@/services/permissionsApi';
import { fetchHomepages } from '@/services/userApi';
import { fetchUserGroups } from '@/services/userGroupApi';

// Composite: every service AddEditUserModal fetches on open, with sensible
// empty/default values. Call this instead of mocking each service
// individually so a new fetch added to the modal only needs a default added
// here, not in every test file that renders it. Override individual mocks
// afterwards (e.g. vi.mocked(fetchHomepages).mockResolvedValue([...])) for
// test-specific data.
export const setupAddEditUserModalMocks = (): void => {
  vi.mocked(fetchFolderTree).mockResolvedValue(mockFolderTree);
  vi.mocked(fetchGroupFolderPermissions).mockResolvedValue(new Map());
  vi.mocked(saveMultiPermissions).mockResolvedValue(undefined);
  vi.mocked(fetchUserGroups).mockResolvedValue({ rows: [mockUserGroup], totalCount: 1 });
  vi.mocked(fetchHomepages).mockResolvedValue([]);
};
