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
import { fetchUsers } from '@/services/userApi';
import { assignGroupMembers } from '@/services/userGroupApi';
import type { User } from '@/types/user';
import type { UserGroup } from '@/types/userGroup';

interface ManageMembersModalProps {
  userGroup: UserGroup;
  onClose: () => void;
  onSuccess: () => void;
}

export default function ManageMembersModal({
  userGroup,
  onClose,
  onSuccess,
}: ManageMembersModalProps) {
  const { t } = useTranslation();

  const [assignedUsers, setAssignedUsers] = useState<User[]>([]);
  const [isLoadingAssigned, setIsLoadingAssigned] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);

  const [toAdd, setToAdd] = useState<number[]>([]);
  const [toRemove, setToRemove] = useState<number[]>([]);

  const [keyword, setKeyword] = useState('');
  const debouncedKeyword = useDebounce(keyword, 400);

  const [pagination, setPagination] = useState<PaginationState>({ pageIndex: 0, pageSize: 5 });
  const [sorting, setSorting] = useState<SortingState>([]);

  // Load currently assigned users (fresh on every open)
  useEffect(() => {
    setIsLoadingAssigned(true);
    setToAdd([]);
    setToRemove([]);
    fetchUsers({ start: 0, length: 1000, userGroupIdMembers: userGroup.groupId })
      .then((res) => setAssignedUsers(res.rows))
      .catch(() => setAssignedUsers([]))
      .finally(() => setIsLoadingAssigned(false));
  }, [userGroup]);

  // Search for users to assign
  const { data: searchData, isFetching } = useQuery({
    queryKey: ['manage-group-members-search', debouncedKeyword, pagination, sorting],
    queryFn: ({ signal }) =>
      fetchUsers({
        start: pagination.pageIndex * pagination.pageSize,
        length: pagination.pageSize,
        userName: debouncedKeyword || undefined,
        sortBy: sorting[0]?.id,
        sortDir: sorting[0] ? (sorting[0].desc ? 'desc' : 'asc') : undefined,
        signal,
      }),
    placeholderData: keepPreviousData,
    staleTime: 1000 * 60,
  });

  const searchRows = searchData?.rows ?? [];
  const pageCount = Math.ceil((searchData?.totalCount ?? 0) / pagination.pageSize);

  const handleAdd = (user: User) => {
    if (assignedUsers.some((u) => u.userId === user.userId)) {
      return;
    }
    setAssignedUsers((prev) => [...prev, user]);
    setToAdd((prev) => [...prev, user.userId]);
    setToRemove((prev) => prev.filter((id) => id !== user.userId));
  };

  const handleRemove = (user: User) => {
    setAssignedUsers((prev) => prev.filter((u) => u.userId !== user.userId));
    setToRemove((prev) => [...prev, user.userId]);
    setToAdd((prev) => prev.filter((id) => id !== user.userId));
  };

  const handleClearAll = () => {
    const originalIds = assignedUsers.filter((u) => !toAdd.includes(u.userId)).map((u) => u.userId);
    setAssignedUsers([]);
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
      await assignGroupMembers(userGroup.groupId, toAdd, toRemove);
      onSuccess();
      onClose();
    } catch (error) {
      const message =
        isAxiosError(error) && error.response?.data?.message
          ? error.response.data.message
          : t('Failed to update group members.');
      setSaveError(message);
    } finally {
      setIsSaving(false);
    }
  };

  const columns: ColumnDef<User>[] = [
    {
      accessorKey: 'userName',
      header: t('User Name'),
      cell: (info) => <TextCell weight="bold">{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'email',
      header: t('Email'),
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
  ];

  return (
    <Modal
      title={t('Manage Members for {{group}}', { group: userGroup.group })}
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
        <SearchAssignPanel<User>
          assignedItems={assignedUsers}
          isLoadingAssigned={isLoadingAssigned}
          onAddItem={handleAdd}
          onRemoveItem={handleRemove}
          onClearAll={handleClearAll}
          assignedLabel={t('Group Members')}
          noAssignedText={t('No users assigned.')}
          getItemId={(u) => u.userId}
          getItemLabel={(u) => u.userName}
          keyword={keyword}
          onKeywordChange={setKeyword}
          searchLabel={t('Search')}
          searchPlaceholder={t('Search users\u2026')}
          columns={columns}
          searchRows={searchRows}
          pageCount={pageCount}
          rowCount={searchData?.totalCount ?? 0}
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
