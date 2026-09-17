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
import { selectFolder, type ApiResult } from '@/services/folderApi';
import { copyMenuBoard, deleteMenuBoard } from '@/services/menuBoardApi';
import type { MenuBoard } from '@/types/menuBoard';
import { isAlreadyDeletedError } from '@/utils/errors';

interface UseMenuBoardActionsProps {
  t: TFunction;
  handleRefresh: () => void;
  closeModal: () => void;
  setRowSelection: Dispatch<SetStateAction<RowSelectionState>>;
  setItemsToMove: (items: MenuBoard[]) => void;
}

export function useMenuBoardActions({
  t,
  handleRefresh,
  closeModal,
  setRowSelection,
  setItemsToMove,
}: UseMenuBoardActionsProps) {
  const [isDeleting, setIsDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const [isCloning, setIsCloning] = useState(false);

  const confirmDelete = async (itemsToDelete: MenuBoard[]) => {
    if (itemsToDelete.length === 0 || isDeleting) {
      return;
    }

    try {
      setIsDeleting(true);
      const results = await Promise.allSettled(
        itemsToDelete.map((item) => deleteMenuBoard(item.menuId)),
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
          t('{{count}} menu board(s) could not be deleted.', { count: trueFailed.length });
        const successPart =
          deletedCount > 0
            ? t('{{count}} menu board(s) deleted successfully.', { count: deletedCount })
            : '';

        setDeleteError([successPart, failurePart].filter(Boolean).join(' '));
        setRowSelection({});
        handleRefresh();
        return;
      }

      notify.success(t('{{count}} menu board(s) deleted successfully.', { count: deletedCount }));
      setRowSelection({});
      handleRefresh();
      closeModal();
    } finally {
      setIsDeleting(false);
    }
  };

  const handleConfirmClone = async (
    selectedMenuBoard: MenuBoard | null,
    name: string,
    description: string,
    code: string,
  ) => {
    if (!selectedMenuBoard) {
      return;
    }

    try {
      setIsCloning(true);
      await copyMenuBoard({ menuBoardId: selectedMenuBoard.menuId, name, description, code });
      notify.success(t('Menu Board copied successfully'));
      handleRefresh();
      closeModal();
    } catch (error) {
      console.error('Copy menu board failed', error);
      notify.error(t('Failed to copy Menu Board'));
    } finally {
      setIsCloning(false);
    }
  };

  const handleConfirmMove = async (itemsToMove: MenuBoard[], newFolderId: number) => {
    if (!itemsToMove || itemsToMove.length === 0) {
      return;
    }

    try {
      const results: ApiResult[] = [];
      for (const item of itemsToMove) {
        results.push(
          await selectFolder({
            folderId: newFolderId,
            targetId: item.menuId,
            targetType: 'menuboard',
          }),
        );
      }
      const failures = results.filter((res) => !res.success);

      if (failures.length === 0) {
        notify.info(t('{{count}} items moved successfully!', { count: itemsToMove.length }));
      } else if (failures.length === itemsToMove.length) {
        notify.error(t('Failed to move items.'));
      } else {
        notify.warning(
          t('Moved {{success}} items, but {{fail}} failed.', {
            success: itemsToMove.length - failures.length,
            fail: failures.length,
          }),
        );
      }

      setItemsToMove([]);
      setRowSelection({});
      handleRefresh();
      closeModal();
    } catch (error) {
      console.error(error);
      notify.error(t('An unexpected error occurred while moving items.'));
    }
  };

  return {
    isDeleting,
    deleteError,
    setDeleteError,
    isCloning,
    confirmDelete,
    handleConfirmClone,
    handleConfirmMove,
  };
}
