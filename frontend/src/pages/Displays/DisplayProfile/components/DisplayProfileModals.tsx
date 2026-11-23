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

import AddDisplayProfileModal from './AddDisplayProfileModal';
import CopyDisplayProfileModal from './CopyDisplayProfileModal';
import DeleteDisplayProfileModal from './DeleteDisplayProfileModal';
import EditDisplayProfileModal from './EditDisplayProfileModal';

import type { DisplayProfile } from '@/types/displayProfile';

interface DisplayProfileModalsProps {
  actions: {
    activeModal: string | null;
    closeModal: () => void;
    handleRefresh: () => void;
    setDisplayProfileList: React.Dispatch<React.SetStateAction<DisplayProfile[]>>;
    deleteError: string | null;
    isDeleting: boolean;
    isCopying: boolean;
  };
  selection: {
    selectedDisplayProfile: DisplayProfile | null;
    selectedDisplayProfileId: number | null;
    itemsToDelete: DisplayProfile[];
    existingNames: string[];
    shareEntityIds: number | number[] | null;
    setShareEntityIds: React.Dispatch<React.SetStateAction<number | number[] | null>>;
  };
  handlers: {
    confirmDelete: (items: DisplayProfile[]) => void;
    confirmCopy: (displayProfileId: number, newName: string) => void;
  };
}

export function DisplayProfileModals({ actions, selection, handlers }: DisplayProfileModalsProps) {
  const isModalOpen = (name: string) => actions.activeModal === name;

  return (
    <>
      {isModalOpen('add') && (
        <AddDisplayProfileModal
          onClose={actions.closeModal}
          onSave={(created) => {
            actions.setDisplayProfileList((prev) => [created, ...prev]);
            actions.handleRefresh();
          }}
        />
      )}

      {isModalOpen('edit') && (
        <EditDisplayProfileModal
          data={selection.selectedDisplayProfile}
          onClose={actions.closeModal}
          onSave={(updated) => {
            actions.setDisplayProfileList((prev) =>
              prev.map((m) =>
                m.displayProfileId === updated.displayProfileId ? { ...m, ...updated } : m,
              ),
            );
            actions.handleRefresh();
          }}
        />
      )}

      {isModalOpen('copy') && (
        <CopyDisplayProfileModal
          displayProfile={selection.selectedDisplayProfile}
          onClose={actions.closeModal}
          onConfirm={(newName) => {
            if (selection.selectedDisplayProfile) {
              handlers.confirmCopy(selection.selectedDisplayProfile.displayProfileId, newName);
            }
          }}
          existingNames={selection.existingNames}
          isLoading={actions.isCopying}
        />
      )}

      {isModalOpen('delete') && (
        <DeleteDisplayProfileModal
          onClose={actions.closeModal}
          onDelete={() => handlers.confirmDelete(selection.itemsToDelete)}
          itemCount={selection.itemsToDelete.length}
          displayProfileName={
            selection.itemsToDelete.length === 1 ? selection.itemsToDelete[0]?.name : undefined
          }
          error={actions.deleteError}
          isLoading={actions.isDeleting}
        />
      )}
    </>
  );
}
