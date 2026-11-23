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

import {
  assignUserGroups,
  createUser,
  deleteUser,
  fetchUsers,
  setUserHomeFolder,
  updateUser,
} from '@/services/userApi';
import type { FetchUsersResponse } from '@/services/userApi';
import type { User } from '@/types/user';

export const mockFetchUsers = (data: FetchUsersResponse) => {
  vi.mocked(fetchUsers).mockResolvedValue(data);
};

export const mockDeleteUser = () => {
  vi.mocked(deleteUser).mockResolvedValue(undefined);
};

export const mockSetUserHomeFolder = () => {
  vi.mocked(setUserHomeFolder).mockResolvedValue(undefined);
};

export const mockAssignUserGroups = () => {
  vi.mocked(assignUserGroups).mockResolvedValue(undefined);
};

export const mockCreateUser = (user: User) => {
  vi.mocked(createUser).mockResolvedValue(user);
};

export const mockUpdateUser = (user: User) => {
  vi.mocked(updateUser).mockResolvedValue(user);
};
