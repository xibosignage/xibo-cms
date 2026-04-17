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
import { useNavigate } from 'react-router-dom';

import { notify } from '@/components/ui/Notification';
import {
  checkLicence,
  collectNow,
  deleteDisplay,
  moveCms,
  moveCmsCancel,
  purgeAll,
  requestScreenShot,
  sendCommand,
  setBandwidthLimitMultiple,
  setDefaultLayout,
  toggleDisplayAuthorised,
  triggerWebhook,
  wakeOnLan,
} from '@/services/displaysApi';
import type { MoveCmsData } from '@/services/displaysApi';
import { selectFolder } from '@/services/folderApi';
import type { Display, DisplayCommandTarget } from '@/types/display';

interface UseDisplaysActionsProps {
  t: TFunction;
  handleRefresh: () => void;
  closeModal: () => void;
  setRowSelection: Dispatch<SetStateAction<RowSelectionState>>;
}

export function useDisplaysActions({
  t,
  handleRefresh,
  closeModal,
  setRowSelection,
}: UseDisplaysActionsProps) {
  const navigate = useNavigate();

  const [isDeleting, setIsDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  const [isActionPending, setIsActionPending] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);

  const confirmDelete = async (itemsToDelete: Display[]) => {
    if (itemsToDelete.length === 0 || isDeleting) {
      return;
    }

    try {
      setIsDeleting(true);
      const results = await Promise.allSettled(
        itemsToDelete.map((item) => deleteDisplay(item.displayId)),
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
          : t('Some selected items cannot be deleted.');
      setDeleteError(message);
    } finally {
      setIsDeleting(false);
    }
  };

  const handleConfirmMove = async (itemsToMove: Display[], newFolderId: number) => {
    if (!itemsToMove || itemsToMove.length === 0) {
      return;
    }

    try {
      const movePromises = itemsToMove.map((item) =>
        selectFolder({
          folderId: newFolderId,
          targetId: item.displayId,
          targetType: 'displaygroup',
        }),
      );
      await Promise.all(movePromises);
      handleRefresh();
      closeModal();
    } catch (error) {
      console.error(error);
      notify.error(
        isAxiosError(error) && error.response?.data?.message
          ? error.response.data.message
          : t('Failed to move one or more displays.'),
      );
    }
  };

  const handleManage = (display: Display) => {
    window.open(`/display/manage/${display.displayId}`, '_blank');
  };

  const runAction = async (fn: () => Promise<unknown>, errorMessage: string) => {
    try {
      setIsActionPending(true);
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

  const confirmAuthorise = (display: Display) =>
    runAction(
      () => toggleDisplayAuthorised(display.displayId),
      t('Failed to toggle authorisation.'),
    );

  const confirmCheckLicence = (display: Display) =>
    runAction(() => checkLicence(display.displayId), t('Failed to check licence.'));

  const confirmRequestScreenShot = (display: Display) =>
    runAction(() => requestScreenShot(display.displayId), t('Failed to request screenshot.'));

  const confirmCollectNow = (display: Display) =>
    runAction(() => collectNow(display.displayGroupId), t('Failed to trigger collection.'));

  const confirmWakeOnLan = (display: Display) =>
    runAction(() => wakeOnLan(display.displayId), t('Failed to send Wake on LAN.'));

  const confirmPurgeAll = (display: Display) =>
    runAction(() => purgeAll(display.displayId), t('Failed to purge display data.'));

  const confirmTriggerWebhook = (display: Display, triggerCode: string) =>
    runAction(
      () => triggerWebhook(display.displayGroupId, triggerCode),
      t('Failed to trigger webhook.'),
    );

  const confirmSetDefaultLayout = (display: Display, layoutId: number) =>
    runAction(
      () => setDefaultLayout(display.displayId, layoutId),
      t('Failed to set default layout.'),
    );

  const runBulkAction = async (promises: (() => Promise<unknown>)[], errorMessage: string) => {
    try {
      setIsActionPending(true);
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

  const confirmMoveCms = (display: Display, data: MoveCmsData) =>
    runAction(() => moveCms(display.displayId, data), t('Failed to transfer to another CMS.'));

  const confirmBulkMoveCms = (items: Display[], data: MoveCmsData) =>
    runBulkAction(
      items.map((d) => () => moveCms(d.displayId, data)),
      t('Failed to transfer one or more displays to another CMS.'),
    );

  const confirmMoveCmsCancel = (display: Display) =>
    runAction(() => moveCmsCancel(display.displayId), t('Failed to cancel CMS transfer.'));

  const confirmSetBandwidth = (items: Display[], bandwidthLimitKb: number) =>
    runAction(
      () =>
        setBandwidthLimitMultiple(
          items.map((d) => d.displayId),
          bandwidthLimitKb,
        ),
      t('Failed to set bandwidth limit.'),
    );

  const confirmBulkAuthorise = (items: Display[]) =>
    runBulkAction(
      items.map((d) => () => toggleDisplayAuthorised(d.displayId)),
      t('Failed to toggle authorisation for one or more displays.'),
    );

  const confirmBulkCheckLicence = (items: Display[]) =>
    runBulkAction(
      items.map((d) => () => checkLicence(d.displayId)),
      t('Failed to check licence for one or more displays.'),
    );

  const confirmBulkRequestScreenShot = (items: Display[]) =>
    runBulkAction(
      items.map((d) => () => requestScreenShot(d.displayId)),
      t('Failed to request screenshot for one or more displays.'),
    );

  const confirmBulkCollectNow = (items: Display[]) =>
    runBulkAction(
      items.map((d) => () => collectNow(d.displayGroupId)),
      t('Failed to trigger collection for one or more displays.'),
    );

  const confirmBulkTriggerWebhook = (items: DisplayCommandTarget[], triggerCode: string) =>
    runBulkAction(
      items.map((d) => () => triggerWebhook(d.displayGroupId, triggerCode)),
      t('Failed to trigger webhook for one or more displays.'),
    );

  const confirmBulkSetDefaultLayout = (items: Display[], layoutId: number) =>
    runBulkAction(
      items.map((d) => () => setDefaultLayout(d.displayId, layoutId)),
      t('Failed to set default layout for one or more displays.'),
    );

  const confirmSendCommand = (items: DisplayCommandTarget[], commandId: number) =>
    runBulkAction(
      items.map((d) => () => sendCommand(d.displayGroupId, commandId)),
      t('Failed to send command to one or more displays.'),
    );

  const handleJumpToScheduledLayouts = (displayGroupId: number) => {
    navigate('/design/layout', {
      state: { activeDisplayGroupId: displayGroupId },
    });
  };

  return {
    isDeleting,
    deleteError,
    setDeleteError,
    confirmDelete,
    confirmAuthorise,
    handleConfirmMove,
    handleManage,
    isActionPending,
    actionError,
    setActionError,
    confirmCheckLicence,
    confirmRequestScreenShot,
    confirmCollectNow,
    confirmWakeOnLan,
    confirmPurgeAll,
    confirmTriggerWebhook,
    confirmSetDefaultLayout,
    confirmMoveCms,
    confirmMoveCmsCancel,
    confirmBulkMoveCms,
    confirmSetBandwidth,
    confirmBulkAuthorise,
    confirmBulkCheckLicence,
    confirmBulkRequestScreenShot,
    confirmBulkCollectNow,
    confirmBulkTriggerWebhook,
    confirmBulkSetDefaultLayout,
    confirmSendCommand,
    handleJumpToScheduledLayouts,
  };
}
