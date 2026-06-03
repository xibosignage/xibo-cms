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

import AddAndEditMenuBoardCategoryModal from './AddAndEditMenuBoardCategoryModal';
import CopyMenuBoardCategoryModal from './CopyMenuBoardCategoryModal';
import DeleteMenuBoardCategoryModal from './DeleteMenuBoardCategoryModal';

import type { MenuBoardCategory } from '@/types/menuBoardCategory';

interface MenuBoardCategoryModalsProps {
  menuId: string | number;
  actions: {
    activeModal: string | null;
    closeModal: () => void;
    handleRefresh: () => void;
    deleteError: string | null;
    isDeleting: boolean;
    isCloning: boolean;
  };
  selection: {
    selectedCategory: MenuBoardCategory | null;
    itemsToDelete: MenuBoardCategory[];
    existingNames: string[];
  };
  handlers: {
    confirmDelete: () => void;
    handleConfirmCopy: (name: string, description: string, code: string) => void;
  };
}

export function MenuBoardCategoryModals({
  menuId,
  actions,
  selection,
  handlers,
}: MenuBoardCategoryModalsProps) {
  const isModalOpen = (name: string) => actions.activeModal === name;

  return (
    <>
      {isModalOpen('edit') && (
        <AddAndEditMenuBoardCategoryModal
          type={selection.selectedCategory ? 'edit' : 'add'}
          menuId={menuId}
          onClose={actions.closeModal}
          data={selection.selectedCategory}
          onSave={actions.handleRefresh}
        />
      )}

      {isModalOpen('copy') && (
        <CopyMenuBoardCategoryModal
          onClose={actions.closeModal}
          onConfirm={handlers.handleConfirmCopy}
          category={selection.selectedCategory}
          isLoading={actions.isCloning}
          existingNames={selection.existingNames}
        />
      )}

      {isModalOpen('delete') && (
        <DeleteMenuBoardCategoryModal
          onClose={actions.closeModal}
          onDelete={handlers.confirmDelete}
          itemCount={selection.itemsToDelete.length}
          categoryName={
            selection.itemsToDelete.length === 1 ? selection.itemsToDelete[0]?.name : undefined
          }
          error={actions.deleteError}
          isLoading={actions.isDeleting}
        />
      )}
    </>
  );
}
