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

import { useTranslation } from 'react-i18next';

import type { ModalType } from '../SyncGroupsConfig';

import AddAndEditSyncGroupModal from './AddAndEditSyncGroupModal';
import DeleteSyncGroupModal from './DeleteSyncGroupModal';
import ManageMembersModal from './ManageMembersModal';

import ShareModal from '@/components/ui/modals/ShareModal';
import type { SyncGroup } from '@/types/syncGroup';

interface SyncGroupModalsProps {
  actions: {
    activeModal: ModalType;
    closeModal: () => void;
    handleRefresh: () => void;
    setSyncGroupList: React.Dispatch<React.SetStateAction<SyncGroup[]>>;
    openMembersForSyncGroup: (syncGroup: SyncGroup) => void;
    openEditForSyncGroup: (syncGroup: SyncGroup) => void;
    deleteError: string | null;
    isDeleting: boolean;
  };
  selection: {
    selectedSyncGroup: SyncGroup | null;
    itemsToDelete: SyncGroup[];
    shareEntityIds: number | number[] | null;
  };
  handlers: {
    confirmDelete: (items: SyncGroup[]) => void;
  };
}

export function SyncGroupModals({ actions, selection, handlers }: SyncGroupModalsProps) {
  const { t } = useTranslation();
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
          onNeedsLeadDisplay={() => {
            if (selection.selectedSyncGroup) {
              actions.openEditForSyncGroup(selection.selectedSyncGroup);
            }
          }}
          onAfterSave={
            selection.selectedSyncGroup && !selection.selectedSyncGroup.leadDisplayId
              ? () => actions.openEditForSyncGroup(selection.selectedSyncGroup!)
              : undefined
          }
        />
      )}

      {isModalOpen('share') && (
        <ShareModal
          isOpen
          title={t('Share Sync Group')}
          entityType="syncGroup"
          entityId={selection.shareEntityIds}
          onClose={() => {
            actions.closeModal();
            actions.handleRefresh();
          }}
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
