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

import AddAndEditMenuBoardProductModal from './AddAndEditMenuBoardProductModal';
import CopyMenuBoardProductModal from './CopyMenuBoardProductModal';
import DeleteMenuBoardProductModal from './DeleteMenuBoardProductModal';

import type { MenuBoardProduct } from '@/types/menuBoardProduct';

interface MenuBoardProductModalsProps {
  menuCategoryId: string | number;
  actions: {
    activeModal: string | null;
    closeModal: () => void;
    handleRefresh: () => void;
    deleteError: string | null;
    isDeleting: boolean;
    isCloning: boolean;
  };
  selection: {
    selectedProduct: MenuBoardProduct | null;
    itemsToDelete: MenuBoardProduct[];
    existingNames: string[];
  };
  handlers: {
    confirmDelete: () => void;
    handleConfirmCopy: (newName: string, newPrice: number | null, newCode: string) => void;
  };
}

export function MenuBoardProductModals({
  menuCategoryId,
  actions,
  selection,
  handlers,
}: MenuBoardProductModalsProps) {
  const isModalOpen = (name: string) => actions.activeModal === name;

  return (
    <>
      {isModalOpen('edit') && (
        <AddAndEditMenuBoardProductModal
          type={selection.selectedProduct ? 'edit' : 'add'}
          menuCategoryId={menuCategoryId}
          onClose={actions.closeModal}
          data={selection.selectedProduct}
          onSave={actions.handleRefresh}
        />
      )}

      {isModalOpen('copy') && (
        <CopyMenuBoardProductModal
          onClose={actions.closeModal}
          onConfirm={handlers.handleConfirmCopy}
          product={selection.selectedProduct}
          existingNames={selection.existingNames}
          isLoading={actions.isCloning}
        />
      )}

      {isModalOpen('delete') && (
        <DeleteMenuBoardProductModal
          onClose={actions.closeModal}
          onDelete={handlers.confirmDelete}
          itemCount={selection.itemsToDelete.length}
          productName={
            selection.itemsToDelete.length === 1 ? selection.itemsToDelete[0]?.name : undefined
          }
          error={actions.deleteError}
          isLoading={actions.isDeleting}
        />
      )}
    </>
  );
}
