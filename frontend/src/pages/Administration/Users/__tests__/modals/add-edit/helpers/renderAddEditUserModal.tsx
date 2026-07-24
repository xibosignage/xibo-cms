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
