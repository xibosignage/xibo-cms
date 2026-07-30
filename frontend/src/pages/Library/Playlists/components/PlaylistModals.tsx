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
import EnableStatsPlaylistModal from './EnableStatsPlaylistModal';

import FolderActionModals from '@/components/ui/FolderActionModals';
import EditTagsMultipleModal from '@/components/ui/modals/EditTagsMultipleModal';
import EnableStatsMultipleModal from '@/components/ui/modals/EnableStatsMultipleModal';
import MoveModal from '@/components/ui/modals/MoveModal';
import ScheduleEventModal from '@/components/ui/modals/ScheduleEventModal';
import ShareModal from '@/components/ui/modals/ShareModal';
import UsageReportModal from '@/components/ui/modals/UsageReportModal';
import type { useFolderActions } from '@/hooks/useFolderActions';
import { setPlaylistEnableStat } from '@/services/playlistApi';
import { EventTypeId } from '@/types/event';
import type { Playlist } from '@/types/playlist';
import { mergeEntityTags } from '@/utils/tags';

interface PlaylistModalsProps {
  actions: {
    activeModal: string | null;
    closeModal: () => void;
    handleRefresh: () => void;
    deleteError: string | null;
    isDeleting: boolean;
    isCloning: boolean;
    isUpdatingStats: boolean;
  };
  selection: {
    selectedPlaylist: Playlist | null;
    selectedPlaylistId: number | null;
    defaultFolderId?: number;
    itemsToDelete: Playlist[];
    itemsToMove: Playlist[];
    bulkItems: Playlist[];
    existingNames: string[];
    shareEntityIds: number | number[] | null;
    setShareEntityIds: React.Dispatch<React.SetStateAction<number | number[] | null>>;
  };
  handlers: {
    confirmDelete: (items: Playlist[]) => void;
    handleConfirmClone: (name: string, copyMediaFiles: boolean) => void;
    handleConfirmMove: (folderId: number) => void;
    handleConfirmEnableStats: (value: string) => void;
  };
  openTimeline: (playlistId: number) => void;
  folderActions: ReturnType<typeof useFolderActions>;
}

export function PlaylistModals({
  actions,
  selection,
  handlers,
  openTimeline,
  folderActions,
}: PlaylistModalsProps) {
  const { t } = useTranslation();
  const isModalOpen = (name: string) => actions.activeModal === name;

  return (
    <>
      {isModalOpen('edit') && (
        <AddAndEditPlaylistModal
          type={selection.selectedPlaylistId ? 'edit' : 'add'}
          defaultFolderId={selection.defaultFolderId}
          onClose={() => {
            actions.closeModal();
          }}
          data={selection.selectedPlaylist}
          onSave={(saved) => {
            actions.handleRefresh();
            // After creating a new (non-dynamic) playlist, jump to the Timeline editor
            if (!selection.selectedPlaylistId && !saved.isDynamic) {
              openTimeline(saved.playlistId);
            }
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

      {isModalOpen('editTagsMultiple') && (
        <EditTagsMultipleModal
          targetType="playlist"
          ids={selection.bulkItems.map((item) => item.playlistId)}
          existingTags={mergeEntityTags(selection.bulkItems)}
          onClose={actions.closeModal}
          onSuccess={() => {
            actions.closeModal();
            actions.handleRefresh();
          }}
        />
      )}

      {isModalOpen('enableStatsMultiple') && (
        <EnableStatsMultipleModal
          ids={selection.bulkItems.map((item) => item.playlistId)}
          entityLabel={t('playlists')}
          mode="inherit"
          setEnableStat={(id, enableStat) => setPlaylistEnableStat(id, String(enableStat))}
          onClose={actions.closeModal}
          onSuccess={() => actions.handleRefresh()}
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

      {isModalOpen('schedule') && selection.selectedPlaylist && (
        <ScheduleEventModal
          isOpen
          onClose={() => {
            actions.closeModal();
            actions.handleRefresh();
          }}
          mode="schedule"
          eventTypeId={EventTypeId.Playlist}
          contentId={selection.selectedPlaylist.playlistId}
          contentName={selection.selectedPlaylist.name}
        />
      )}

      {isModalOpen('enableStats') && (
        <EnableStatsPlaylistModal
          playlist={selection.selectedPlaylist}
          isLoading={actions.isUpdatingStats}
          onClose={actions.closeModal}
          onConfirm={handlers.handleConfirmEnableStats}
        />
      )}

      {isModalOpen('usageReport') && selection.selectedPlaylist && (
        <UsageReportModal
          entityType="playlist"
          entityId={selection.selectedPlaylist.playlistId}
          entityName={selection.selectedPlaylist.name}
          onClose={actions.closeModal}
        />
      )}
    </>
  );
}
