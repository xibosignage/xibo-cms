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

import type { ColumnDef, PaginationState, SortingState } from '@tanstack/react-table';
import { useEffect, useState, useTransition } from 'react';
import { useTranslation } from 'react-i18next';

import Modal from '../../../../components/ui/modals/Modal';

import Checkbox from '@/components/ui/forms/Checkbox';
import NumberInput from '@/components/ui/forms/NumberInput';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import SelectFolder from '@/components/ui/forms/SelectFolder';
import TagInput from '@/components/ui/forms/TagInput';
import TextInput from '@/components/ui/forms/TextInput';
import { DataTable } from '@/components/ui/table/DataTable';
import { StatusCell, TagsCell, TextCell } from '@/components/ui/table/cells';
import { getCommonFormOptions } from '@/config/commonForms';
import { useDebounce } from '@/hooks/useDebounce';
import type { MediaFilterInput } from '@/pages/Library/Media/MediaConfig';
import {
  getStatusTypeFromMediaType,
  INITIAL_FILTER_STATE,
} from '@/pages/Library/Media/MediaConfig';
import { useMediaData } from '@/pages/Library/Media/hooks/useMediaData';
import { updatePlaylist, createPlaylist } from '@/services/playlistApi';
import type { Media } from '@/types/media';
import type { Playlist } from '@/types/playlist';
import type { Tag } from '@/types/tag';
import { formatDuration } from '@/utils/formatters';

interface AddAndEditPlaylistModalProps {
  type: 'add' | 'edit';
  openModal: boolean;
  data?: Playlist | null;
  onClose: () => void;
  onSave: (updated: Playlist) => void;
}

interface PlaylistDraft {
  name: string;
  folderId: number | null;
  tags: Tag[];
  enableStat: string;
  isDynamic: boolean;
  filterMediaName: string;
  logicalOperatorName: 'OR' | 'AND';
  filterMediaTag: Tag[];
  exactTags: boolean;
  logicalOperator: 'OR' | 'AND';
  filterFolderId: number | null;
  maxNumberOfItems: number;
}

const DEFAULT_DRAFT: PlaylistDraft = {
  name: '',
  folderId: null,
  tags: [],
  enableStat: 'off',
  isDynamic: false,
  filterMediaName: '',
  logicalOperatorName: 'OR',
  filterMediaTag: [],
  exactTags: false,
  logicalOperator: 'OR',
  filterFolderId: null,
  maxNumberOfItems: 0,
};

