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
import type { PublishValue } from '@/components/ui/forms/PublishDateSelect';
import { selectFolder } from '@/services/folderApi';
import {
  assignLayoutToCampaign,
  checkoutLayout,
  copyLayout,
  createLayout,
  deleteLayout,
  discardLayout,
  exportLayout,
  publishLayout,
} from '@/services/layoutsApi';
import type { Layout } from '@/types/layout';

interface UsePlaylistActionsProps {
  t: TFunction;
  handleRefresh: () => void;
  closeModal: () => void;
  setRowSelection: Dispatch<SetStateAction<RowSelectionState>>;
  setItemsToMove: (items: Layout[]) => void;
}

export function useLayoutActions({
  t,
  handleRefresh,
  closeModal,
  setRowSelection,
  setItemsToMove,
}: UsePlaylistActionsProps) {
  const [isDeleting, setIsDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const [isCloning, setIsCloning] = useState(false);
  const [isPublishing, setIsPublishing] = useState(false);
  const [isDiscarding, setIsDiscarding] = useState(false);
  const [isAssigning, setIsAssigning] = useState(false);
  const [isExporting, setIsExporting] = useState(false);

  const navigate = useNavigate();

  const confirmDelete = async (itemsToDelete: Layout[]) => {
    if (itemsToDelete.length === 0 || isDeleting) {
      return;
    }

    try {
      setIsDeleting(true);
      const results = await Promise.allSettled(
        itemsToDelete.map((item) => deleteLayout(item.layoutId)),
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

  const handleConfirmClone = async (
    selectedLayout: Layout | null,
    newName: string,
    description: string,
    copyMediaFiles: boolean,
  ) => {
    if (!selectedLayout) {
      return;
    }
    try {
      setIsCloning(true);

      await copyLayout(selectedLayout.layoutId, {
        name: newName,
        description,
        copyMediaFiles: copyMediaFiles ? 1 : 0,
      });

      notify.success(t('Layout copied successfully'));
      handleRefresh();
      closeModal();
    } catch (error) {
      console.error('Copy layout failed', error);
      notify.error(t('Failed to copy layout'));
    } finally {
      setIsCloning(false);
    }
  };

  const handleConfirmMove = async (itemsToMove: Layout[], newFolderId: number) => {
    if (!itemsToMove || itemsToMove.length === 0) {
      return;
    }

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

  const handleCreateLayout = async () => {
    try {
      const newLayout = await createLayout();

      if (!newLayout?.layoutId) {
        throw new Error('Invalid layout response');
      }

      window.open(`/layout/designer/${newLayout.layoutId}`, '_blank', 'noopener,noreferrer');
    } catch (error) {
      console.error(error);
      notify.error(t('Failed to create layout'));
    }
  };

  const handleOpenLayout = (layoutId: number) => {
    window.open(`/layout/designer/${layoutId}`, '_blank');
  };

  const handleCheckoutLayout = async (layoutId: number) => {
    notify.info(t('Preparing layout for editing...'));

    try {
      await checkoutLayout(layoutId);

      window.open(`/layout/designer/${layoutId}`, '_blank');

      notify.success(t('Layout checked out successfully'));
    } catch (error) {
      console.error(error);
      notify.error(t('Failed to checkout layout'));
    }
  };

  const handleConfirmDiscard = async (layoutId: number | null) => {
    if (!layoutId || isDiscarding) return;

    try {
      setIsDiscarding(true);

      notify.info(t('Discarding layout changes...'));

      await discardLayout(layoutId);

      notify.success(t('Layout restored successfully'));

      setRowSelection({});
      handleRefresh();
      closeModal();
    } catch (error) {
      console.error(error);
      notify.error(t('Failed to discard layout changes'));
    } finally {
      setIsDiscarding(false);
    }
  };
  const confirmPublish = async (layoutId: number, value: PublishValue) => {
    if (!layoutId || isPublishing) return;

    try {
      setIsPublishing(true);

      await publishLayout(layoutId, {
        publishNow: value.type === 'now' ? 1 : 0,
        publishDate: value.type === 'scheduled' ? value.date.toISOString() : undefined,
      });

      notify.success(t('Layout published successfully'));

      setRowSelection({});
      handleRefresh();
      closeModal();
    } catch (error) {
      console.error(error);
      const apiErr = error as { response?: { data?: { message?: string } } };
      const message = apiErr.response?.data?.message ?? t('Failed to publish layout');
      notify.error(message);
    } finally {
      setIsPublishing(false);
    }
  };

  const handleConfirmAssign = async (campaignId: number, layoutId: number | null) => {
    if (!layoutId || isAssigning) return;

    try {
      setIsAssigning(true);

      await assignLayoutToCampaign(campaignId, layoutId);

      notify.success(t('Layout assigned to campaign'));

      setRowSelection({});
      handleRefresh();
      closeModal();
    } catch (error) {
      console.error(error);
      notify.error(t('Failed to assign layout'));
    } finally {
      setIsAssigning(false);
    }
  };

  const handleExportLayout = async (
    layoutId: number,
    options: {
      includeData: boolean;
      includeFallback: boolean;
      fileName: string;
    },
  ) => {
    if (!layoutId || isExporting) return;

    try {
      setIsExporting(true);

      const blob = await exportLayout(layoutId, {
        includeData: options.includeData,
        includeFallback: options.includeFallback,
      });

      const url = window.URL.createObjectURL(blob);

      const a = document.createElement('a');
      a.href = url;
      a.download = `${options.fileName}.zip`;
      a.click();

      window.URL.revokeObjectURL(url);

      const text = await blob.text();
      console.log('TEXT RESPONSE:', text);

      notify.success(t('Layout exported successfully'));
      closeModal();
    } catch (error) {
      console.error(error);
      notify.error(t('Failed to export layout'));
    } finally {
      setIsExporting(false);
    }
  };

  const handleJumpToPlaylists = (layoutId: number) => {
    navigate('/library/playlists', {
      state: { layoutId },
    });
  };

  const handleJumpToCampaigns = (layoutId: number) => {
    navigate('/design/campaign', {
      state: { layoutId },
    });
  };

  const handleJumpToMedia = (layoutId: number) => {
    navigate('/library/media', {
      state: { layoutId },
    });
  };

  return {
    isDeleting,
    deleteError,
    setDeleteError,
    isCloning,
    isPublishing,
    isAssigning,
    confirmDelete,
    handleConfirmClone,
    handleConfirmMove,
    handleCreateLayout,
    handleOpenLayout,
    confirmPublish,
    handleCheckoutLayout,
    isDiscarding,
    handleConfirmDiscard,
    handleConfirmAssign,
    handleJumpToPlaylists,
    handleExportLayout,
    isExporting,
    handleJumpToCampaigns,
    handleJumpToMedia,
  };
}
