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

import CopyLayoutModal from './CopyLayoutModal';
import DeleteLayoutModal from './DeleteLayoutModal';
import EditLayout from './EditLayout';
import { LayoutInfoPanel } from './LayoutInfoPannel';

import FolderActionModals from '@/components/ui/FolderActionModals';
import MoveModal from '@/components/ui/modals/MoveModal';
import ShareModal from '@/components/ui/modals/ShareModal';
import type { useFolderActions } from '@/hooks/useFolderActions';
import type { Layout } from '@/types/layout';
import type { User } from '@/types/user';
// import type { Tag } from '@/types/tag';

interface LayoutModalsProps {
  actions: {
    activeModal: string | null;
    closeModal: () => void;
    handleRefresh: () => void;
    setLayoutList: React.Dispatch<React.SetStateAction<Layout[]>>;
    deleteError: string | null;
    isDeleting: boolean;
    isCloning: boolean;
  };
  selection: {
    selectedLayout: Layout | null;
    itemsToDelete: Layout[];
    itemsToMove: Layout[];
    shareEntityIds: number | number[] | null;
    setShareEntityIds: React.Dispatch<React.SetStateAction<number | number[] | null>>;
    existingNames: string[];
  };
  handlers: {
    confirmDelete: (items: Layout[]) => void;
    // handleConfirmClone: (newName: string, description: string, copyMedia: boolean) => void;
    handleConfirmClone: (newName: string, description: string, copyMedia: boolean) => void;
    handleConfirmMove: (newFolderId: number) => void;
  };
  infoPanel: {
    isOpen: boolean;
    setOpen: (open: boolean) => void;
    setSelectedLayoutId: (id: number | null) => void;
    owner: User | null;
    loading: boolean;
    folderName: string;
  };
  folderActions: ReturnType<typeof useFolderActions>;
}

export function LayoutModals({
  actions,
  selection,
  handlers,
  infoPanel,
  folderActions,
}: LayoutModalsProps) {
  const { t } = useTranslation();

  const isModalOpen = (name: string) => actions.activeModal === name;

  return (
    <>
      {selection.selectedLayout && (
        <EditLayout
          openModal={isModalOpen('edit')}
          onClose={actions.closeModal}
          data={selection.selectedLayout}
          onSave={(updatedLayout) => {
            actions.setLayoutList((prev) =>
              prev.map((l) => (l.layoutId === updatedLayout.layoutId ? updatedLayout : l)),
            );
            actions.handleRefresh();
          }}
        />
      )}

      <FolderActionModals folderActions={folderActions} />

      <DeleteLayoutModal
        isOpen={isModalOpen('delete')}
        onClose={actions.closeModal}
        onDelete={() => handlers.confirmDelete(selection.itemsToDelete)}
        itemCount={selection.itemsToDelete.length}
        layoutName={
          selection.itemsToDelete.length === 1
            ? selection.itemsToDelete[0]?.name || selection.itemsToDelete[0]?.layout
            : undefined
        }
        error={actions.deleteError}
        isLoading={actions.isDeleting}
      />

      <MoveModal
        isOpen={isModalOpen('move')}
        onClose={actions.closeModal}
        onConfirm={handlers.handleConfirmMove}
        items={selection.itemsToMove}
        entityLabel={t('Layouts')}
      />

      <ShareModal
        title={t('Share Layout')}
        onClose={() => {
          actions.closeModal();
          selection.setShareEntityIds(null);
          actions.handleRefresh();
        }}
        openModal={isModalOpen('share')}
        entityType="campaign"
        entityId={selection.shareEntityIds ?? (selection.selectedLayout?.campaignId || null)}
      />

      <CopyLayoutModal
        isOpen={isModalOpen('copy')}
        onClose={actions.closeModal}
        onConfirm={(name, description, copyMedia) =>
          handlers.handleConfirmClone(name, description, copyMedia)
        }
        layout={selection.selectedLayout}
        isLoading={actions.isCloning}
        existingNames={selection.existingNames}
      />
      <LayoutInfoPanel
        open={infoPanel.isOpen}
        onClose={() => {
          infoPanel.setSelectedLayoutId(null);
          infoPanel.setOpen(false);
        }}
        layoutData={selection.selectedLayout}
        owner={infoPanel.owner}
        folderName={infoPanel.folderName}
        loading={infoPanel.loading}
        applyVersionTwo
      />
    </>
  );
}
