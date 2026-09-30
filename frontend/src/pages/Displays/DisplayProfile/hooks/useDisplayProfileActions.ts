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
import { copyDisplayProfile, deleteDisplayProfile } from '@/services/displayProfileApi';
import type { DisplayProfile } from '@/types/displayProfile';
import { isAlreadyDeletedError } from '@/utils/errors';

interface UseDisplayProfileActionsProps {
  t: TFunction;
  handleRefresh: () => void;
  closeModal: () => void;
  setRowSelection: Dispatch<SetStateAction<RowSelectionState>>;
}

export function useDisplayProfileActions({
  t,
  handleRefresh,
  closeModal,
  setRowSelection,
}: UseDisplayProfileActionsProps) {
  const [isDeleting, setIsDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const [isCopying, setIsCopying] = useState(false);

  const confirmDelete = async (itemsToDelete: DisplayProfile[]) => {
    if (itemsToDelete.length === 0 || isDeleting) {
      return;
    }

    try {
      setIsDeleting(true);
      const results = await Promise.allSettled(
        itemsToDelete.map((item) => deleteDisplayProfile(item.displayProfileId)),
      );

      const trueFailed = results.filter(
        (r) => r.status === 'rejected' && !isAlreadyDeletedError(r.reason),
      );
      const deletedCount = itemsToDelete.length - trueFailed.length;

      if (trueFailed.length > 0) {
        const firstRejected = trueFailed[0] as PromiseRejectedResult;
        const reason = firstRejected.reason;
        const specificMessage =
          isAxiosError(reason) && reason.response?.data?.message
            ? reason.response.data.message
            : undefined;
        const failurePart =
          specificMessage ??
          t('{{count}} display profile(s) could not be deleted.', { count: trueFailed.length });
        const successPart =
          deletedCount > 0
            ? t('{{count}} display profile(s) deleted successfully.', { count: deletedCount })
            : '';

        setDeleteError([successPart, failurePart].filter(Boolean).join(' '));
        setRowSelection({});
        handleRefresh();
        return;
      }

      notify.success(
        t('{{count}} display profile(s) deleted successfully.', {
          count: deletedCount,
        }),
      );
      setRowSelection({});
      handleRefresh();
      closeModal();
    } finally {
      setIsDeleting(false);
    }
  };

  const confirmCopy = async (displayProfileId: number, newName: string) => {
    try {
      setIsCopying(true);
      await copyDisplayProfile(displayProfileId, newName);
      handleRefresh();
      closeModal();
    } catch (error) {
      console.error(error);
    } finally {
      setIsCopying(false);
    }
  };

  return {
    isDeleting,
    deleteError,
    setDeleteError,
    confirmDelete,
    isCopying,
    confirmCopy,
  };
}
