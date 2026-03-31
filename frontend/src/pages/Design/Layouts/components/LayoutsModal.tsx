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

import AssignCampaignModal from './AssignCampaignModal';
import CopyLayoutModal from './CopyLayoutModal';
import DeleteLayoutModal from './DeleteLayoutModal';
import DiscardLayoutModal from './DiscardLayoutModal';
import EditLayout from './EditLayout';
import { EnableStatsLayoutModal } from './EnableStatsLayoutModal';
import ExportLayoutModal from './ExportLayoutModal';
import { LayoutInfoPanel } from './LayoutInfoPannel';
import { RetireLayoutModal } from './RetireLayoutModal';
import SaveAsTemplateModal from './SaveAsTemplateModal';

import FolderActionModals from '@/components/ui/FolderActionModals';
import type { PublishValue } from '@/components/ui/forms/PublishDateSelect';
import MoveModal from '@/components/ui/modals/MoveModal';
import PublishModal from '@/components/ui/modals/PublishModal';
import ShareModal from '@/components/ui/modals/ShareModal';
import type { useFolderActions } from '@/hooks/useFolderActions';
import type { Layout } from '@/types/layout';
import type { User } from '@/types/user';

interface LayoutModalsProps {
  actions: {
    activeModal: string | null;
    closeModal: () => void;
    handleRefresh: () => void;
    setLayoutList: React.Dispatch<React.SetStateAction<Layout[]>>;
    deleteError: string | null;
    isDeleting: boolean;
    isCloning: boolean;
    isPublishing: boolean;
    isDiscarding: boolean;
    isAssigning: boolean;
    isExporting: boolean;
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
    handleConfirmClone: (newName: string, description: string, copyMedia: boolean) => void;
    handleConfirmMove: (newFolderId: number) => void;
    confirmPublish: (itemId: number, value: PublishValue) => void;
    confirmDiscard: (layoutId: number) => void;
    handleConfirmAssign: (campaignId: number, layoutId: number) => void;
    handleExportLayout: (
      layoutId: number,
      options: {
        includeData: boolean;
        includeFallback: boolean;
        fileName: string;
      },
    ) => void;
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
      {isModalOpen('edit') && selection.selectedLayout && (
        <EditLayout
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

      {isModalOpen('delete') && (
        <DeleteLayoutModal
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
      )}

      {isModalOpen('move') && (
        <MoveModal
          onClose={actions.closeModal}
          onConfirm={handlers.handleConfirmMove}
          items={selection.itemsToMove}
          entityLabel={t('Layouts')}
        />
      )}

      {isModalOpen('share') && (
        <ShareModal
          title={t('Share Layout')}
          onClose={() => {
            actions.closeModal();
            selection.setShareEntityIds(null);
            actions.handleRefresh();
          }}
          entityType="campaign"
          entityId={selection.shareEntityIds ?? (selection.selectedLayout?.campaignId || null)}
        />
      )}

      {isModalOpen('copy') && (
        <CopyLayoutModal
          onClose={actions.closeModal}
          onConfirm={(name, description, copyMedia) =>
            handlers.handleConfirmClone(name, description, copyMedia)
          }
          layout={selection.selectedLayout}
          isLoading={actions.isCloning}
          existingNames={selection.existingNames}
        />
      )}
      {isModalOpen('publish') && (
        <PublishModal
          onClose={actions.closeModal}
          fileName={selection.selectedLayout?.layout}
          titleText={t('Publish Layout?')}
          isLoading={actions.isPublishing}
          onPublish={handlers.confirmPublish}
          layoutId={selection.selectedLayout?.layoutId}
        />
      )}
      {isModalOpen('discard') && (
        <DiscardLayoutModal
          onClose={actions.closeModal}
          onConfirm={() =>
            selection.selectedLayout && handlers.confirmDiscard(selection.selectedLayout.layoutId)
          }
          layoutName={selection.selectedLayout?.name || selection.selectedLayout?.layout}
          isLoading={actions.isDiscarding}
        />
      )}
      {isModalOpen('campaign') && (
        <AssignCampaignModal
          onClose={actions.closeModal}
          onConfirm={(campaignId) =>
            selection.selectedLayout &&
            handlers.handleConfirmAssign(campaignId, selection.selectedLayout.layoutId)
          }
          isLoading={actions.isAssigning}
        />
      )}
      {isModalOpen('export') && (
        <ExportLayoutModal
          onClose={actions.closeModal}
          onConfirm={(options) =>
            selection.selectedLayout &&
            handlers.handleExportLayout(selection.selectedLayout.layoutId, options)
          }
          layoutName={selection.selectedLayout?.name || selection.selectedLayout?.layout}
          isLoading={actions.isExporting}
        />
      )}
      {isModalOpen('template') && selection.selectedLayout && (
        <SaveAsTemplateModal onClose={actions.closeModal} layout={selection.selectedLayout} />
      )}

      {isModalOpen('retire') && selection.selectedLayout && (
        <RetireLayoutModal layout={selection.selectedLayout} onClose={actions.closeModal} />
      )}

      {isModalOpen('enableStats') && selection.selectedLayout && (
        <EnableStatsLayoutModal layout={selection.selectedLayout} onClose={actions.closeModal} />
      )}

      {infoPanel.isOpen && (
        <LayoutInfoPanel
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
      )}
    </>
  );
}
