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
import Modal from '@/components/ui/modals/Modal';
import { TextCell } from '@/components/ui/table/cells';
import { useDebounce } from '@/hooks/useDebounce';
import { fetchDisplays } from '@/services/displaysApi';
import { assignSyncGroupMembers, fetchSyncGroupDisplays } from '@/services/syncGroupApi';
import type { Display } from '@/types/display';
import type { SyncGroup } from '@/types/syncGroup';

interface ManageMembersModalProps {
  isOpen?: boolean;
  syncGroup: SyncGroup | null;
  onClose: () => void;
  onSuccess: () => void;
}

export default function ManageMembersModal({
  isOpen = true,
  syncGroup,
  onClose,
  onSuccess,
}: ManageMembersModalProps) {
  const { t } = useTranslation();
  const syncGroupId = syncGroup?.syncGroupId;

  const [isSaving, setIsSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | undefined>();

  const [assignedDisplays, setAssignedDisplays] = useState<Display[]>([]);
  const [displaysToAdd, setDisplaysToAdd] = useState<number[]>([]);
  const [displaysToRemove, setDisplaysToRemove] = useState<number[]>([]);
  const [displayKeyword, setDisplayKeyword] = useState('');
  const debouncedDisplayKeyword = useDebounce(displayKeyword, 400);
  const [displayPagination, setDisplayPagination] = useState<PaginationState>({
    pageIndex: 0,
    pageSize: 5,
  });
  const [displaySorting, setDisplaySorting] = useState<SortingState>([]);

  const { data: initialDisplaysData, isLoading: isLoadingDisplays } = useQuery({
    queryKey: ['syncGroup', 'assigned-displays', syncGroupId],
    queryFn: () => fetchSyncGroupDisplays(syncGroupId!),
    enabled: isOpen && !!syncGroupId,
    staleTime: 0,
  });

  useEffect(() => {
    if (initialDisplaysData) {
      const mapped: Display[] = initialDisplaysData.map((d) => ({
        displayId: d.displayId,
        display: d.display,
      })) as Display[];
      setAssignedDisplays(mapped);
    }
  }, [initialDisplaysData]);

  useEffect(() => {
    if (!isOpen) {
      setDisplayKeyword('');
      setDisplayPagination({ pageIndex: 0, pageSize: 5 });
      setDisplaySorting([]);
      setDisplaysToAdd([]);
      setDisplaysToRemove([]);
      setSaveError(undefined);
    }
  }, [isOpen]);

  const { data: displaySearchData, isFetching: isFetchingDisplays } = useQuery({
    queryKey: [
      'syncgroup-members-displays-search',
      debouncedDisplayKeyword,
      displayPagination,
      displaySorting,
    ],
    queryFn: ({ signal }) =>
      fetchDisplays({
        start: displayPagination.pageIndex * displayPagination.pageSize,
        length: displayPagination.pageSize,
        keyword: debouncedDisplayKeyword || undefined,
        sortBy: displaySorting[0]?.id,
        sortDir: displaySorting[0] ? (displaySorting[0].desc ? 'desc' : 'asc') : undefined,
        signal,
      }),
    placeholderData: keepPreviousData,
    enabled: isOpen && !!syncGroupId && !isLoadingDisplays,
    staleTime: 1000 * 60,
  });

  const displaySearchRows = displaySearchData?.rows ?? [];
  const displayPageCount = Math.ceil(
    (displaySearchData?.totalCount ?? 0) / displayPagination.pageSize,
  );

  const handleAddDisplay = (display: Display) => {
    if (assignedDisplays.some((d) => d.displayId === display.displayId)) return;
    setAssignedDisplays((prev) => [...prev, display]);
    setDisplaysToAdd((prev) => [...prev, display.displayId]);
    setDisplaysToRemove((prev) => prev.filter((id) => id !== display.displayId));
  };

  const handleRemoveDisplay = (display: Display) => {
    setAssignedDisplays((prev) => prev.filter((d) => d.displayId !== display.displayId));
    setDisplaysToRemove((prev) => [...prev, display.displayId]);
    setDisplaysToAdd((prev) => prev.filter((id) => id !== display.displayId));
  };

  const handleClearDisplays = () => {
    const originalIds = assignedDisplays
      .filter((d) => !displaysToAdd.includes(d.displayId))
      .map((d) => d.displayId);
    setAssignedDisplays([]);
    setDisplaysToAdd([]);
    setDisplaysToRemove((prev) => [...new Set([...prev, ...originalIds])]);
  };

  const displayColumns: ColumnDef<Display>[] = [
    {
      accessorKey: 'display',
      header: t('Display'),
      cell: (info) => <TextCell weight="bold">{info.getValue<string>()}</TextCell>,
    },
  ];

  const handleSave = async () => {
    if (!syncGroupId) return;

    if (displaysToAdd.length === 0 && displaysToRemove.length === 0) {
      onClose();
      return;
    }

    try {
      setIsSaving(true);
      setSaveError(undefined);

      await assignSyncGroupMembers(syncGroupId, displaysToAdd, displaysToRemove);

      onSuccess();
      onClose();
    } catch (error) {
      const message =
        isAxiosError(error) && error.response?.data?.message
          ? error.response.data.message
          : t('Failed to update membership.');
      setSaveError(message);
    } finally {
      setIsSaving(false);
    }
  };

  if (!isOpen) return null;

  return (
    <Modal
      isOpen={isOpen}
      title={t('Manage Members for {{name}}', { name: syncGroup?.name })}
      onClose={onClose}
      size="lg"
      isPending={isSaving}
      error={saveError}
      actions={[
        { label: t('Cancel'), onClick: onClose, variant: 'secondary', disabled: isSaving },
        {
          label: isSaving ? t('Saving\u2026') : t('Save'),
          onClick: handleSave,
          disabled: isSaving,
        },
      ]}
    >
      <div className="flex flex-col flex-1 min-h-0 overflow-y-auto p-6">
        <SearchAssignPanel<Display>
          assignedItems={assignedDisplays}
          isLoadingAssigned={isLoadingDisplays}
          onAddItem={handleAddDisplay}
          onRemoveItem={handleRemoveDisplay}
          onClearAll={handleClearDisplays}
          assignedLabel={t('Member Displays')}
          noAssignedText={t('No displays assigned.')}
          getItemId={(d) => d.displayId}
          getItemLabel={(d) => d.display}
          keyword={displayKeyword}
          onKeywordChange={setDisplayKeyword}
          searchLabel={t('Search')}
          searchPlaceholder={t('Search displays\u2026')}
          columns={displayColumns}
          searchRows={displaySearchRows}
          pageCount={displayPageCount}
          pagination={displayPagination}
          onPaginationChange={setDisplayPagination}
          sorting={displaySorting}
          onSortingChange={setDisplaySorting}
          isSearching={isLoadingDisplays || isFetchingDisplays}
        />
      </div>
    </Modal>
  );
}
