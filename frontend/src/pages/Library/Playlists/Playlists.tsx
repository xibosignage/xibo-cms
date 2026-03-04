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

import { useQueryClient } from '@tanstack/react-query';
import {
  type SortingState,
  type PaginationState,
  type RowSelectionState,
} from '@tanstack/react-table';
import { Search, Filter, FilterX, Plus } from 'lucide-react';
import { useEffect, useState, useRef } from 'react';
import { useTranslation } from 'react-i18next';

import ShareModal from '../../../components/ui/modals/ShareModal';

import type { ModalType } from './PlaylistsConfig';
import {
  getPlaylistColumns,
  getBulkActions,
  INITIAL_FILTER_STATE,
  type PlaylistFilterInput,
} from './PlaylistsConfig';
import AddAndEditPlaylistModal from './components/AddAndEditPlaylistModal';
import CopyPlaylistModal from './components/CopyPlaylistModal';
import DeletePlaylistModal from './components/DeletePlaylistModal';
import { usePlaylistData } from './hooks/usePlaylistData';
import { usePlaylistFilterOptions } from './hooks/usePlaylistFilterOptions';

import Button from '@/components/ui/Button';
import FilterInputs from '@/components/ui/FilterInputs';
import FolderActionModals from '@/components/ui/FolderActionModals';
import FolderBreadcrumb from '@/components/ui/FolderBreadCrumb';
import FolderSidebar from '@/components/ui/FolderSidebar';
import { notify } from '@/components/ui/Notification';
import TabNav from '@/components/ui/TabNav';
import MoveModal from '@/components/ui/modals/MoveModal';
import { DataTable } from '@/components/ui/table/DataTable';
import { useDebounce } from '@/hooks/useDebounce';
import { useFilteredTabs } from '@/hooks/useFilteredTabs';
import { useFolderActions } from '@/hooks/useFolderActions';
import { fetchContextButtons, selectFolder } from '@/services/folderApi';
import { clonePlaylist, deletePlaylist } from '@/services/playlistApi';
import type { Playlist } from '@/types/playlist';

