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
import { collectNow, copyDisplayGroup, deleteDisplayGroup } from '@/services/displayGroupApi';
import { sendCommand, triggerWebhook } from '@/services/displaysApi';
import { selectFolder } from '@/services/folderApi';
import type { DisplayGroup } from '@/types/displayGroup';

export interface CopyDisplayGroupFormData {
  name: string;
  description: string;
  copyMembers: boolean;
  copyAssignments: boolean;
  copyTags: boolean;
}

interface UseDisplayGroupActionsProps {
  t: TFunction;
  handleRefresh: () => void;
  closeModal: () => void;
  setRowSelection: Dispatch<SetStateAction<RowSelectionState>>;
}

export function useDisplayGroupActions({
  t,
  handleRefresh,
  closeModal,
  setRowSelection,
}: UseDisplayGroupActionsProps) {
  const [isDeleting, setIsDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const [isCopying, setIsCopying] = useState(false);
  const [isMoving, setIsMoving] = useState(false);
  const [isActionPending, setIsActionPending] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);

  const runAction = async (fn: () => Promise<unknown>, errorMessage: string) => {
    try {
      setIsActionPending(true);
      setActionError(null);
      await fn();
      handleRefresh();
      closeModal();
    } catch (error) {
      console.error(error);
      const message =
        isAxiosError(error) && error.response?.data?.message
          ? error.response.data.message
          : errorMessage;
      setActionError(message);
    } finally {
      setIsActionPending(false);
    }
  };

  const confirmDelete = async (itemsToDelete: DisplayGroup[]) => {
    if (itemsToDelete.length === 0 || isDeleting) {
      return;
    }

    try {
      setIsDeleting(true);
      const results = await Promise.allSettled(
        itemsToDelete.map((item) => deleteDisplayGroup(item.displayGroupId)),
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

      notify.success(t('{{count}} item(s) deleted successfully.', { count: itemsToDelete.length }));
      setRowSelection({});
      handleRefresh();
      closeModal();
    } catch (error) {
      console.error(error);
      const message =
        isAxiosError(error) && error.response?.data?.message
          ? error.response.data.message
          : t('Some selected items cannot be deleted.');
      setDeleteError(message);
    } finally {
      setIsDeleting(false);
    }
  };

  const confirmCopy = async (displayGroupId: number, data: CopyDisplayGroupFormData) => {
    try {
      setIsCopying(true);
      await copyDisplayGroup(displayGroupId, {
        displayGroup: data.name,
        description: data.description || undefined,
        copyMembers: data.copyMembers ? 1 : 0,
        copyAssignments: data.copyAssignments ? 1 : 0,
        copyTags: data.copyTags ? 1 : 0,
      });
      notify.success(t('Display group copied successfully.'));
      handleRefresh();
      closeModal();
    } catch (error) {
      console.error(error);
      notify.error(t('Failed to copy display group.'));
    } finally {
      setIsCopying(false);
    }
  };

  const confirmMove = async (itemsToMove: DisplayGroup[], newFolderId: number) => {
    if (!itemsToMove || itemsToMove.length === 0) return;

    setIsMoving(true);
    try {
      const results = await Promise.all(
        itemsToMove.map((item) =>
          selectFolder({
            folderId: newFolderId,
            targetId: item.displayGroupId,
            targetType: 'displaygroup',
          }),
        ),
      );

      const failures = results.filter((res) => !res.success);

      if (failures.length === 0) {
        notify.info(t('{{count}} item(s) moved successfully!', { count: itemsToMove.length }));
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

      setRowSelection({});
      handleRefresh();
      closeModal();
    } catch (error) {
      console.error(error);
      notify.error(t('An unexpected error occurred while moving items.'));
    } finally {
      setIsMoving(false);
    }
  };

  const runBulkAction = async (promises: (() => Promise<unknown>)[], errorMessage: string) => {
    try {
      setIsActionPending(true);
      setActionError(null);
      const results = await Promise.allSettled(promises.map((fn) => fn()));
      const failed = results.filter((r) => r.status === 'rejected');
      if (failed.length > 0) {
        const firstRejected = failed[0] as PromiseRejectedResult;
        const reason = firstRejected.reason;
        const message =
          isAxiosError(reason) && reason.response?.data?.message
            ? reason.response.data.message
            : errorMessage;
        setActionError(message);
        handleRefresh();
      } else {
        handleRefresh();
        closeModal();
      }
    } catch (error) {
      console.error(error);
      const message =
        isAxiosError(error) && error.response?.data?.message
          ? error.response.data.message
          : errorMessage;
      setActionError(message);
    } finally {
      setIsActionPending(false);
    }
  };

  const confirmCollectNow = (displayGroupId: number) =>
    runAction(() => collectNow(displayGroupId), t('Failed to send collection request.'));

  const confirmSendCommand = (displayGroupId: number, commandId: number) =>
    runAction(() => sendCommand(displayGroupId, commandId), t('Failed to send command.'));

  const confirmTriggerWebhook = (displayGroupId: number, triggerCode: string) =>
    runAction(() => triggerWebhook(displayGroupId, triggerCode), t('Failed to trigger webhook.'));

  const confirmBulkSendCommand = (items: DisplayGroup[], commandId: number) =>
    runBulkAction(
      items.map((dg) => () => sendCommand(dg.displayGroupId, commandId)),
      t('Failed to send command to one or more display groups.'),
    );

  const confirmBulkTriggerWebhook = (items: DisplayGroup[], triggerCode: string) =>
    runBulkAction(
      items.map((dg) => () => triggerWebhook(dg.displayGroupId, triggerCode)),
      t('Failed to trigger webhook for one or more display groups.'),
    );

  return {
    isDeleting,
    deleteError,
    setDeleteError,
    confirmDelete,
    isCopying,
    confirmCopy,
    isMoving,
    confirmMove,
    isActionPending,
    actionError,
    confirmCollectNow,
    confirmSendCommand,
    confirmTriggerWebhook,
    confirmBulkSendCommand,
    confirmBulkTriggerWebhook,
  };
}
