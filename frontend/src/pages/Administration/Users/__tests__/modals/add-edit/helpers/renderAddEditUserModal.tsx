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

import { QueryClientProvider } from '@tanstack/react-query';
import { render } from '@testing-library/react';

import AddEditUserModal from '../../../../components/AddEditUserModal';
import { mockSuperAdmin } from '../../../fixtures/user';

import { UserProvider } from '@/context/UserContext';
import { testQueryClient } from '@/setupTests';
import type { User } from '@/types/user';

interface RenderAddEditUserModalOptions {
  mode: 'add' | 'edit';
  user?: User | null;
  currentUser?: User;
  onClose?: () => void;
  onSuccess?: () => void;
}

// Mounts AddEditUserModal in isolation for form-level and tab-level tests —
// no DataTable, no page chrome. Pass currentUser to drive isSuperAdmin /
// canViewFolders gates via the real UserProvider + usePermissions.
export const renderAddEditUserModal = ({
  mode,
  user = null,
  currentUser = mockSuperAdmin,
  onClose = () => {},
  onSuccess = () => {},
}: RenderAddEditUserModalOptions) => {
  return render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={currentUser}>
        <AddEditUserModal isOpen mode={mode} user={user} onClose={onClose} onSuccess={onSuccess} />
      </UserProvider>
    </QueryClientProvider>,
  );
};
