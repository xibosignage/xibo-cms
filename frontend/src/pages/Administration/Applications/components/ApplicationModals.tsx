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
