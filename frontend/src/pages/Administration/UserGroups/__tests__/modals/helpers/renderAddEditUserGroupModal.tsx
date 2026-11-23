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

import { render, screen } from '@testing-library/react';
import { vi } from 'vitest';

import AddEditUserGroupModal from '../../../components/AddEditUserGroupModal';
import { mockUser } from '../../fixtures/userGroup';

import { UserProvider } from '@/context/UserContext';
import type { User } from '@/types/user';
import type { UserGroup } from '@/types/userGroup';

interface Options {
  mode?: 'add' | 'edit';
  userGroup?: UserGroup | null;
  user?: User;
}

// Renders the real AddEditUserGroupModal; isSuperAdmin comes from UserProvider.
// Requires vi.mock('@/components/ui/modals/Modal') in the test file.
export const renderAddEditUserGroupModal = async ({
  mode = 'add',
  userGroup = null,
  user = mockUser,
}: Options = {}) => {
  const onClose = vi.fn();
  const onSuccess = vi.fn();
  const utils = render(
    <UserProvider initialUser={user}>
      <AddEditUserGroupModal
        isOpen
        mode={mode}
        userGroup={userGroup}
        onClose={onClose}
        onSuccess={onSuccess}
      />
    </UserProvider>,
  );
  const dialog = await screen.findByRole('dialog');
  return { ...utils, dialog, onClose, onSuccess };
};
