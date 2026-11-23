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
