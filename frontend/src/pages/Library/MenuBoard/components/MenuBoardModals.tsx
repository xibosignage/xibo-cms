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

import AddAndEditMenuBoardModal from './AddAndEditMenuBoardModal';
import CopyMenuBoardModal from './CopyMenuBoardModal';
import DeleteMenuBoardModal from './DeleteMenuBoardModal';

import FolderActionModals from '@/components/ui/FolderActionModals';
import MoveModal from '@/components/ui/modals/MoveModal';
import ShareModal from '@/components/ui/modals/ShareModal';
import type { useFolderActions } from '@/hooks/useFolderActions';
import type { MenuBoard } from '@/types/menuBoard';

interface MenuBoardModalsProps {
  actions: {
    activeModal: string | null;
    closeModal: () => void;
    handleRefresh: () => void;
    deleteError: string | null;
    isDeleting: boolean;
    isCloning: boolean;
  };
  selection: {
    selectedMenuBoard: MenuBoard | null;
    selectedMenuBoardId: number | null;
    defaultFolderId?: number;
    itemsToDelete: MenuBoard[];
    itemsToMove: MenuBoard[];
    existingNames: string[];
    shareEntityIds: number | number[] | null;
    setShareEntityIds: React.Dispatch<React.SetStateAction<number | number[] | null>>;
  };
  handlers: {
    confirmDelete: () => void;
    handleConfirmClone: (name: string, description: string, code: string) => void;
    handleConfirmMove: (folderId: number) => void;
  };
  folderActions: ReturnType<typeof useFolderActions>;
}

export function MenuBoardModals({
  actions,
  selection,
  handlers,
  folderActions,
}: MenuBoardModalsProps) {
  const { t } = useTranslation();
  const isModalOpen = (name: string) => actions.activeModal === name;

  return (
    <>
      {isModalOpen('edit') && (
        <AddAndEditMenuBoardModal
          type={selection.selectedMenuBoardId ? 'edit' : 'add'}
          defaultFolderId={selection.defaultFolderId}
          onClose={actions.closeModal}
          data={selection.selectedMenuBoard}
          onSave={() => {
            actions.handleRefresh();
          }}
        />
      )}

      {isModalOpen('share') && (
        <ShareModal
          title={t('Share Menu Board')}
          onClose={() => {
            actions.closeModal();
            selection.setShareEntityIds(null);
            actions.handleRefresh();
          }}
          entityType="MenuBoard"
          entityId={selection.shareEntityIds ?? selection.selectedMenuBoard?.menuId ?? null}
        />
      )}

      <FolderActionModals folderActions={folderActions} />

      {isModalOpen('delete') && (
        <DeleteMenuBoardModal
          onClose={actions.closeModal}
          onDelete={handlers.confirmDelete}
          itemCount={selection.itemsToDelete.length}
          menuBoardName={
            selection.itemsToDelete.length === 1 ? selection.itemsToDelete[0]?.name : undefined
          }
          error={actions.deleteError}
          isLoading={actions.isDeleting}
        />
      )}

      {isModalOpen('copy') && (
        <CopyMenuBoardModal
          onClose={actions.closeModal}
          onConfirm={(name, description, code) =>
            handlers.handleConfirmClone(name, description, code)
          }
          menuBoard={selection.selectedMenuBoard}
          isLoading={actions.isCloning}
          existingNames={selection.existingNames}
        />
      )}

      {isModalOpen('move') && (
        <MoveModal
          onClose={actions.closeModal}
          onConfirm={handlers.handleConfirmMove}
          items={selection.itemsToMove}
          entityLabel={t('Menu Board')}
        />
      )}
    </>
  );
}
