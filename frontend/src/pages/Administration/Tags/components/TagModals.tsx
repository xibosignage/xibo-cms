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
