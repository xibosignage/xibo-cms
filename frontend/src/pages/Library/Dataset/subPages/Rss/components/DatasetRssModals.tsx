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
          onClose={actions.closeModal}
          isLoading={!!actions.isCloning}
          onConfirm={() => {
            handlers.handleConfirmCopy();
          }}
        />
      )}

      {isModalOpen('delete') && selection.rssToDeleteId !== null && (
        <DeleteDatasetRssModal
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
