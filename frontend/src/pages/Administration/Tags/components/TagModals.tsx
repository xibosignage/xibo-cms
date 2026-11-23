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

import type { ModalType } from '../TagsConfig';

import AddEditTagModal from './AddEditTagModal';
import DeleteTagModal from './DeleteTagModal';
import TagUsageModal from './TagUsageModal';

import type { Tag } from '@/types/tag';

export interface TagModalsProps {
  activeModal: ModalType | null;
  closeModal: () => void;
  handleRefresh: () => void;
  selectedTag: Tag | null;
  itemsToDelete: Tag[];
  deleteError: string | null;
  isDeleting: boolean;
  confirmDelete: (items: Tag[]) => void;
}

export function TagModals({
  activeModal,
  closeModal,
  handleRefresh,
  selectedTag,
  itemsToDelete,
  deleteError,
  isDeleting,
  confirmDelete,
}: TagModalsProps) {
  const isModalOpen = (name: ModalType) => activeModal === name;

  return (
    <>
      {(isModalOpen('add') || isModalOpen('edit')) && (
        <AddEditTagModal
          isOpen
          mode={activeModal === 'edit' ? 'edit' : 'add'}
          tag={activeModal === 'edit' ? selectedTag : null}
          onClose={closeModal}
          onSuccess={handleRefresh}
        />
      )}

      {isModalOpen('delete') && (
        <DeleteTagModal
          isOpen
          onClose={closeModal}
          onDelete={() => confirmDelete(itemsToDelete)}
          itemCount={itemsToDelete.length}
          tagName={itemsToDelete.length === 1 ? itemsToDelete[0]?.tag : undefined}
          error={deleteError}
          isLoading={isDeleting}
        />
      )}

      {isModalOpen('usage') && <TagUsageModal isOpen tag={selectedTag} onClose={closeModal} />}
    </>
  );
}
