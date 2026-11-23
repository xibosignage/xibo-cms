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

import AddAndEditDatasetModal from './AddAndEditDatasetModal';
import ClearDatasetCacheModal from './ClearDatasetCacheModal';
import CopyDatasetModal from './CopyDatasetModal';
import DeleteDatasetModal from './DeleteDatasetModal';
import ImportDatasetCsvModal from './ImportDatasetCsvModal';

import FolderActionModals from '@/components/ui/FolderActionModals';
import MoveModal from '@/components/ui/modals/MoveModal';
import ShareModal from '@/components/ui/modals/ShareModal';
import { AUTO_SUBMIT_FORMS } from '@/constants/autoSubmitForms';
import type { useFolderActions } from '@/hooks/useFolderActions';
import type { Dataset } from '@/types/dataset';

interface DatasetModalsProps {
  actions: {
    activeModal: string | null;
    closeModal: () => void;
    handleRefresh: () => void;
    deleteError: string | null;
    isDeleting: boolean;
    isCloning: boolean;
    isClearingCache: boolean;
  };
  selection: {
    selectedDataset: Dataset | null;
    selectedDatasetId: number | null;
    defaultFolderId?: number;
    itemsToDelete: Dataset[];
    itemsToMove: Dataset[];
    existingNames: string[];
    shareEntityIds: number | number[] | null;
    setShareEntityIds: React.Dispatch<React.SetStateAction<number | number[] | null>>;
  };
  handlers: {
    confirmDelete: (options: { deleteData: boolean }) => void;
    handleConfirmClone: (
      dataSet: string,
      description: string,
      code: string,
      copyRows: boolean,
    ) => void;
    handleConfirmMove: (folderId: number) => void;
    confirmClearCache: () => void;
  };
  folderActions: ReturnType<typeof useFolderActions>;
  canUseRealTime?: boolean;
}

export function DatasetModals({
  actions,
  selection,
  handlers,
  folderActions,
  canUseRealTime,
}: DatasetModalsProps) {
  const { t } = useTranslation();
  const isModalOpen = (name: string) => actions.activeModal === name;

  return (
    <>
      {isModalOpen('edit') && (
        <AddAndEditDatasetModal
          type={selection.selectedDatasetId ? 'edit' : 'add'}
          canUseRealTime={canUseRealTime}
          defaultFolderId={selection.defaultFolderId}
          onClose={() => {
            actions.closeModal();
          }}
          data={selection.selectedDataset}
          onSave={() => {
            actions.handleRefresh();
          }}
        />
      )}

      {isModalOpen('share') && (
        <ShareModal
          title={t('Share Dataset')}
          onClose={() => {
            actions.closeModal();
            selection.setShareEntityIds(null);
            actions.handleRefresh();
          }}
          entityType="DataSet"
          entityId={selection.shareEntityIds ?? (selection.selectedDataset?.dataSetId || null)}
        />
      )}

      <FolderActionModals folderActions={folderActions} />

      {isModalOpen('delete') && (
        <DeleteDatasetModal
          onClose={actions.closeModal}
          onDelete={handlers.confirmDelete}
          itemCount={selection.itemsToDelete.length}
          datasetName={
            selection.itemsToDelete.length === 1 ? selection.itemsToDelete[0]?.dataSet : undefined
          }
          error={actions.deleteError}
          isLoading={actions.isDeleting}
        />
      )}

      {isModalOpen('copy') && (
        <CopyDatasetModal
          onClose={actions.closeModal}
          onConfirm={(dataSet, description, code, copyRows) =>
            handlers.handleConfirmClone(dataSet, description, code, copyRows)
          }
          dataset={selection.selectedDataset}
          isLoading={actions.isCloning}
          existingNames={selection.existingNames}
        />
      )}

      {isModalOpen('move') && (
        <MoveModal
          onClose={actions.closeModal}
          onConfirm={handlers.handleConfirmMove}
          items={selection.itemsToMove}
          entityLabel={t('Dataset')}
        />
      )}

      {isModalOpen('import') && selection.selectedDatasetId && (
        <ImportDatasetCsvModal
          onClose={actions.closeModal}
          datasetId={selection.selectedDatasetId}
          onSuccess={() => {
            actions.handleRefresh();
          }}
        />
      )}

      {isModalOpen('clearCache') && selection.selectedDataset && (
        <ClearDatasetCacheModal
          onClose={actions.closeModal}
          onConfirm={handlers.confirmClearCache}
          isActionPending={actions.isClearingCache}
          autoSubmitFormId={AUTO_SUBMIT_FORMS.dataSetClearCache}
        />
      )}
    </>
  );
}
