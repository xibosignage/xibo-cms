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
          onClose={actions.closeModal}
          datasetId={datasetId}
          columnsSchema={columnsSchema}
          rowData={selection.selectedData}
          onSave={actions.handleRefresh}
        />
      )}

      {isModalOpen('copy') && (
        <CopyDatasetDataModal
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
