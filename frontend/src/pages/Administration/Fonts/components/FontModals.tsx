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
