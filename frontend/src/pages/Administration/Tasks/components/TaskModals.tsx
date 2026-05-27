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

import type { ModalType } from '../TasksConfig';

import AddEditTaskModal from './AddEditTaskModal';
import DeleteTaskModal from './DeleteTaskModal';

import type { Task } from '@/types/task';

export interface TaskModalsProps {
  activeModal: ModalType | null;
  closeModal: () => void;
  handleRefresh: () => void;
  selectedTask: Task | null;
  itemsToDelete: Task[];
  deleteError: string | null;
  isDeleting: boolean;
  confirmDelete: (items: Task[]) => void;
}

export function TaskModals({
  activeModal,
  closeModal,
  handleRefresh,
  selectedTask,
  itemsToDelete,
  deleteError,
  isDeleting,
  confirmDelete,
}: TaskModalsProps) {
  const isModalOpen = (name: ModalType) => activeModal === name;

  return (
    <>
      {(isModalOpen('add') || isModalOpen('edit')) && (
        <AddEditTaskModal
          isOpen
          mode={activeModal === 'edit' ? 'edit' : 'add'}
          task={activeModal === 'edit' ? selectedTask : null}
          onClose={closeModal}
          onSuccess={handleRefresh}
        />
      )}

      {isModalOpen('delete') && (
        <DeleteTaskModal
          isOpen
          onClose={closeModal}
          onDelete={() => confirmDelete(itemsToDelete)}
          itemCount={itemsToDelete.length}
          taskName={itemsToDelete.length === 1 ? itemsToDelete[0]?.name : undefined}
          error={deleteError}
          isLoading={isDeleting}
        />
      )}
    </>
  );
}
