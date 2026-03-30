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

import { useTranslation } from 'react-i18next';

import AddAndEditPlaylistModal from './AddAndEditPlaylistModal';
import CopyPlaylistModal from './CopyPlaylistModal';
import DeletePlaylistModal from './DeletePlaylistModal';

import FolderActionModals from '@/components/ui/FolderActionModals';
import MoveModal from '@/components/ui/modals/MoveModal';
import ShareModal from '@/components/ui/modals/ShareModal';
import type { useFolderActions } from '@/hooks/useFolderActions';
import type { Playlist } from '@/types/playlist';

interface PlaylistModalsProps {
  actions: {
    activeModal: string | null;
    closeModal: () => void;
    handleRefresh: () => void;
    setPlaylistList: React.Dispatch<React.SetStateAction<Playlist[]>>;
    deleteError: string | null;
    isDeleting: boolean;
    isCloning: boolean;
  };
  selection: {
    selectedPlaylist: Playlist | null;
    selectedPlaylistId: number | null;
    itemsToDelete: Playlist[];
    itemsToMove: Playlist[];
    existingNames: string[];
    shareEntityIds: number | number[] | null;
    setShareEntityIds: React.Dispatch<React.SetStateAction<number | number[] | null>>;
  };
  handlers: {
    confirmDelete: (items: Playlist[]) => void;
    handleConfirmClone: (name: string, copyMediaFiles: boolean) => void;
    handleConfirmMove: (folderId: number) => void;
  };
  folderActions: ReturnType<typeof useFolderActions>;
}

export function PlaylistModals({
  actions,
  selection,
  handlers,
  folderActions,
}: PlaylistModalsProps) {
  const { t } = useTranslation();
  const isModalOpen = (name: string) => actions.activeModal === name;

  return (
    <>
      {isModalOpen('edit') && (
        <AddAndEditPlaylistModal
          type={selection.selectedPlaylistId ? 'edit' : 'add'}
          onClose={() => {
            actions.closeModal();
          }}
          data={selection.selectedPlaylist}
          onSave={(savedPlaylist) => {
            if (selection.selectedPlaylistId) {
              actions.setPlaylistList((prev) =>
                prev.map((m) => (m.playlistId === savedPlaylist.playlistId ? savedPlaylist : m)),
              );
            } else {
              actions.setPlaylistList((prev) => [savedPlaylist, ...prev]);
            }
            actions.handleRefresh();
          }}
        />
      )}

      {isModalOpen('share') && (
        <ShareModal
          title={t('Share Playlist')}
          onClose={() => {
            actions.closeModal();
            selection.setShareEntityIds(null);
            actions.handleRefresh();
          }}
          entityType="playlist"
          entityId={selection.shareEntityIds ?? (selection.selectedPlaylist?.playlistId || null)}
        />
      )}

      <FolderActionModals folderActions={folderActions} />

      {isModalOpen('delete') && (
        <DeletePlaylistModal
          onClose={actions.closeModal}
          onDelete={() => handlers.confirmDelete(selection.itemsToDelete)}
          itemCount={selection.itemsToDelete.length}
          playlistName={
            selection.itemsToDelete.length === 1 ? selection.itemsToDelete[0]?.name : undefined
          }
          error={actions.deleteError}
          isLoading={actions.isDeleting}
        />
      )}

      {isModalOpen('copy') && (
        <CopyPlaylistModal
          onClose={actions.closeModal}
          onConfirm={(name, copyMedia) => handlers.handleConfirmClone(name, copyMedia)}
          playlist={selection.selectedPlaylist}
          isLoading={actions.isCloning}
          existingNames={selection.existingNames}
        />
      )}

      {isModalOpen('move') && (
        <MoveModal
          onClose={actions.closeModal}
          onConfirm={handlers.handleConfirmMove}
          items={selection.itemsToMove}
          entityLabel={t('Playlist')}
        />
      )}
    </>
  );
}
