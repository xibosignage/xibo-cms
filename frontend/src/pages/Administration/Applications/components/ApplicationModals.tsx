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

import type { ModalType } from '../ApplicationsConfig';

import AddApplicationModal from './AddApplicationModal';
import DeleteApplicationModal from './DeleteApplicationModal';
import EditApplicationModal from './EditApplicationModal';

import type { Application } from '@/types/application';

export interface ApplicationModalsProps {
  activeModal: ModalType | null;
  closeModal: () => void;
  handleRefresh: () => void;
  selectedApplication: Application | null;
  itemsToDelete: Application[];
  deleteError: string | null;
  isDeleting: boolean;
  onAddSuccess: (newApplication: Application) => void;
  confirmDelete: (items: Application[]) => void;
}

export function ApplicationModals({
  activeModal,
  closeModal,
  handleRefresh,
  selectedApplication,
  itemsToDelete,
  deleteError,
  isDeleting,
  onAddSuccess,
  confirmDelete,
}: ApplicationModalsProps) {
  const isModalOpen = (name: ModalType) => activeModal === name;

  return (
    <>
      {isModalOpen('add') && (
        <AddApplicationModal isOpen onClose={closeModal} onSuccess={onAddSuccess} />
      )}

      {isModalOpen('edit') && (
        <EditApplicationModal
          isOpen
          application={selectedApplication}
          onClose={closeModal}
          onSuccess={handleRefresh}
        />
      )}

      {isModalOpen('delete') && (
        <DeleteApplicationModal
          isOpen
          onClose={closeModal}
          onDelete={() => confirmDelete(itemsToDelete)}
          itemCount={itemsToDelete.length}
          applicationName={itemsToDelete.length === 1 ? itemsToDelete[0]?.name : undefined}
          error={deleteError}
          isLoading={isDeleting}
        />
      )}
    </>
  );
}
