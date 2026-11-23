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

import { AddAndEditDatasetColumnModal } from './AddAndEditDatasetColumnModal';
import CopyDatasetColumnModal from './CopyDatasetColumnModal';
import { DeleteDatasetColumnModal } from './DeleteDatasetColumnModal';

import type { DatasetColumn } from '@/types/datasetColumn';

interface DatasetColumnModalsProps {
  datasetId: string;
  actions: {
    activeModal: string | null;
    closeModal: () => void;
    handleRefresh: () => void;
    deleteError: string | null;
    isDeleting: boolean;
    isCloning?: boolean;
  };
  selection: {
    selectedColumn: DatasetColumn | null;
    columnToDeleteId: number | null;
    itemsToDelete: DatasetColumn[];
    existingNames?: string[];
  };
  handlers: {
    handleConfirmCopy: (newHeading: string) => void;
    confirmDelete: (items: DatasetColumn[]) => void;
  };
}

export function DatasetColumnModals({
  datasetId,
  actions,
  selection,
  handlers,
}: DatasetColumnModalsProps) {
  const isModalOpen = (name: string) => actions.activeModal === name;

  return (
    <>
      {isModalOpen('edit') && (
        <AddAndEditDatasetColumnModal
          type={selection.selectedColumn ? 'edit' : 'add'}
          onClose={actions.closeModal}
          datasetId={datasetId}
          column={selection.selectedColumn}
          onSave={() => {
            actions.closeModal();
            actions.handleRefresh();
          }}
        />
      )}

      {isModalOpen('copy') && (
        <CopyDatasetColumnModal
          onClose={actions.closeModal}
          column={selection.selectedColumn}
          isLoading={!!actions.isCloning}
          existingNames={selection.existingNames || []}
          onConfirm={(newHeading) => {
            if (handlers?.handleConfirmCopy) {
              handlers.handleConfirmCopy(newHeading);
            }
          }}
        />
      )}

      {isModalOpen('delete') && selection.columnToDeleteId !== null && (
        <DeleteDatasetColumnModal
          onClose={actions.closeModal}
          onDelete={() => handlers.confirmDelete(selection.itemsToDelete)}
          itemCount={selection.itemsToDelete.length}
          columnName={
            selection.itemsToDelete.length === 1 ? selection.itemsToDelete[0]?.heading : undefined
          }
          error={actions.deleteError}
          isLoading={actions.isDeleting}
        />
      )}
    </>
  );
}
