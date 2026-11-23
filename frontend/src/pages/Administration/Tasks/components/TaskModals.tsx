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

import type { ModalType } from '../TasksConfig';

import AddEditTaskModal from './AddEditTaskModal';
import DeleteTaskModal from './DeleteTaskModal';
import RunTaskNowModal from './RunTaskNowModal';

import { AUTO_SUBMIT_FORMS } from '@/constants/autoSubmitForms';
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
  taskToRun: Task | null;
  runError: string | null;
  isRunning: boolean;
  onConfirmRunNow: () => void;
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
  taskToRun,
  runError,
  isRunning,
  onConfirmRunNow,
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

      {isModalOpen('runNow') && taskToRun && (
        <RunTaskNowModal
          onClose={closeModal}
          onConfirm={onConfirmRunNow}
          isActionPending={isRunning}
          actionError={runError}
          autoSubmitFormId={AUTO_SUBMIT_FORMS.taskRunNow}
        />
      )}
    </>
  );
}
