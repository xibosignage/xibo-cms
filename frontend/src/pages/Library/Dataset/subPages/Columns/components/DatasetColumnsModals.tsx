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
