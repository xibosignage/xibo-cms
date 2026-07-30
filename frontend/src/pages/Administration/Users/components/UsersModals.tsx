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