export default function AddAndEditPlaylistModal({
  type,
  openModal,
  onClose,
  data,
  onSave,
}: AddAndEditPlaylistModalProps) {
  const { t } = useTranslation();
  const [openSelect, setOpenSelect] = useState<string | null>(null);
  const [isPending, startTransition] = useTransition();
  const [error, setError] = useState<string | undefined>();

  const [draft, setDraft] = useState<PlaylistDraft>(() => {
    if (type === 'edit' && data) {
      return {
        ...DEFAULT_DRAFT,
        name: data.name,
        folderId: data.folderId ?? null,
        tags: data.tags.map((t) => ({ ...t })),
        enableStat: data.enableStat,
        isDynamic: data.isDynamic,
        filterMediaName: data.filterMediaName || '',
        logicalOperatorName: data.logicalOperatorName || 'OR',
        filterMediaTag: data.filterMediaTag ? data.filterMediaTag.map((t) => ({ ...t })) : [],
        exactTags: data.exactTags || false,
        logicalOperator: data.logicalOperator || 'OR',
        filterFolderId: data.filterFolderId ?? null,
        maxNumberOfItems: data.maxNumberOfItems || 0,
      };
    }
    return { ...DEFAULT_DRAFT };
  });

  const [previewPagination, setPreviewPagination] = useState<PaginationState>({
    pageIndex: 0,
    pageSize: 5,
  });
  const [previewSorting, setPreviewSorting] = useState<SortingState>([]);

  const debouncedFilterMediaName = useDebounce(draft.filterMediaName, 500);

  const { data: previewQueryData, isFetching: isFetchingPreview } = useMediaData({
    pagination: previewPagination,
    sorting: previewSorting,
    filter: '',
    folderId: draft.filterFolderId,
    advancedFilters: {
      ...INITIAL_FILTER_STATE,
      media: debouncedFilterMediaName,
      tags: draft.filterMediaTag.map((t) => t.tag).join(','),
      exactTags: draft.exactTags,
      logicalOperator: draft.logicalOperator,
      logicalOperatorName: draft.logicalOperatorName,
    } as MediaFilterInput,
  });

  const previewData = previewQueryData?.rows ?? [];
  const previewPageCount = Math.ceil(
    (previewQueryData?.totalCount || 0) / previewPagination.pageSize,
  );

  useEffect(() => {
    if (type === 'edit' && data) {
      setDraft({
        ...DEFAULT_DRAFT,
        name: data.name,
        folderId: data.folderId ?? null,
        tags: data.tags.map((t) => ({ ...t })),
        enableStat: data.enableStat,
        isDynamic: data.isDynamic,
        filterMediaName: data.filterMediaName || '',
        logicalOperatorName: data.logicalOperatorName || 'OR',
        filterMediaTag: data.filterMediaTag ? data.filterMediaTag.map((t) => ({ ...t })) : [],
        exactTags: data.exactTags || false,
        logicalOperator: data.logicalOperator || 'OR',
        filterFolderId: data.filterFolderId || null,
        maxNumberOfItems: data.maxNumberOfItems || 0,
      });
    } else {
      setDraft({ ...DEFAULT_DRAFT });
    }

    setError(undefined);
  }, [data, type]);

  const handleSave = () => {
    startTransition(async () => {
      try {
        const serializedTags = draft.tags.map((t) =>
          t.value != null ? `${t.tag}|${t.value}` : t.tag,
        );

        const payload = {
          name: draft.name,
          isDynamic: draft.isDynamic,
          tags: serializedTags.join(','),
          enableStat: draft.enableStat,
          folderId: draft.folderId,

          ...(draft.isDynamic && {
            filterMediaName: draft.filterMediaName,
            logicalOperatorName: draft.logicalOperatorName,
            filterMediaTag: draft.filterMediaTag.map((t) => t.tag).join(','),
            exactTags: draft.exactTags,
            logicalOperator: draft.logicalOperator,
            filterFolderId: draft.filterFolderId,
            maxNumberOfItems: draft.maxNumberOfItems,
          }),
        };

        if (type === 'edit') {
          if (!data) {
            console.error('Playlist data is missing.');
            return;
          }

          const updatedPlaylist = await updatePlaylist(data.playlistId, payload);

          onSave({
            ...data,
            ...updatedPlaylist,
          });
        } else {
          const newPlaylist = await createPlaylist(payload);
          onSave(newPlaylist);
        }

        onClose();
      } catch (err: unknown) {
        console.error('Failed to save playlist:', err);

        const apiError = err as { response?: { data?: { message?: string } } };

        if (apiError.response?.data?.message) {
          setError(apiError.response.data.message);
        } else if (err instanceof Error) {
          setError(err.message);
        } else {
          setError(t('An unexpected error occurred while saving the playlist.'));
        }
      }
    });
  };

  const hasActiveDynamicFilters = Boolean(
    draft.filterFolderId !== null ||
    draft.filterMediaName.trim() !== '' ||
    draft.filterMediaTag.length > 0,
  );

  const previewColumns = [
    {
      accessorKey: 'mediaId',
      header: t('ID'),
      size: 70,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },
    {
      accessorKey: 'name',
      header: t('Name'),
      size: 140,
      enableHiding: false,
      cell: (info) => <TextCell weight="bold">{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'mediaType',
      header: t('Type'),
      size: 100,
      cell: (info) => {
        const value = info.getValue() as string;
        return <StatusCell label={value} type={getStatusTypeFromMediaType(value)} />;
      },
    },
    {
      accessorKey: 'tags',
      header: t('Tags'),
      enableSorting: false,
      size: 120,
      cell: (info) => {
        const tags = info.getValue<Tag[]>() || [];
        const formattedTags = tags.map((tag) => ({
          id: tag.tagId,
          label: tag.tag,
        }));
        return <TagsCell tags={formattedTags} noTagsPlaceholder="-" />;
      },
    },
    {
      id: 'formattedDuration',
      accessorKey: 'duration',
      header: t('Duration'),
      size: 120,
      cell: (info) => <TextCell>{formatDuration(info.getValue<number>())}</TextCell>,
    },
  ] as ColumnDef<Media>[];

  const modalTitle = type === 'add' ? t('Add Playlist') : t('Edit Playlist');

  return (
    <Modal
      title={modalTitle}
      onClose={onClose}
      isOpen={openModal}
      isPending={isPending}
      scrollable={false}
      error={error}
      actions={[
        {
          label: t('Cancel'),
          onClick: onClose,
          variant: 'secondary',
          disabled: isPending,
        },
        {
          label: isPending ? t('Saving…') : t('Save'),
          onClick: handleSave,
          disabled: isPending,
        },
      ]}
    >
      <div className="flex flex-col h-full overflow-y-hidden overflow-x-visible gap-3 px-4">
        <div className="flex flex-col gap-3 flex-1 min-h-0 p-4 overflow-y-auto">
          {/* Select Folder */}
          <div className="relative z-20">
            <SelectFolder
              selectedId={draft.folderId}
              onSelect={(folder) => {
                setDraft((prev) => ({
                  ...prev,
                  folderId: folder ? folder.id : null,
                }));
              }}
            />
          </div>

          {/* Name */}
          <div className="flex flex-col">
            <label htmlFor="name" className="text-xs font-semibold text-gray-500 leading-5">
              {t('Name')}
            </label>
            <input
              id="name"
              className="border-gray-200 text-sm rounded-lg"
              name="name"
              value={draft.name}
              onChange={(e) => setDraft((prev) => ({ ...prev, name: e.target.value }))}
            />
          </div>

          {/* Tags */}
          <TagInput
            value={draft.tags}
            helpText={t('Tags (Comma-separated: Tag or Tag|Value)')}
            onChange={(tags) => setDraft((prev) => ({ ...prev, tags }))}
          />

          {/* Enable Stats */}
          <SelectDropdown
            label="Enable Playlist Stats Collection?"
            value={draft.enableStat}
            placeholder="Inherit"
            options={getCommonFormOptions(t).inherit}
            isOpen={openSelect === 'enableStat'}
            onToggle={() => setOpenSelect((prev) => (prev === 'enableStat' ? null : 'enableStat'))}
            onSelect={(value) => {
              setDraft((prev) => ({ ...prev, enableStat: value }));
              setOpenSelect(null);
            }}
            helper={t(
              `Enable the collection of Proof of Play statistics for this Playlist Item. Ensure that 'Enable Stats Collection' is set to 'On' in the Display Settings.`,
            )}
          />

          {/* Dynamic */}
          <div className="p-3 flex flex-col gap-3 bg-slate-50">
            <Checkbox
              id="isDynamic"
              className="px-3 py-2.5 gap-1 items-start"
              title={t('Dynamic Playlist')}
              label={t(`Use filters to automatically manage media assignments for this playlist.`)}
              checked={draft.isDynamic}
              classNameLabel="text-xs"
              onChange={() => setDraft((prev) => ({ ...prev, isDynamic: !prev.isDynamic }))}
            />

            {!!draft.isDynamic && (
              <>
                <div className="relative z-20">
                  <SelectFolder
                    selectedId={draft.filterFolderId}
                    onSelect={(folder) => {
                      setDraft((prev) => ({ ...prev, filterFolderId: folder ? folder.id : null }));
                    }}
                  />
                </div>

                <TextInput
                  name="filterMediaName"
                  label={t('Name Filter')}
                  placeholder={t('Enter Name Filter ')}
                  value={draft.filterMediaName}
                  onChange={(val) => setDraft((prev) => ({ ...prev, filterMediaName: val }))}
                  suffix={
                    <select
                      className="bg-transparent text-sm font-semibold items-center justify-center text-gray-500 border-none focus:ring-0 cursor-pointer p-3 pr-8"
                      value={draft.logicalOperatorName}
                      onChange={(e) =>
                        setDraft((prev) => ({
                          ...prev,
                          logicalOperatorName: e.target.value as 'OR' | 'AND',
                        }))
                      }
                    >
                      <option value="AND">AND</option>
                      <option value="OR">OR</option>
                    </select>
                  }
                />

                <TagInput
                  label={t('Tag Filter')}
                  placeholder={t('Enter Tag Filter')}
                  value={draft.filterMediaTag}
                  onChange={(val) => setDraft((prev) => ({ ...prev, filterMediaTag: val }))}
                  suffix={
                    <div className="flex items-stretch">
                      <label className="flex items-center gap-1.5 px-3 text-sm text-gray-500 border-gray-200 cursor-pointer border-e">
                        <input
                          type="checkbox"
                          title={t('Exact')}
                          className="shrink-0 mt-0.5 border-gray-200 rounded text-blue-600 focus:ring-blue-500"
                          checked={draft.exactTags}
                          onChange={(e) =>
                            setDraft((prev) => ({ ...prev, exactTags: e.target.checked }))
                          }
                        />
                      </label>
                      <select
                        className="bg-transparent text-sm font-semibold items-center justify-center text-gray-500 border-none focus:ring-0 cursor-pointer p-3 pr-8"
                        value={draft.logicalOperator}
                        onChange={(e) =>
                          setDraft((prev) => ({
                            ...prev,
                            logicalOperator: e.target.value as 'OR' | 'AND',
                          }))
                        }
                      >
                        <option value="AND">AND</option>
                        <option value="OR">OR</option>
                      </select>
                    </div>
                  }
                />

                <NumberInput
                  name="maxNumberOfItems"
                  label={t('Max number of Items')}
                  helpText={t(
                    'The max number of Media items that can be dynamically assigned to this Playlist.',
                  )}
                  value={draft.maxNumberOfItems}
                  onChange={(num) => setDraft((prev) => ({ ...prev, maxNumberOfItems: num }))}
                />

                {hasActiveDynamicFilters && (
                  <div className="flex flex-col gap-3 animate-in fade-in slide-in-from-top-2 duration-300">
                    <div className="flex flex-col overflow-hidden">
                      <DataTable
                        columns={previewColumns}
                        data={previewData}
                        pageCount={previewPageCount}
                        pagination={previewPagination}
                        onPaginationChange={setPreviewPagination}
                        sorting={previewSorting}
                        onSortingChange={setPreviewSorting}
                        globalFilter=""
                        onGlobalFilterChange={() => {}}
                        rowSelection={{}}
                        onRowSelectionChange={() => {}}
                        loading={isFetchingPreview}
                        enableSelection={false}
                        hideToolbar={true}
                      />
                    </div>
                  </div>
                )}
              </>
            )}
          </div>
        </div>
      </div>
    </Modal>
  );
}
