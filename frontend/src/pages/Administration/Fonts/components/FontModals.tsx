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

import type { ModalType } from '../FontsConfig';

import DeleteFontModal from './DeleteFontModal';
import FontDetailsModal from './FontDetailsModal';
import UploadFontModal from './UploadFontModal';

import type { Font } from '@/types/font';

interface FontModalsProps {
  actions: {
    activeModal: ModalType | null;
    closeModal: () => void;
    handleRefresh: () => void;
    deleteError: string | null;
    isDeleting: boolean;
  };
  selection: {
    itemsToDelete: Font[];
    selectedFontId: number | null;
    selectedFont: Font | null;
  };
  handlers: {
    confirmDelete: (items: Font[]) => void;
  };
}

export function FontModals({ actions, selection, handlers }: FontModalsProps) {
  const isModalOpen = (name: string) => actions.activeModal === name;

  return (
    <>
      {isModalOpen('delete') && (
        <DeleteFontModal
          onClose={actions.closeModal}
          onDelete={() => handlers.confirmDelete(selection.itemsToDelete)}
          itemCount={selection.itemsToDelete.length}
          fontName={
            selection.itemsToDelete.length === 1 ? selection.itemsToDelete[0]?.name : undefined
          }
          error={actions.deleteError}
          isLoading={actions.isDeleting}
        />
      )}

      {isModalOpen('upload') && (
        <UploadFontModal onClose={actions.closeModal} onSuccess={actions.handleRefresh} />
      )}

      {isModalOpen('details') && selection.selectedFontId && (
        <FontDetailsModal
          onClose={actions.closeModal}
          fontId={selection.selectedFontId}
          font={selection.selectedFont}
        />
      )}
    </>
  );
}