export default function Playlist() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();

  const [pagination, setPagination] = useState<PaginationState>({
    pageIndex: 0,
    pageSize: 10,
  });

  const [folderRefreshTrigger, setFolderRefreshTrigger] = useState(0);
  const [sorting, setSorting] = useState<SortingState>([]);
  const [globalFilter, setGlobalFilter] = useState('');
  const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
  const [selectionCache, setSelectionCache] = useState<Record<string, Playlist>>({});
  const [openFilter, setOpenFilter] = useState(false);
  const [filterInputs, setFilterInput] = useState<PlaylistFilterInput>(INITIAL_FILTER_STATE);

  const [selectedFolderId, setSelectedFolderId] = useState<number | null>(1);
  const [canAddToFolder, setCanAddToFolder] = useState(false);

  const [showFolderSidebar, setShowFolderSidebar] = useState(false);
  const [activeModal, setActiveModal] = useState<ModalType | null>(null);

  const [itemsToDelete, setItemsToDelete] = useState<Playlist[]>([]);
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const [isDeleting, setIsDeleting] = useState(false);
  const [isCloning, setIsCloning] = useState(false);
  const [itemsToMove, setItemsToMove] = useState<Playlist[]>([]);

  const [shareEntityIds, setShareEntityIds] = useState<number | number[] | null>(null);

  const [selectedPlaylistId, setSelectedPlaylistId] = useState<number | null>(null);

  const debouncedFilter = useDebounce(globalFilter, 500);

  const openModal = (name: ModalType) => setActiveModal(name);
  const closeModal = () => setActiveModal(null);
  const isModalOpen = (name: ModalType) => activeModal === name;

  // Data fetching
  const {
    data: queryData,
    isFetching,
    isError,
    error: queryError,
  } = usePlaylistData({
    pagination,
    sorting,
    filter: debouncedFilter,
    advancedFilters: filterInputs,
    folderId: selectedFolderId,
  });

  // Computed values
  const data = queryData?.rows;
  const pageCount = Math.ceil((queryData?.totalCount || 0) / pagination.pageSize);
  const error = isError && queryError instanceof Error ? queryError.message : '';

  const [playlistList, setPlaylistList] = useState<Playlist[]>([]);

  const folderActions = useFolderActions({
    onSuccess: (targetFolder) => {
      setFolderRefreshTrigger((prev) => prev + 1);

      if (targetFolder) {
        handleFolderChange({ id: targetFolder.id, text: targetFolder.text });
      } else {
        handleRefresh();
      }
    },
  });

  useEffect(() => {
    setPlaylistList(data ?? []);
  }, [data]);

  // Check selected folder permission to add playlist
  useEffect(() => {
    if (selectedFolderId === null) {
      setCanAddToFolder(false);
      return;
    }

    let active = true;

    fetchContextButtons(selectedFolderId)
      .then((perms) => {
        if (active) {
          setCanAddToFolder(perms.create || false);
        }
      })
      .catch((err) => {
        console.error('Failed to fetch folder permissions', err);
        if (active) {
          setCanAddToFolder(false);
        }
      });

    return () => {
      active = false;
    };
  }, [selectedFolderId]);

  // Update the selected cache when data loads or selection changes
  useEffect(() => {
    if (!playlistList || playlistList.length === 0) {
      return;
    }

    setSelectionCache((prev) => {
      const next = { ...prev };
      let hasChanges = false;

      playlistList.forEach((item) => {
        const id = item.playlistId.toString();
        if (rowSelection[id]) {
          if (!next[id]) {
            next[id] = item;
            hasChanges = true;
          }
        }
      });

      return hasChanges ? next : prev;
    });
  }, [playlistList, rowSelection]);

  const uploadStateRef = useRef({ selectedFolderId, canCreate: canAddToFolder });
  useEffect(() => {
    uploadStateRef.current = { selectedFolderId, canCreate: canAddToFolder };
  }, [selectedFolderId, canAddToFolder]);

  const selectedPlaylist = playlistList.find((m) => m.playlistId === selectedPlaylistId) ?? null;
  const existingNames = playlistList.map((m) => m.name);

  const getRowId = (row: Playlist) => {
    return row.playlistId.toString();
  };

  // Event handlers
  const handleRefresh = () => {
    queryClient.invalidateQueries({ queryKey: ['playlist'] });
  };

  const handleFolderChange = (folder: { id: number | null; text: string | '' }) => {
    setSelectedFolderId(folder.id);
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
    setRowSelection({});
  };

  const handleDelete = (id: number) => {
    const playlist = playlistList.find((m) => m.playlistId === id);
    if (!playlist) return;

    setItemsToDelete([playlist]);
    setDeleteError(null);
    openModal('delete');
  };

  const confirmDelete = async () => {
    if (itemsToDelete.length === 0 || isDeleting) return;

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

  const openAddEditModal = (playlist: Playlist | null) => {
    if (playlist) {
      setSelectedPlaylistId(playlist.playlistId);
      openModal('edit');
    } else {
      setSelectedPlaylistId(null);
      openModal('edit');
    }
  };

  const closeAddEditModal = () => {
    closeModal();
    setSelectedPlaylistId(null);
  };

  const handleResetFilters = () => {
    setFilterInput(INITIAL_FILTER_STATE);
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
  };

  const handleConfirmClone = async (newName: string, copyMediaFiles: boolean) => {
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

  const handleConfirmMove = async (newFolderId: number) => {
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
        // All Success
        notify.info(t('{{count}} items moved successfully!', { count: itemsToMove.length }));
        setItemsToMove([]);
        handleRefresh();
        closeModal();
      } else {
        // Some failed
        console.error('Failed to move some items:', failures);

        if (failures.length === itemsToMove.length) {
          notify.error(t('Failed to move items.'));
        } else {
          notify.warning(
            t('Moved {{success}} items, but {{fail}} failed.', {
              success: itemsToMove.length - failures.length,
              fail: failures.length,
            }),
          );
          setItemsToMove([]);
          handleRefresh();
          closeModal();
        }
      }
    } catch (error) {
      console.error(error);
      notify.error(t('An unexpected error occurred while moving items.'));
    }
  };

  const openCopyModal = (playlistId: number) => {
    setSelectedPlaylistId(playlistId);
    openModal('copy');
  };

  const columns = getPlaylistColumns({
    t,
    onDelete: handleDelete,
    openAddEditModal,
    openMoveModal: (playlist) => {
      setItemsToMove([playlist] as Playlist[]);
      openModal('move');
    },
    openShareModal: (playlistId) => {
      setShareEntityIds(playlistId);
      openModal('share');
    },
    copyPlaylist: openCopyModal,
  });

  const getAllSelectedItems = (): Playlist[] => {
    return Object.keys(rowSelection)
      .map((id) => selectionCache[id])
      .filter((item): item is Playlist => !!item); // Filter out any missing data
  };

  const bulkActions = getBulkActions({
    t,
    onDelete: () => {
      const allItems = getAllSelectedItems();
      setItemsToDelete(allItems);
      setDeleteError(null);
      openModal('delete');
    },
    onMove: () => {
      const allItems = getAllSelectedItems();
      setItemsToMove(allItems);
      openModal('move');
    },
    onShare: () => {
      const allItems = getAllSelectedItems();
      const ids = allItems.map((i) => i.playlistId);
      setShareEntityIds(ids);
      openModal('share');
    },
  });

  const { filterOptions } = usePlaylistFilterOptions();

  const libraryTabs = useFilteredTabs('library');

  return (
    <section className="flex h-full w-full min-h-0 relative outline-none overflow-hidden">
      <FolderSidebar
        isOpen={showFolderSidebar}
        selectedFolderId={selectedFolderId}
        onSelect={handleFolderChange}
        onClose={() => setShowFolderSidebar(false)}
        onAction={folderActions.openAction}
        refreshTrigger={folderRefreshTrigger}
      />
      <div className="flex-1 flex flex-col min-h-0 min-w-0 px-5 pb-5">
        <div className="flex flex-row justify-between py-4 items-center gap-4">
          <TabNav activeTab="Playlists" navigation={libraryTabs} />
          <div className="flex items-center gap-2 md:mb-0">
            <Button
              variant="primary"
              className="font-semibold"
              onClick={() => openAddEditModal(null)}
              leftIcon={Plus}
            >
              {t('New Playlist')}
            </Button>
          </div>
        </div>

        <div className="flex flex-col lg:flex-row justify-between items-center gap-4">
          <div className="w-full lg:flex-1 md:min-w-0">
            <FolderBreadcrumb
              currentFolderId={selectedFolderId}
              onNavigate={handleFolderChange}
              isSidebarOpen={showFolderSidebar}
              onToggleSidebar={() => setShowFolderSidebar(!showFolderSidebar)}
              onAction={folderActions.openAction}
              refreshTrigger={folderRefreshTrigger}
            />
          </div>

          <div className="flex items-center gap-2 w-full xl:w-115 lg:w-75 shrink-0">
            <div className="relative flex-1 flex">
              <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                <Search className="w-4 h-4 text-gray-400" />
              </div>
              <input
                name="search"
                value={globalFilter}
                onChange={(e) => {
                  setGlobalFilter(e.target.value);
                  setPagination((prev) => ({ ...prev, pageIndex: 0 }));
                }}
                placeholder={t('Search playlist...')}
                className="py-2 px-3 pl-10 block h-11.25 bg-gray-100 rounded-lg w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none"
              />
            </div>
            <Button
              leftIcon={!openFilter ? Filter : FilterX}
              variant="secondary"
              onClick={() => setOpenFilter((prev) => !prev)}
              removeTextOnMobile
            >
              {t('Filters')}
            </Button>
          </div>
        </div>

        <FilterInputs
          onChange={(name, value) => {
            setFilterInput((prev) => ({ ...prev, [name]: value }));
            setPagination((prev) => ({ ...prev, pageIndex: 0 }));
          }}
          open={openFilter}
          values={filterInputs}
          options={filterOptions}
          onReset={handleResetFilters}
        />

        {error && (
          <div className="bg-red-50 border border-red-200 text-red-800 p-4" role="alert">
            {error}
          </div>
        )}

        <div className="min-h-0 flex flex-col">
          <DataTable
            columns={columns}
            data={playlistList}
            pageCount={pageCount}
            pagination={pagination}
            onPaginationChange={setPagination}
            sorting={sorting}
            onSortingChange={setSorting}
            globalFilter={globalFilter}
            onGlobalFilterChange={setGlobalFilter}
            loading={isFetching}
            rowSelection={rowSelection}
            onRowSelectionChange={setRowSelection}
            onRefresh={handleRefresh}
            columnPinning={{
              left: ['tableSelection'],
              right: ['tableActions'],
            }}
            initialState={{
              columnVisibility: {
                playlistId: true,
                name: true,
                tags: true,
                duration: true,
                isDynamic: false,
                createdDt: false,
                modifiedDt: true,
                enableStat: true,
                groupsWithPermissions: false,
                revised: false,
                released: false,
                ownerId: true,
              },
            }}
            bulkActions={bulkActions}
            viewMode={null}
            getRowId={getRowId}
          />
        </div>
      </div>

      {isModalOpen('edit') && (
        <AddAndEditPlaylistModal
          type={selectedPlaylistId ? 'edit' : 'add'}
          openModal={isModalOpen('edit')}
          onClose={closeAddEditModal}
          data={selectedPlaylist}
          onSave={(savedPlaylist) => {
            if (selectedPlaylistId) {
              // Update existing item in local state
              setPlaylistList((prev) =>
                prev.map((m) => (m.playlistId === savedPlaylist.playlistId ? savedPlaylist : m)),
              );
            } else {
              setPlaylistList((prev) => [savedPlaylist, ...prev]);
            }
            handleRefresh();
          }}
        />
      )}

      <ShareModal
        title={t('Share Playlist')}
        onClose={() => {
          closeModal();
          setShareEntityIds(null);
          handleRefresh();
        }}
        openModal={isModalOpen('share')}
        entityType="playlist"
        entityId={shareEntityIds ?? (selectedPlaylist?.playlistId || null)}
      />

      <FolderActionModals folderActions={folderActions} />

      <DeletePlaylistModal
        isOpen={isModalOpen('delete')}
        onClose={closeModal}
        onDelete={confirmDelete}
        itemCount={itemsToDelete.length}
        playlistName={itemsToDelete.length === 1 ? itemsToDelete[0]?.name : undefined}
        error={deleteError}
        isLoading={isDeleting}
      />

      <CopyPlaylistModal
        isOpen={isModalOpen('copy')}
        onClose={closeModal}
        onConfirm={handleConfirmClone}
        playlist={selectedPlaylist}
        isLoading={isCloning}
        existingNames={existingNames}
      />

      <MoveModal
        isOpen={isModalOpen('move')}
        onClose={closeModal}
        onConfirm={handleConfirmMove}
        items={itemsToMove}
        entityLabel={t('Playlist')}
      />
    </section>
  );
}
