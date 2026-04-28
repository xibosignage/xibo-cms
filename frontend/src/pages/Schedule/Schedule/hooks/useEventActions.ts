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
import { cloneEvent, deleteEvent, deleteEventOccurrence } from '@/services/eventApi';
import type { Event } from '@/types/event';

interface UseEventActionsProps {
  t: TFunction;
  handleRefresh: () => void;
  closeModal: () => void;
  setRowSelection: Dispatch<SetStateAction<RowSelectionState>>;
}

export function useEventActions({
  t,
  handleRefresh,
  closeModal,
  setRowSelection,
}: UseEventActionsProps) {
  const [isDeleting, setIsDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const [isCloning, setIsCloning] = useState(false);

  const confirmDelete = async (itemsToDelete: Event[]) => {
    if (itemsToDelete.length === 0 || isDeleting) {
      return;
    }

    try {
      setIsDeleting(true);
      const results = await Promise.allSettled(
        itemsToDelete.map((item) => deleteEvent(item.eventId)),
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

      setRowSelection({});
      handleRefresh();
      closeModal();
    } catch (error) {
      console.error(error);
      const message =
        isAxiosError(error) && error.response?.data?.message
          ? error.response.data.message
          : t('Some selected items could not be deleted.');
      setDeleteError(message);
    } finally {
      setIsDeleting(false);
    }
  };

  const confirmDeleteOccurrence = async (scheduleEvent: Event) => {
    try {
      setIsDeleting(true);
      await deleteEventOccurrence(scheduleEvent.eventId, scheduleEvent.fromDt, scheduleEvent.toDt);
      handleRefresh();
      closeModal();
    } catch (error) {
      console.error(error);
      const message =
        isAxiosError(error) && error.response?.data?.message
          ? error.response.data.message
          : t('Could not delete this occurrence.');
      setDeleteError(message);
    } finally {
      setIsDeleting(false);
    }
  };

  const handleConfirmClone = async (selectedEvent: Event | null, newName: string) => {
    if (!selectedEvent) {
      return;
    }

    try {
      setIsCloning(true);
      await cloneEvent({
        eventId: selectedEvent.eventId,
        name: newName,
      });

      notify.success(t('Event copied successfully'));
      handleRefresh();
      closeModal();
    } catch (error) {
      console.error('Copy event failed', error);
      notify.error(t('Failed to copy event'));
    } finally {
      setIsCloning(false);
    }
  };

  return {
    isDeleting,
    isCloning,
    deleteError,
    setDeleteError,
    confirmDelete,
    confirmDeleteOccurrence,
    handleConfirmClone,
  };
}
