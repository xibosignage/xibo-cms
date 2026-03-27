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

import { AddAndEditDatasetRssModal } from './AddAndEditDatasetRssModal';
import CopyDatasetRssModal from './CopyDatasetRssModal';
import { DeleteDatasetRssModal } from './DeleteDatasetRssModal';

import type { DatasetRss } from '@/types/datasetRss';

interface DatasetRssModalsProps {
  datasetId: string;
  actions: {
    activeModal: string | null;
    closeModal: () => void;
    handleRefresh: () => void;
    setRssList: React.Dispatch<React.SetStateAction<DatasetRss[]>>;
    deleteError: string | null;
    isDeleting: boolean;
    isCloning?: boolean;
  };
  selection: {
    selectedRss: DatasetRss | null;
    rssToDeleteId: number | null;
    itemsToDelete: DatasetRss[];
    existingNames?: string[];
  };
  handlers: {
    handleConfirmCopy: () => void;
    confirmDelete: (items: DatasetRss[]) => void;
  };
}

export function DatasetRssModals({
  datasetId,
  actions,
  selection,
  handlers,
}: DatasetRssModalsProps) {
  const isModalOpen = (name: string) => actions.activeModal === name;

  return (
    <>
      {isModalOpen('edit') && (
        <AddAndEditDatasetRssModal
          type={selection.selectedRss ? 'edit' : 'add'}
          isOpen={true}
          onClose={actions.closeModal}
          datasetId={datasetId}
          rss={selection.selectedRss}
          onSave={() => {
            actions.closeModal();
            actions.handleRefresh();
          }}
        />
      )}

      {isModalOpen('copy') && (
        <CopyDatasetRssModal
          isOpen={true}
          onClose={actions.closeModal}
          isLoading={!!actions.isCloning}
          onConfirm={() => {
            handlers.handleConfirmCopy();
          }}
        />
      )}

      {isModalOpen('delete') && selection.rssToDeleteId !== null && (
        <DeleteDatasetRssModal
          isOpen={isModalOpen('delete')}
          onClose={actions.closeModal}
          onDelete={() => handlers.confirmDelete(selection.itemsToDelete)}
          itemCount={selection.itemsToDelete.length}
          error={actions.deleteError}
          isLoading={actions.isDeleting}
        />
      )}
    </>
  );
}
