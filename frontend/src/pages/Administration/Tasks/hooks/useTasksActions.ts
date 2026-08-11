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

import type { RowSelectionState } from '@tanstack/react-table';
import { isAxiosError } from 'axios';
import type { TFunction } from 'i18next';
import type { Dispatch, SetStateAction } from 'react';
import { useState } from 'react';

import { notify } from '@/components/ui/Notification';
import { deleteTask, runTaskNow } from '@/services/taskApi';
import type { Task } from '@/types/task';

interface UseTasksActionsProps {
  t: TFunction;
  handleRefresh: () => void;
  closeModal: () => void;
  setRowSelection: Dispatch<SetStateAction<RowSelectionState>>;
}

export function useTasksActions({
  t,
  handleRefresh,
  closeModal,
  setRowSelection,
}: UseTasksActionsProps) {
  const [isDeleting, setIsDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  const [isRunning, setIsRunning] = useState(false);
  const [runError, setRunError] = useState<string | null>(null);

  const confirmDelete = async (itemsToDelete: Task[]) => {
    if (itemsToDelete.length === 0 || isDeleting) {
      return;
    }

    try {
      setIsDeleting(true);
      const results = await Promise.allSettled(
        itemsToDelete.map((item) => deleteTask(item.taskId)),
      );

      const failed = results.filter((r) => r.status === 'rejected');
      if (failed.length > 0) {
        const firstRejected = failed[0] as PromiseRejectedResult;
        const reason = firstRejected.reason;
        const message =
          isAxiosError(reason) && reason.response?.data?.message
            ? reason.response.data.message
            : t('{{count}} item(s) could not be deleted.', { count: failed.length });
        setDeleteError(message);
        setRowSelection({});
        handleRefresh();
        return;
      }

      notify.success(t('{{count}} task(s) deleted successfully.', { count: itemsToDelete.length }));
      setRowSelection({});
      handleRefresh();
      closeModal();
    } finally {
      setIsDeleting(false);
    }
  };

  const runNow = async (task: Task, options?: { notifyOnError?: boolean }) => {
    if (isRunning) {
      return;
    }

    try {
      setIsRunning(true);
      await runTaskNow(task.taskId);
      notify.success(t('Task run started'));
      handleRefresh();
      closeModal();
    } catch (error) {
      const message =
        isAxiosError(error) && error.response?.data?.message
          ? error.response.data.message
          : t('Failed to run task');

      if (options?.notifyOnError) {
        notify.error(message);
      } else {
        setRunError(message);
      }
    } finally {
      setIsRunning(false);
    }
  };

  return {
    isDeleting,
    deleteError,
    setDeleteError,
    confirmDelete,
    isRunning,
    runError,
    setRunError,
    runNow,
  };
}
