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

import type { ModalType } from '../SyncGroupsConfig';

import AddAndEditSyncGroupModal from './AddAndEditSyncGroupModal';
import DeleteSyncGroupModal from './DeleteSyncGroupModal';
import ManageMembersModal from './ManageMembersModal';

import type { SyncGroup } from '@/types/syncGroup';

interface SyncGroupModalsProps {
  actions: {
    activeModal: ModalType;
    closeModal: () => void;
    handleRefresh: () => void;
    setSyncGroupList: React.Dispatch<React.SetStateAction<SyncGroup[]>>;
    openMembersForSyncGroup: (syncGroup: SyncGroup) => void;
    deleteError: string | null;
    isDeleting: boolean;
  };
  selection: {
    selectedSyncGroup: SyncGroup | null;
    itemsToDelete: SyncGroup[];
  };
  handlers: {
    confirmDelete: (items: SyncGroup[]) => void;
  };
}

export function SyncGroupModals({ actions, selection, handlers }: SyncGroupModalsProps) {
  const isModalOpen = (name: string) => actions.activeModal === name;

  return (
    <>
      {isModalOpen('add') && (
        <AddAndEditSyncGroupModal
          mode="add"
          syncGroup={null}
          onClose={actions.closeModal}
          onSave={(created) => {
            actions.setSyncGroupList((prev) => [created, ...prev]);
            actions.handleRefresh();
          }}
          onAfterSave={(created) => {
            actions.openMembersForSyncGroup(created);
          }}
        />
      )}

      {isModalOpen('edit') && (
        <AddAndEditSyncGroupModal
          mode="edit"
          syncGroup={selection.selectedSyncGroup}
          onClose={actions.closeModal}
          onSave={(updated) => {
            actions.setSyncGroupList((prev) =>
              prev.map((m) => (m.syncGroupId === updated.syncGroupId ? { ...m, ...updated } : m)),
            );
            actions.handleRefresh();
          }}
        />
      )}

      {isModalOpen('members') && (
        <ManageMembersModal
          syncGroup={selection.selectedSyncGroup}
          onClose={actions.closeModal}
          onSuccess={actions.handleRefresh}
        />
      )}

      {isModalOpen('delete') && (
        <DeleteSyncGroupModal
          onClose={actions.closeModal}
          onDelete={() => handlers.confirmDelete(selection.itemsToDelete)}
          itemCount={selection.itemsToDelete.length}
          syncGroupName={
            selection.itemsToDelete.length === 1 ? selection.itemsToDelete[0]?.name : undefined
          }
          error={actions.deleteError}
          isLoading={actions.isDeleting}
        />
      )}
    </>
  );
}
