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

import AddAndEditResolutionModal from './AddAndEditResolutionModal';
import DeleteResolutionModal from './DeleteResolutionModal';

import type { Resolution } from '@/types/resolution';

interface ResolutionModalsProps {
  actions: {
    activeModal: string | null;
    closeModal: () => void;
    handleRefresh: () => void;
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
          onClose={() => {
            actions.closeModal();
          }}
          data={selection.selectedResolution}
          onSave={() => {
            actions.handleRefresh();
          }}
        />
      )}

      {isModalOpen('delete') && (
        <DeleteResolutionModal
          onClose={actions.closeModal}
          onDelete={() => handlers.confirmDelete(selection.itemsToDelete)}
          itemCount={selection.itemsToDelete.length}
          resolutionName={
            selection.itemsToDelete.length === 1
              ? selection.itemsToDelete[0]?.resolution
              : undefined
          }
          error={actions.deleteError}
          isLoading={actions.isDeleting}
        />
      )}
    </>
  );
}
