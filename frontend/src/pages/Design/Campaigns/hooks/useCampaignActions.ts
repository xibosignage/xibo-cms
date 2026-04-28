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
import { copyCampaign, deleteCampaign } from '@/services/campaignApi';
import { selectFolder } from '@/services/folderApi';
import type { Campaign } from '@/types/campaign';

interface UseCampaignActionsProps {
  t: TFunction;
  handleRefresh: () => void;
  closeModal: () => void;
  setRowSelection: Dispatch<SetStateAction<RowSelectionState>>;
  setItemsToMove: (items: Campaign[]) => void;
}

export function useCampaignActions({
  t,
  handleRefresh,
  closeModal,
  setRowSelection,
  setItemsToMove,
}: UseCampaignActionsProps) {
  const [isDeleting, setIsDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const [isCloning, setIsCloning] = useState(false);

  const confirmDelete = async (itemsToDelete: Campaign[]) => {
    if (itemsToDelete.length === 0 || isDeleting) return;

    try {
      setIsDeleting(true);

      const results = await Promise.allSettled(
        itemsToDelete.map((item) => deleteCampaign(item.campaignId)),
      );

      const failed = results.filter((r) => r.status === 'rejected');

      if (failed.length > 0) {
        const firstRejected = failed[0] as PromiseRejectedResult;
        const reason = firstRejected.reason;

        const message =
          isAxiosError(reason) && reason.response?.data?.message
            ? reason.response.data.message
            : t('{{count}} campaign(s) could not be deleted.', {
                count: failed.length,
              });

        setDeleteError(message);

        setRowSelection({});
        handleRefresh();
        return;
      }

      notify.success(
        t('{{count}} campaign(s) deleted successfully', {
          count: itemsToDelete.length,
        }),
      );

      setRowSelection({});
      handleRefresh();
      closeModal();
    } catch (error) {
      console.error(error);

      const message =
        isAxiosError(error) && error.response?.data?.message
          ? error.response.data.message
          : t('Some campaigns could not be deleted.');

      setDeleteError(message);
    } finally {
      setIsDeleting(false);
    }
  };

  const handleConfirmClone = async (selectedCampaign: Campaign | null, newName: string) => {
    if (!selectedCampaign) return;

    try {
      setIsCloning(true);

      await copyCampaign(selectedCampaign.campaignId, {
        name: newName,
      });

      notify.success(t('Campaign copied successfully'));
      handleRefresh();
      closeModal();
    } catch (error) {
      console.error('Copy campaign failed', error);
      notify.error(t('Failed to copy campaign'));
    } finally {
      setIsCloning(false);
    }
  };

  const handleConfirmMove = async (itemsToMove: Campaign[], newFolderId: number) => {
    if (!itemsToMove || itemsToMove.length === 0) return;

    const movePromises = itemsToMove.map((item) =>
      selectFolder({
        folderId: newFolderId,
        targetId: item.campaignId,
        targetType: 'campaign',
      }),
    );

    try {
      const results = await Promise.all(movePromises);
      const failures = results.filter((res) => !res.success);

      if (failures.length === 0) {
        notify.info(
          t('{{count}} items moved successfully!', {
            count: itemsToMove.length,
          }),
        );
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
