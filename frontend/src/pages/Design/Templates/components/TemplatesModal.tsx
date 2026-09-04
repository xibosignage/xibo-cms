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

import AddAndEditTemplateModal from './AddAndEditTemplate';
import CopyTemplateModal from './CopyTemplateModal';
import DeleteTemplateModal from './DeleteTemplateModal';
import DiscardTemplateModal from './DiscardTemplateModal';
import ExportTemplateModal from './ExportTemplateModal';

import FolderActionModals from '@/components/ui/FolderActionModals';
import type { PublishValue } from '@/components/ui/forms/PublishDateSelect';
import EditTagsMultipleModal from '@/components/ui/modals/EditTagsMultipleModal';
import MoveModal from '@/components/ui/modals/MoveModal';
import PublishModal from '@/components/ui/modals/PublishModal';
import ShareModal from '@/components/ui/modals/ShareModal';
import type { useFolderActions } from '@/hooks/useFolderActions';
import type { Template } from '@/types/templates';
import { mergeEntityTags } from '@/utils/tags';

interface TemplatesModalsProps {
  actions: {
    activeModal: string | null;
    closeModal: () => void;
    handleRefresh: () => Promise<unknown>;
    deleteError: string | null;
    isDeleting: boolean;
    isCloning: boolean;
    isPublishing: boolean;
    isDiscarding: boolean;
    isExporting: boolean;
  };
  selection: {
    selectedTemplate: Template | null;
    selectedTemplateId: number | null;
    defaultFolderId?: number;
    itemsToDelete: Template[];
    existingNames: string[];
    itemsToMove: Template[];
    bulkItems: Template[];
    shareEntityIds: number | number[] | null;
    setShareEntityIds: React.Dispatch<React.SetStateAction<number | number[] | null>>;
  };
  handlers: {
    confirmDelete: (items: Template[]) => void;
    handleConfirmMove: (newFolderId: number) => void;
    handleConfirmClone: (newName: string, description: string, copyTemplate: boolean) => void;
    confirmPublish: (layoutId: number, value: PublishValue) => void;
    confirmDiscard: (layoutId: number) => void;
    handleExportTemplate: (
      layoutId: number,
      options: {
        includeData: boolean;
        includeFallback: boolean;
        fileName: string;
      },
    ) => void;
  };
  folderActions: ReturnType<typeof useFolderActions>;
}

export function TemplateModals({
  actions,
  selection,
  folderActions,
  handlers,
}: TemplatesModalsProps) {
  const { t } = useTranslation();

  const isModalOpen = (name: string) => actions.activeModal === name;

  return (
    <>
      {isModalOpen('edit') && (
        <AddAndEditTemplateModal
          type={selection.selectedTemplateId ? 'edit' : 'add'}
          defaultFolderId={selection.defaultFolderId}
          onClose={() => {
            actions.closeModal();
          }}
          data={selection.selectedTemplate}
          onSave={() => {
            actions.handleRefresh();
          }}
        />
      )}
      <FolderActionModals folderActions={folderActions} />
      {isModalOpen('editTagsMultiple') && (
        <EditTagsMultipleModal
          targetType="layout"
          ids={selection.bulkItems.map((item) => item.layoutId)}
          existingTags={mergeEntityTags(selection.bulkItems)}
          onClose={actions.closeModal}
          onSuccess={async () => {
            await actions.handleRefresh();
            actions.closeModal();
          }}
        />
      )}
      {isModalOpen('share') && (
        <ShareModal
          title={t('Share Template')}
          onClose={() => {
            actions.closeModal();
            selection.setShareEntityIds(null);
            actions.handleRefresh();
          }}
          entityType="campaign"
          entityId={selection.shareEntityIds ?? (selection.selectedTemplate?.campaignId || null)}
        />
      )}
      {isModalOpen('delete') && (
        <DeleteTemplateModal
          onClose={actions.closeModal}
          onDelete={() => handlers.confirmDelete(selection.itemsToDelete)}
          itemCount={selection.itemsToDelete.length}
          templateName={
            selection.itemsToDelete.length === 1 ? selection.itemsToDelete[0]?.layout : undefined
          }
          error={actions.deleteError}
          isLoading={actions.isDeleting}
        />
      )}
      {isModalOpen('copy') && (
        <CopyTemplateModal
          onClose={actions.closeModal}
          onConfirm={(name, description, copyTemplate) =>
            handlers.handleConfirmClone(name, description, copyTemplate)
          }
          template={selection.selectedTemplate}
          isLoading={actions.isCloning}
          existingNames={selection.existingNames}
        />
      )}
      {isModalOpen('move') && (
        <MoveModal
          onClose={actions.closeModal}
          onConfirm={handlers?.handleConfirmMove}
          items={selection.itemsToMove}
          entityLabel={t('Templates')}
        />
      )}
      {isModalOpen('publish') && (
        <PublishModal
          onClose={actions.closeModal}
          fileName={selection.selectedTemplate?.layout}
          titleText={t('Publish Template?')}
          isLoading={actions.isPublishing}
          onPublish={handlers.confirmPublish}
          layoutId={selection.selectedTemplate?.layoutId}
          publishedDate={selection.selectedTemplate?.publishedDate}
        />
      )}
      {isModalOpen('discard') && (
        <DiscardTemplateModal
          onClose={actions.closeModal}
          onConfirm={() =>
            selection.selectedTemplate &&
            handlers.confirmDiscard(selection.selectedTemplate.layoutId)
          }
          templateName={selection.selectedTemplate?.layout}
          isLoading={actions.isDiscarding}
        />
      )}
      {isModalOpen('export') && (
        <ExportTemplateModal
          onClose={actions.closeModal}
          onConfirm={(options) =>
            selection.selectedTemplate &&
            handlers.handleExportTemplate(selection.selectedTemplate.layoutId, options)
          }
          templateName={selection.selectedTemplate?.layout}
          isLoading={actions.isExporting}
        />
      )}
    </>
  );
}
