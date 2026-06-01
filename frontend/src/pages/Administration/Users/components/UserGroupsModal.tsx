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
import { assignUserGroups } from '@/services/userApi';
import { fetchUserGroups } from '@/services/userGroupApi';
import type { User } from '@/types/user';
import type { UserGroup } from '@/types/userGroup';

export interface UserGroupsModalProps {
  user: User;
  onClose: () => void;
  onSuccess: () => void;
}

export default function UserGroupsModal({ user, onClose, onSuccess }: UserGroupsModalProps) {
  const { t } = useTranslation();

  const [assignedGroups, setAssignedGroups] = useState<UserGroup[]>([]);
  const [isLoadingAssigned, setIsLoadingAssigned] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);

  const [toAdd, setToAdd] = useState<number[]>([]);
  const [toRemove, setToRemove] = useState<number[]>([]);

  const [keyword, setKeyword] = useState('');
  const debouncedKeyword = useDebounce(keyword, 400);

  const [pagination, setPagination] = useState<PaginationState>({ pageIndex: 0, pageSize: 5 });
  const [sorting, setSorting] = useState<SortingState>([]);

  // Load currently assigned groups fresh from the API
  useEffect(() => {
    setIsLoadingAssigned(true);
    setToAdd([]);
    setToRemove([]);
    fetchUserGroups({ start: 0, length: 1000, userIdMember: user.userId })
      .then((res) => setAssignedGroups(res.rows.filter((g) => g.isUserSpecific !== 1)))
      .catch(() => setAssignedGroups([]))
      .finally(() => setIsLoadingAssigned(false));
  }, [user]);

  // Search available groups
  const { data: searchData, isFetching } = useQuery({
    queryKey: ['user-groups-search', debouncedKeyword, pagination, sorting],
    queryFn: ({ signal }) =>
      fetchUserGroups({
        start: pagination.pageIndex * pagination.pageSize,
        length: pagination.pageSize,
        userGroup: debouncedKeyword || undefined,
        isUser: 0,
        signal,
      }),
    placeholderData: keepPreviousData,
    staleTime: 1000 * 60,
  });

  const searchRows = (searchData?.rows ?? []).filter((g) => g.isUserSpecific !== 1);
  const pageCount = Math.ceil((searchData?.totalCount ?? 0) / pagination.pageSize);

  const handleAdd = (group: UserGroup) => {
    if (assignedGroups.some((g) => g.groupId === group.groupId)) return;
    setAssignedGroups((prev) => [...prev, group]);
    setToAdd((prev) => [...prev, group.groupId]);
    setToRemove((prev) => prev.filter((id) => id !== group.groupId));
  };

  const handleRemove = (group: UserGroup) => {
    setAssignedGroups((prev) => prev.filter((g) => g.groupId !== group.groupId));
    setToRemove((prev) => [...prev, group.groupId]);
    setToAdd((prev) => prev.filter((id) => id !== group.groupId));
  };

  const handleClearAll = () => {
    const originalIds = assignedGroups
      .filter((g) => !toAdd.includes(g.groupId))
      .map((g) => g.groupId);
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
      await assignUserGroups(user.userId, toAdd, toRemove);
      onSuccess();
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

  const columns: ColumnDef<UserGroup>[] = [
    {
      accessorKey: 'group',
      header: t('Group'),
      cell: (info) => <TextCell weight="bold">{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'description',
      header: t('Description'),
      cell: (info) => <TextCell truncate>{info.getValue<string>() ?? ''}</TextCell>,
    },
  ];

  return (
    <Modal
      title={t('User Groups for {{name}}', { name: user.userName })}
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
        <SearchAssignPanel<UserGroup>
          assignedItems={assignedGroups}
          isLoadingAssigned={isLoadingAssigned}
          onAddItem={handleAdd}
          onRemoveItem={handleRemove}
          onClearAll={handleClearAll}
          assignedLabel={t('Member Groups')}
          noAssignedText={t('No groups assigned.')}
          getItemId={(g) => g.groupId}
          getItemLabel={(g) => g.group}
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
