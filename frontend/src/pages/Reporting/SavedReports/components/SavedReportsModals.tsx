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

import type { ModalType } from '../SavedReportsConfig';

import DeleteSavedReportModal from './DeleteSavedReportModal';

import type { SavedReport } from '@/types/savedReport';

export interface SavedReportModalsProps {
  activeModal: ModalType | null;
  closeModal: () => void;
  itemsToDelete: SavedReport[];
  deleteError: string | null;
  isDeleting: boolean;
  confirmDelete: (items: SavedReport[]) => void;
}

export function SavedReportModals({
  activeModal,
  closeModal,
  itemsToDelete,
  deleteError,
  isDeleting,
  confirmDelete,
}: SavedReportModalsProps) {
  return (
    <>
      {activeModal === 'delete' && (
        <DeleteSavedReportModal
          isOpen
          onClose={closeModal}
          onDelete={() => confirmDelete(itemsToDelete)}
          itemCount={itemsToDelete.length}
          reportName={itemsToDelete.length === 1 ? itemsToDelete[0]?.saveAs : undefined}
          error={deleteError}
          isLoading={isDeleting}
        />
      )}
    </>
  );
}
