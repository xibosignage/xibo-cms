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
import { fetchDisplayGroups } from '@/services/displayGroupApi';
import { assignDisplayGroups, fetchDisplayGroupMembership } from '@/services/displaysApi';
import type { Display } from '@/types/display';
import type { DisplayGroup } from '@/types/displayGroup';

interface ManageGroupMembershipModalProps {
  display: Display;
  onClose: () => void;
  onSave: () => void;
}

export default function ManageGroupMembershipModal({
  display,
  onClose,
  onSave,
}: ManageGroupMembershipModalProps) {
  const { t } = useTranslation();

  const [assignedGroups, setAssignedGroups] = useState<DisplayGroup[]>([]);
  const [isLoadingAssigned, setIsLoadingAssigned] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);

  const [toAdd, setToAdd] = useState<number[]>([]);
  const [toRemove, setToRemove] = useState<number[]>([]);

  const [keyword, setKeyword] = useState('');
  const debouncedKeyword = useDebounce(keyword, 400);

  const [pagination, setPagination] = useState<PaginationState>({ pageIndex: 0, pageSize: 5 });
  const [sorting, setSorting] = useState<SortingState>([]);

  useEffect(() => {
    setIsLoadingAssigned(true);
    fetchDisplayGroupMembership(display.displayId)
      .then((groups) => setAssignedGroups(groups))
      .catch(() => setAssignedGroups([]))
      .finally(() => setIsLoadingAssigned(false));
  }, [display.displayId]);

  const { data: searchData, isFetching } = useQuery({
    queryKey: ['manage-group-membership-search', debouncedKeyword, pagination, sorting],
    queryFn: ({ signal }) =>
      fetchDisplayGroups({
        start: pagination.pageIndex * pagination.pageSize,
        length: pagination.pageSize,
        keyword: debouncedKeyword || undefined,
        isDisplaySpecific: 0,
        sortBy: sorting[0]?.id,
        sortDir: sorting[0] ? (sorting[0].desc ? 'desc' : 'asc') : undefined,
        signal,
      }),
    placeholderData: keepPreviousData,
    staleTime: 1000 * 60,
  });

  const searchRows = searchData?.rows ?? [];
  const pageCount = Math.ceil((searchData?.totalCount ?? 0) / pagination.pageSize);

  const handleAdd = (group: DisplayGroup) => {
    if (assignedGroups.some((g) => g.displayGroupId === group.displayGroupId)) {
      return;
    }
    setAssignedGroups((prev) => [...prev, group]);
    setToAdd((prev) => [...prev, group.displayGroupId]);
    setToRemove((prev) => prev.filter((id) => id !== group.displayGroupId));
  };

  const handleRemove = (group: DisplayGroup) => {
    setAssignedGroups((prev) => prev.filter((g) => g.displayGroupId !== group.displayGroupId));
    setToRemove((prev) => [...prev, group.displayGroupId]);
    setToAdd((prev) => prev.filter((id) => id !== group.displayGroupId));
  };

  const handleClearAll = () => {
    const originalIds = assignedGroups
      .filter((g) => !toAdd.includes(g.displayGroupId))
      .map((g) => g.displayGroupId);
    setAssignedGroups([]);
    setToAdd([]);
    setToRemove((prev) => [...new Set([...prev, ...originalIds])]);
  };

  const handleSave = async () => {
    if (toAdd.length === 0 && toRemove.length === 0) {
      onClose();
      return;
    }
    try {
      setSaveError(null);
      setIsSaving(true);
      await assignDisplayGroups(display.displayId, toAdd, toRemove);
      onSave();
      onClose();
    } catch (error) {
      const message =
        isAxiosError(error) && error.response?.data?.message
          ? error.response.data.message
          : t('Failed to update group membership.');
      setSaveError(message);
    } finally {
      setIsSaving(false);
    }
  };

  const columns: ColumnDef<DisplayGroup>[] = [
    {
      accessorKey: 'displayGroup',
      header: t('Display Group'),
      cell: (info) => <TextCell weight="bold">{info.getValue<string>()}</TextCell>,
    },
  ];

  return (
    <Modal
      title={t('Manage Membership for {{display}}', { display: display.display })}
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
        <SearchAssignPanel<DisplayGroup>
          assignedItems={assignedGroups}
          isLoadingAssigned={isLoadingAssigned}
          onAddItem={handleAdd}
          onRemoveItem={handleRemove}
          onClearAll={handleClearAll}
          assignedLabel={t('Member Groups')}
          noAssignedText={t('No groups assigned.')}
          getItemId={(g) => g.displayGroupId}
          getItemLabel={(g) => g.displayGroup}
          keyword={keyword}
          onKeywordChange={setKeyword}
          searchLabel={t('Search')}
          searchPlaceholder={t('Search groups\u2026')}
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
