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

import { AddAndEditDataModal } from './AddAndEditDatasetDataModal';
import CopyDatasetDataModal from './CopyDatasetDataModal';
import { DeleteDatasetDataModal } from './DeleteDatasetDataModal';

import type { DynamicRowData } from '@/services/datasetApi';
import type { DatasetColumn } from '@/types/datasetColumn';

interface DatasetDataModalsProps {
  datasetId: string;
  columnsSchema: DatasetColumn[];
  actions: {
    activeModal: string | null;
    closeModal: () => void;
    handleRefresh: () => void;
    deleteError: string | null;
    isDeleting: boolean;
    isCloning?: boolean;
  };
  selection: {
    selectedData: DynamicRowData | null;
    itemsToDelete: DynamicRowData[];
    rowToDeleteId: string | null;
  };
  handlers: {
    handleConfirmCopy: () => void;
    confirmDelete: (items: DynamicRowData[]) => void;
  };
}

export function DatasetDataModals({
  datasetId,
  columnsSchema,
  actions,
  selection,
  handlers,
}: DatasetDataModalsProps) {
  const isModalOpen = (name: string) => actions.activeModal === name;

  return (
    <>
      {isModalOpen('edit') && (
        <AddAndEditDataModal
          type={selection.selectedData ? 'edit' : 'add'}
          isOpen={true}
          onClose={actions.closeModal}
          datasetId={datasetId}
          columnsSchema={columnsSchema}
          rowData={selection.selectedData}
          onSave={() => {
            actions.closeModal();
            actions.handleRefresh();
          }}
        />
      )}

      {isModalOpen('copy') && (
        <CopyDatasetDataModal
          isOpen={true}
          onClose={actions.closeModal}
          isLoading={!!actions.isCloning}
          onConfirm={() => {
            if (handlers?.handleConfirmCopy) {
              handlers.handleConfirmCopy();
            }
          }}
        />
      )}

      {isModalOpen('delete') && selection.itemsToDelete.length > 0 && (
        <DeleteDatasetDataModal
          isOpen={true}
          onClose={actions.closeModal}
          onDelete={() => handlers.confirmDelete(selection.itemsToDelete)}
          itemCount={selection.itemsToDelete.length}
          rowId={selection.itemsToDelete.length === 1 ? selection.rowToDeleteId : undefined}
          isLoading={actions.isDeleting}
          error={actions.deleteError}
        />
      )}
    </>
  );
}
