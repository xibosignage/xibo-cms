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
import type { TFunction } from 'i18next';
import type { Dispatch, SetStateAction } from 'react';
import { useState } from 'react';

import { notify } from '@/components/ui/Notification';
import { selectFolder } from '@/services/folderApi';
import { clonePlaylist, deletePlaylist } from '@/services/playlistApi';
import type { Playlist } from '@/types/playlist';

interface UsePlaylistActionsProps {
  t: TFunction;
  handleRefresh: () => void;
  closeModal: () => void;
  setRowSelection: Dispatch<SetStateAction<RowSelectionState>>;
  setItemsToMove: (items: Playlist[]) => void;
}

export function usePlaylistActions({
  t,
  handleRefresh,
  closeModal,
  setRowSelection,
  setItemsToMove,
}: UsePlaylistActionsProps) {
  const [isDeleting, setIsDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const [isCloning, setIsCloning] = useState(false);

  const confirmDelete = async (itemsToDelete: Playlist[]) => {
    if (itemsToDelete.length === 0 || isDeleting) {
      return;
    }

    try {
      setIsDeleting(true);
      const results = await Promise.allSettled(
        itemsToDelete.map((item) => deletePlaylist(item.playlistId)),
      );

      const failed = results.filter((r) => r.status === 'rejected');
      if (failed.length > 0) {
        setDeleteError(`${failed.length} item(s) could not be deleted because they are in use.`);
      }

      setRowSelection({});
      handleRefresh();
      closeModal();
    } catch (error) {
      console.error(error);
      setDeleteError('Some selected items are in use and cannot be deleted.');
    } finally {
      setIsDeleting(false);
    }
  };

  const handleConfirmClone = async (
    selectedPlaylist: Playlist | null,
    newName: string,
    copyMediaFiles: boolean,
  ) => {
    if (!selectedPlaylist) {
      return;
    }

    try {
      setIsCloning(true);
      await clonePlaylist({
        playlistId: selectedPlaylist.playlistId,
        name: newName,
        copyMediaFiles: copyMediaFiles,
      });

      notify.success(t('Playlist copied successfully'));
      handleRefresh();
      closeModal();
    } catch (error) {
      console.error('Copy playlist failed', error);
      notify.error(t('Failed to copy playlist'));
    } finally {
      setIsCloning(false);
    }
  };

  const handleConfirmMove = async (itemsToMove: Playlist[], newFolderId: number) => {
    if (!itemsToMove || itemsToMove.length === 0) {
      return;
    }

    const movePromises = itemsToMove.map((item) =>
      selectFolder({
        folderId: newFolderId,
        targetId: item.playlistId,
        targetType: 'playlist',
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
