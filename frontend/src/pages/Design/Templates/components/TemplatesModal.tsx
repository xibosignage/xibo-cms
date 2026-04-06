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

import FolderActionModals from '@/components/ui/FolderActionModals';
import MoveModal from '@/components/ui/modals/MoveModal';
import ShareModal from '@/components/ui/modals/ShareModal';
import type { useFolderActions } from '@/hooks/useFolderActions';
import type { Template } from '@/types/templates';

interface TemplatesModalsProps {
  actions: {
    activeModal: string | null;
    closeModal: () => void;
    handleRefresh: () => void;
    deleteError: string | null;
    isDeleting: boolean;
    isCloning: boolean;
  };
  selection: {
    selectedTemplate: Template | null;
    selectedTemplateId: number | null;
    itemsToDelete: Template[];
    existingNames: string[];
    itemsToMove: Template[];
    shareEntityIds: number | number[] | null;
    setShareEntityIds: React.Dispatch<React.SetStateAction<number | number[] | null>>;
  };
  handlers: {
    confirmDelete: (items: Template[]) => void;
    handleConfirmMove: (newFolderId: number) => void;
    handleConfirmClone: (newName: string, description: string, copyTemplate: boolean) => void;
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
    </>
  );
}
