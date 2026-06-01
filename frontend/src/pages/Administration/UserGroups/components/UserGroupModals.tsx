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

import type { Dispatch, SetStateAction } from 'react';

import type { ModalType } from '../UserGroupsConfig';

import AddEditUserGroupModal from './AddEditUserGroupModal';
import CopyUserGroupModal from './CopyUserGroupModal';
import DeleteUserGroupModal from './DeleteUserGroupModal';
import ManageMembersModal from './ManageMembersModal';

import type { UserGroup } from '@/types/userGroup';

interface UserGroupModalsProps {
  activeModal: ModalType;
  closeModal: () => void;
  handleRefresh: () => void;
  selectedUserGroup: UserGroup | null;
  itemsToDelete: UserGroup[];
  deleteError: string | null;
  setDeleteError: Dispatch<SetStateAction<string | null>>;
  isDeleting: boolean;
  confirmDelete: (items: UserGroup[]) => void;
}

export function UserGroupModals({
  activeModal,
  closeModal,
  handleRefresh,
  selectedUserGroup,
  itemsToDelete,
  deleteError,
  setDeleteError,
  isDeleting,
  confirmDelete,
}: UserGroupModalsProps) {
  const isModalOpen = (name: ModalType) => activeModal === name;

  const handleDeleteClose = () => {
    setDeleteError(null);
    closeModal();
  };

  return (
    <>
      {isModalOpen('add') && (
        <AddEditUserGroupModal isOpen mode="add" onClose={closeModal} onSuccess={handleRefresh} />
      )}

      {isModalOpen('edit') && (
        <AddEditUserGroupModal
          isOpen
          mode="edit"
          userGroup={selectedUserGroup}
          onClose={closeModal}
          onSuccess={handleRefresh}
        />
      )}

      {isModalOpen('delete') && itemsToDelete.length > 0 && (
        <DeleteUserGroupModal
          isOpen
          onClose={handleDeleteClose}
          onDelete={() => confirmDelete(itemsToDelete)}
          itemCount={itemsToDelete.length}
          groupName={itemsToDelete.length === 1 ? itemsToDelete[0]?.group : undefined}
          error={deleteError}
          isLoading={isDeleting}
        />
      )}

      {isModalOpen('copy') && selectedUserGroup && (
        <CopyUserGroupModal
          userGroup={selectedUserGroup}
          onClose={closeModal}
          onSuccess={handleRefresh}
        />
      )}

      {isModalOpen('members') && selectedUserGroup && (
        <ManageMembersModal
          userGroup={selectedUserGroup}
          onClose={closeModal}
          onSuccess={handleRefresh}
        />
      )}
    </>
  );
}
