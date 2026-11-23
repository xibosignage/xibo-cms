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

import type { ModalType } from '../UsersConfig';

import AddEditUserModal from './AddEditUserModal';
import DeleteUserModal from './DeleteUserModal';
import FeaturesModal from './FeaturesModal';
import SetHomeFolderModal from './SetHomeFolderModal';
import UserGroupsModal from './UserGroupsModal';

import type { User } from '@/types/user';
import { UserType } from '@/types/user';

interface UsersModalsProps {
  activeModal: ModalType;
  closeModal: () => void;
  handleRefresh: () => void;
  selectedUser: User | null;
  itemsToSetHomeFolder: User[];
  deleteError: string | null;
  isDeleting: boolean;
  confirmDelete: (
    userId: number,
    options: { deleteAllItems: boolean; reassignUserId: number | null },
  ) => void;
}

export function UsersModals({
  activeModal,
  closeModal,
  handleRefresh,
  selectedUser,
  itemsToSetHomeFolder,
  deleteError,
  isDeleting,
  confirmDelete,
}: UsersModalsProps) {
  return (
    <>
      {activeModal === 'add' && (
        <AddEditUserModal isOpen mode="add" onClose={closeModal} onSuccess={handleRefresh} />
      )}

      {activeModal === 'edit' && selectedUser && (
        <AddEditUserModal
          isOpen
          mode="edit"
          user={selectedUser}
          onClose={closeModal}
          onSuccess={handleRefresh}
        />
      )}

      {activeModal === 'delete' && selectedUser && (
        <DeleteUserModal
          isOpen
          onClose={closeModal}
          onDelete={(options) => confirmDelete(selectedUser.userId, options)}
          userName={selectedUser.userName}
          userId={selectedUser.userId}
          isSuperAdmin={selectedUser.userTypeId === UserType.SuperAdmin}
          error={deleteError}
          isLoading={isDeleting}
        />
      )}

      {activeModal === 'setHomeFolder' && (itemsToSetHomeFolder.length > 0 || selectedUser) && (
        <SetHomeFolderModal
          users={itemsToSetHomeFolder.length > 0 ? itemsToSetHomeFolder : [selectedUser!]}
          onClose={closeModal}
          onSuccess={handleRefresh}
        />
      )}

      {activeModal === 'userGroups' && selectedUser && (
        <UserGroupsModal user={selectedUser} onClose={closeModal} onSuccess={handleRefresh} />
      )}

      {activeModal === 'features' && selectedUser && (
        <FeaturesModal user={selectedUser} onClose={closeModal} onSuccess={handleRefresh} />
      )}
    </>
  );
}
