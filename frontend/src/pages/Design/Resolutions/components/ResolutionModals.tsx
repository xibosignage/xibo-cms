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

import AddAndEditResolutionModal from './AddAndEditResolutionModal';
import DeleteResolutionModal from './DeleteResolutionModal';

import type { Resolution } from '@/types/resolution';

interface ResolutionModalsProps {
  actions: {
    activeModal: string | null;
    closeModal: () => void;
    handleRefresh: () => void;
    setResolutionList: React.Dispatch<React.SetStateAction<Resolution[]>>;
    deleteError: string | null;
    isDeleting: boolean;
  };
  selection: {
    selectedResolution: Resolution | null;
    selectedResolutionId: number | null;
    itemsToDelete: Resolution[];
    existingNames: string[];
    shareEntityIds: number | number[] | null;
    setShareEntityIds: React.Dispatch<React.SetStateAction<number | number[] | null>>;
  };
  handlers: {
    confirmDelete: (items: Resolution[]) => void;
  };
}

export function ResolutionModals({ actions, selection, handlers }: ResolutionModalsProps) {
  const isModalOpen = (name: string) => actions.activeModal === name;

  return (
    <>
      {isModalOpen('edit') && (
        <AddAndEditResolutionModal
          type={selection.selectedResolutionId ? 'edit' : 'add'}
          openModal={isModalOpen('edit')}
          onClose={() => {
            actions.closeModal();
          }}
          data={selection.selectedResolution}
          onSave={(savedResolution) => {
            if (selection.selectedResolutionId) {
              actions.setResolutionList((prev) =>
                prev.map((m) =>
                  m.resolutionId === savedResolution.resolutionId ? savedResolution : m,
                ),
              );
            } else {
              actions.setResolutionList((prev) => [savedResolution, ...prev]);
            }
            actions.handleRefresh();
          }}
        />
      )}

      <DeleteResolutionModal
        isOpen={isModalOpen('delete')}
        onClose={actions.closeModal}
        onDelete={() => handlers.confirmDelete(selection.itemsToDelete)}
        itemCount={selection.itemsToDelete.length}
        resolutionName={
          selection.itemsToDelete.length === 1 ? selection.itemsToDelete[0]?.resolution : undefined
        }
        error={actions.deleteError}
        isLoading={actions.isDeleting}
      />
    </>
  );
}
