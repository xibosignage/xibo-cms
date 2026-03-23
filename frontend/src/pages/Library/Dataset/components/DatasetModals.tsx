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

import { useTranslation } from 'react-i18next';

import AddAndEditDatasetModal from './AddAndEditDatasetModal';
import CopyDatasetModal from './CopyDatasetModal';
import DeleteDatasetModal from './DeleteDatasetModal';
import ImportDatasetCsvModal from './ImportDatasetCsvModal';

import FolderActionModals from '@/components/ui/FolderActionModals';
import MoveModal from '@/components/ui/modals/MoveModal';
import ShareModal from '@/components/ui/modals/ShareModal';
import type { useFolderActions } from '@/hooks/useFolderActions';
import type { Dataset } from '@/types/dataset';

interface DatasetModalsProps {
  actions: {
    activeModal: string | null;
    closeModal: () => void;
    handleRefresh: () => void;
    setDatasetList: React.Dispatch<React.SetStateAction<Dataset[]>>;
    deleteError: string | null;
    isDeleting: boolean;
    isCloning: boolean;
  };
  selection: {
    selectedDataset: Dataset | null;
    selectedDatasetId: number | null;
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
  };
  folderActions: ReturnType<typeof useFolderActions>;
}

export function DatasetModals({ actions, selection, handlers, folderActions }: DatasetModalsProps) {
  const { t } = useTranslation();
  const isModalOpen = (name: string) => actions.activeModal === name;

  return (
    <>
      {isModalOpen('edit') && (
        <AddAndEditDatasetModal
          type={selection.selectedDatasetId ? 'edit' : 'add'}
          openModal={isModalOpen('edit')}
          onClose={() => {
            actions.closeModal();
          }}
          data={selection.selectedDataset}
          onSave={(savedDataset) => {
            if (selection.selectedDatasetId) {
              actions.setDatasetList((prev) =>
                prev.map((m) => (m.dataSetId === savedDataset.dataSetId ? savedDataset : m)),
              );
            } else {
              actions.setDatasetList((prev) => [savedDataset, ...prev]);
            }
            actions.handleRefresh();
          }}
        />
      )}

      <ShareModal
        title={t('Share Dataset')}
        onClose={() => {
          actions.closeModal();
          selection.setShareEntityIds(null);
          actions.handleRefresh();
        }}
        openModal={isModalOpen('share')}
        entityType="DataSet"
        entityId={selection.shareEntityIds ?? (selection.selectedDataset?.dataSetId || null)}
      />

      <FolderActionModals folderActions={folderActions} />

      <DeleteDatasetModal
        isOpen={isModalOpen('delete')}
        onClose={actions.closeModal}
        onDelete={handlers.confirmDelete}
        itemCount={selection.itemsToDelete.length}
        datasetName={
          selection.itemsToDelete.length === 1 ? selection.itemsToDelete[0]?.dataSet : undefined
        }
        error={actions.deleteError}
        isLoading={actions.isDeleting}
      />

      <CopyDatasetModal
        isOpen={isModalOpen('copy')}
        onClose={actions.closeModal}
        onConfirm={(dataSet, description, code, copyRows) =>
          handlers.handleConfirmClone(dataSet, description, code, copyRows)
        }
        dataset={selection.selectedDataset}
        isLoading={actions.isCloning}
        existingNames={selection.existingNames}
      />

      <MoveModal
        isOpen={isModalOpen('move')}
        onClose={actions.closeModal}
        onConfirm={handlers.handleConfirmMove}
        items={selection.itemsToMove}
        entityLabel={t('Dataset')}
      />

      {isModalOpen('import') && selection.selectedDatasetId && (
        <ImportDatasetCsvModal
          isOpen={true}
          onClose={actions.closeModal}
          datasetId={selection.selectedDatasetId}
          onSuccess={() => {
            actions.handleRefresh();
          }}
        />
      )}
    </>
  );
}
