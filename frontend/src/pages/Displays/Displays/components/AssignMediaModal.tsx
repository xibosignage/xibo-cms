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

import { keepPreviousData, useQuery } from '@tanstack/react-query';
import type { ColumnDef, PaginationState, SortingState } from '@tanstack/react-table';
import { isAxiosError } from 'axios';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { SearchAssignPanel } from '@/components/ui/SearchAssignPanel';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import Modal from '@/components/ui/modals/Modal';
import { StatusCell, TextCell } from '@/components/ui/table/cells';
import { useDebounce } from '@/hooks/useDebounce';
import { getStatusTypeFromMediaType } from '@/pages/Library/Media/MediaConfig';
import { assignMedia } from '@/services/displaysApi';
import { fetchMedia } from '@/services/mediaApi';
import type { Media } from '@/types/media';

const MEDIA_TYPE_OPTIONS = [
  { label: 'Image', value: 'image' },
  { label: 'Video', value: 'video' },
  { label: 'Audio', value: 'audio' },
  { label: 'PDF', value: 'pdf' },
  { label: 'Archive', value: 'archive' },
  { label: 'Other', value: 'other' },
];

interface AssignMediaModalProps {
  display: { displayGroupId: number };
  onClose: () => void;
  onSave: () => void;
}

export default function AssignMediaModal({ display, onClose, onSave }: AssignMediaModalProps) {
  const { t } = useTranslation();

  const [assignedMedia, setAssignedMedia] = useState<Media[]>([]);
  const [isLoadingAssigned, setIsLoadingAssigned] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);

  const [toAdd, setToAdd] = useState<number[]>([]);
  const [toRemove, setToRemove] = useState<number[]>([]);

  const [nameFilter, setNameFilter] = useState('');
  const [typeFilter, setTypeFilter] = useState('');
  const debouncedName = useDebounce(nameFilter, 400);

  const [pagination, setPagination] = useState<PaginationState>({ pageIndex: 0, pageSize: 5 });
  const [sorting, setSorting] = useState<SortingState>([]);

  useEffect(() => {
    setIsLoadingAssigned(true);
    fetchMedia({ start: 0, length: 500, displayGroupId: display.displayGroupId })
      .then(({ rows }) => setAssignedMedia(rows))
      .catch(() => setAssignedMedia([]))
      .finally(() => setIsLoadingAssigned(false));
  }, [display.displayGroupId]);

  const { data: searchData, isFetching } = useQuery({
    queryKey: ['assign-media-search', debouncedName, typeFilter, pagination, sorting],
    queryFn: ({ signal }) =>
      fetchMedia({
        start: pagination.pageIndex * pagination.pageSize,
        length: pagination.pageSize,
        keyword: debouncedName || undefined,
        type: typeFilter || undefined,
        sortBy: sorting[0]?.id,
        sortDir: sorting[0] ? (sorting[0].desc ? 'desc' : 'asc') : undefined,
        signal,
      }),
    placeholderData: keepPreviousData,
    staleTime: 1000 * 60,
  });

  const searchRows = searchData?.rows ?? [];
  const pageCount = Math.ceil((searchData?.totalCount ?? 0) / pagination.pageSize);

  const handleAdd = (media: Media) => {
    if (assignedMedia.some((m) => m.mediaId === media.mediaId)) {
      return;
    }
    setAssignedMedia((prev) => [...prev, media]);
    setToAdd((prev) => [...prev, media.mediaId]);
    setToRemove((prev) => prev.filter((id) => id !== media.mediaId));
  };

  const handleRemove = (media: Media) => {
    setAssignedMedia((prev) => prev.filter((m) => m.mediaId !== media.mediaId));
    setToRemove((prev) => [...prev, media.mediaId]);
    setToAdd((prev) => prev.filter((id) => id !== media.mediaId));
  };

  const handleSave = async () => {
    if (toAdd.length === 0 && toRemove.length === 0) {
      onClose();
      return;
    }
    try {
      setSaveError(null);
      setIsSaving(true);
      await assignMedia(display.displayGroupId, toAdd, toRemove);
      onSave();
      onClose();
    } catch (error) {
      const message =
        isAxiosError(error) && error.response?.data?.message
          ? error.response.data.message
          : t('Failed to assign media.');
      setSaveError(message);
    } finally {
      setIsSaving(false);
    }
  };

  const columns: ColumnDef<Media>[] = [
    {
      accessorKey: 'name',
      header: t('Name'),
      cell: (info) => <TextCell weight="bold">{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'mediaType',
      header: t('Type'),
      size: 120,
      cell: (info) => {
        const value = info.getValue<string>();
        return <StatusCell label={value} type={getStatusTypeFromMediaType(value)} />;
      },
    },
  ];

  const handleClearAll = () => {
    const originalAssignedIds = assignedMedia
      .filter((m) => !toAdd.includes(m.mediaId))
      .map((m) => m.mediaId);
    setAssignedMedia([]);
    setToAdd([]);
    setToRemove((prev) => [...new Set([...prev, ...originalAssignedIds])]);
  };

  return (
    <Modal
      title={t('Assign Files')}
      isOpen
      isPending={isSaving}
      onClose={onClose}
      error={saveError ?? undefined}
      size="lg"
      actions={[
        { label: t('Cancel'), onClick: onClose, variant: 'secondary', disabled: isSaving },
        {
          label: isSaving ? t('Saving\u2026') : t('Save'),
          onClick: handleSave,
          disabled: isSaving,
        },
      ]}
    >
      <div className="p-5">
        <SearchAssignPanel<Media>
          assignedItems={assignedMedia}
          isLoadingAssigned={isLoadingAssigned}
          onAddItem={handleAdd}
          onRemoveItem={handleRemove}
          onClearAll={handleClearAll}
          assignedLabel={t('Assigned Files')}
          noAssignedText={t('No files assigned.')}
          getItemId={(m) => m.mediaId}
          getItemLabel={(m) => m.name}
          keyword={nameFilter}
          onKeywordChange={setNameFilter}
          searchLabel={t('Name')}
          searchPlaceholder={t('Filter by name\u2026')}
          extraFilters={
            <div className="w-40">
              <label
                htmlFor="mediaTypeFilter"
                className="block text-sm font-medium text-gray-700 mb-1"
              >
                {t('Type')}
              </label>
              <SelectDropdown
                value={typeFilter}
                onSelect={(value) => {
                  setTypeFilter(value);
                  setPagination((prev) => ({ ...prev, pageIndex: 0 }));
                }}
                options={MEDIA_TYPE_OPTIONS}
                placeholder="All"
                clearable
              />
            </div>
          }
          columns={columns}
          searchRows={searchRows}
          pageCount={pageCount}
          pagination={pagination}
          onPaginationChange={setPagination}
          sorting={sorting}
          onSortingChange={setSorting}
          isSearching={isFetching}
        />
      </div>
    </Modal>
  );
}
