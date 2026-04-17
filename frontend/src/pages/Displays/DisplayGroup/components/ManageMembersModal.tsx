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
import {
  assignDisplayGroupsToGroup,
  assignDisplaysToGroup,
  fetchDisplayGroupRelationshipTree,
  fetchDisplayGroups,
  fetchDisplayGroupsAssigned,
  fetchDisplaysAssigned,
} from '@/services/displayGroupApi';
import { fetchDisplays } from '@/services/displaysApi';
import type { Display } from '@/types/display';
import type { DisplayGroup } from '@/types/displayGroup';

type ActiveTab = 'displays' | 'displayGroups' | 'tree';

interface ManageMembersModalProps {
  isOpen?: boolean;
  displayGroup: DisplayGroup | null;
  onClose: () => void;
  onSuccess: () => void;
}

export default function ManageMembersModal({
  isOpen = true,
  displayGroup,
  onClose,
  onSuccess,
}: ManageMembersModalProps) {
  const { t } = useTranslation();
  const groupId = displayGroup?.displayGroupId;
  const isDynamic = displayGroup?.isDynamic === 1;

  const [activeTab, setActiveTab] = useState<ActiveTab>('displays');
  const [isSaving, setIsSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | undefined>();

  // Displays tab state
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

  // Display Groups tab state
  const [assignedGroups, setAssignedGroups] = useState<DisplayGroup[]>([]);
  const [groupsToAdd, setGroupsToAdd] = useState<number[]>([]);
  const [groupsToRemove, setGroupsToRemove] = useState<number[]>([]);
  const [groupKeyword, setGroupKeyword] = useState('');
  const debouncedGroupKeyword = useDebounce(groupKeyword, 400);
  const [groupPagination, setGroupPagination] = useState<PaginationState>({
    pageIndex: 0,
    pageSize: 5,
  });
  const [groupSorting, setGroupSorting] = useState<SortingState>([]);

  // Initial membership queries
  const { data: initialDisplaysData, isLoading: isLoadingDisplays } = useQuery({
    queryKey: ['displayGroup', 'assigned-displays', groupId],
    queryFn: () => fetchDisplaysAssigned(groupId!),
    enabled: isOpen && !!groupId,
    staleTime: 0,
  });

  const { data: initialGroupsData, isLoading: isLoadingGroups } = useQuery({
    queryKey: ['displayGroup', 'assigned-groups', groupId],
    queryFn: () => fetchDisplayGroupsAssigned(groupId!),
    enabled: isOpen && !!groupId,
    staleTime: 0,
  });

  const { data: treeData } = useQuery({
    queryKey: ['displayGroup', 'relationship-tree', groupId],
    queryFn: () => fetchDisplayGroupRelationshipTree(groupId!),
    enabled: isOpen && !!groupId && activeTab === 'tree',
    staleTime: 1000 * 60,
  });

  // Seed local state from initial query data on open
  useEffect(() => {
    if (initialDisplaysData) {
      setAssignedDisplays(initialDisplaysData);
    }
  }, [initialDisplaysData]);

  useEffect(() => {
    if (initialGroupsData) {
      setAssignedGroups(initialGroupsData);
    }
  }, [initialGroupsData]);

  // Reset on close
  useEffect(() => {
    if (!isOpen) {
      setActiveTab('displays');
      setDisplayKeyword('');
      setDisplayPagination({ pageIndex: 0, pageSize: 5 });
      setDisplaySorting([]);
      setDisplaysToAdd([]);
      setDisplaysToRemove([]);
      setGroupKeyword('');
      setGroupPagination({ pageIndex: 0, pageSize: 5 });
      setGroupSorting([]);
      setGroupsToAdd([]);
      setGroupsToRemove([]);
      setSaveError(undefined);
    }
  }, [isOpen]);

  // Displays search query
  const { data: displaySearchData, isFetching: isFetchingDisplays } = useQuery({
    queryKey: [
      'members-displays-search',
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
    enabled: isOpen && !!groupId && !isLoadingDisplays,
    staleTime: 1000 * 60,
  });

  const displaySearchRows = displaySearchData?.rows ?? [];
  const displayPageCount = Math.ceil(
    (displaySearchData?.totalCount ?? 0) / displayPagination.pageSize,
  );

  // Display Groups search query
  const { data: groupSearchData, isFetching: isFetchingGroups } = useQuery({
    queryKey: ['members-groups-search', debouncedGroupKeyword, groupPagination, groupSorting],
    queryFn: ({ signal }) =>
      fetchDisplayGroups({
        start: groupPagination.pageIndex * groupPagination.pageSize,
        length: groupPagination.pageSize,
        keyword: debouncedGroupKeyword || undefined,
        isDisplaySpecific: 0,
        sortBy: groupSorting[0]?.id,
        sortDir: groupSorting[0] ? (groupSorting[0].desc ? 'desc' : 'asc') : undefined,
        signal,
      }),
    placeholderData: keepPreviousData,
    enabled: isOpen && !!groupId && !isLoadingGroups,
    staleTime: 1000 * 60,
  });

  const groupSearchRows = (groupSearchData?.rows ?? []).filter((g) => g.displayGroupId !== groupId);
  const groupPageCount = Math.ceil((groupSearchData?.totalCount ?? 0) / groupPagination.pageSize);

  // Displays add/remove/clear handlers
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

  // Display Groups add/remove/clear handlers
  const handleAddGroup = (group: DisplayGroup) => {
    if (assignedGroups.some((g) => g.displayGroupId === group.displayGroupId)) return;
    setAssignedGroups((prev) => [...prev, group]);
    setGroupsToAdd((prev) => [...prev, group.displayGroupId]);
    setGroupsToRemove((prev) => prev.filter((id) => id !== group.displayGroupId));
  };

  const handleRemoveGroup = (group: DisplayGroup) => {
    setAssignedGroups((prev) => prev.filter((g) => g.displayGroupId !== group.displayGroupId));
    setGroupsToRemove((prev) => [...prev, group.displayGroupId]);
    setGroupsToAdd((prev) => prev.filter((id) => id !== group.displayGroupId));
  };

  const handleClearGroups = () => {
    const originalIds = assignedGroups
      .filter((g) => !groupsToAdd.includes(g.displayGroupId))
      .map((g) => g.displayGroupId);
    setAssignedGroups([]);
    setGroupsToAdd([]);
    setGroupsToRemove((prev) => [...new Set([...prev, ...originalIds])]);
  };

  const displayColumns: ColumnDef<Display>[] = [
    {
      accessorKey: 'display',
      header: t('Display'),
      cell: (info) => <TextCell weight="bold">{info.getValue<string>()}</TextCell>,
    },
  ];

  const groupColumns: ColumnDef<DisplayGroup>[] = [
    {
      accessorKey: 'displayGroup',
      header: t('Display Group'),
      cell: (info) => <TextCell weight="bold">{info.getValue<string>()}</TextCell>,
    },
  ];

  const handleSave = async () => {
    if (!groupId) return;

    if (
      displaysToAdd.length === 0 &&
      displaysToRemove.length === 0 &&
      groupsToAdd.length === 0 &&
      groupsToRemove.length === 0
    ) {
      onClose();
      return;
    }

    try {
      setIsSaving(true);
      setSaveError(undefined);

      if (displaysToAdd.length > 0 || displaysToRemove.length > 0) {
        await assignDisplaysToGroup(groupId, displaysToAdd, displaysToRemove);
      }
      if (groupsToAdd.length > 0 || groupsToRemove.length > 0) {
        await assignDisplayGroupsToGroup(groupId, groupsToAdd, groupsToRemove);
      }

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

  const tabs: { key: ActiveTab; label: string }[] = [
    { key: 'displays', label: t('Displays') },
    { key: 'displayGroups', label: t('Display Groups') },
    { key: 'tree', label: t('Relationship Tree') },
  ];

  if (!isOpen) return null;

  return (
    <Modal
      isOpen={isOpen}
      title={t('Manage Membership for {{name}}', { name: displayGroup?.displayGroup })}
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
      {/* Tab bar */}
      <div className="flex flex-col h-full overflow-y-hidden overflow-x-visible px-6">
        <nav className="flex overflow-x-auto border-b border-gray-200" aria-label="Tabs">
          {tabs.map((tab) => (
            <button
              key={tab.key}
              type="button"
              onClick={() => setActiveTab(tab.key)}
              className={`py-2 px-3 inline-flex items-center gap-2 border-b-2 text-sm font-semibold whitespace-nowrap focus:outline-none transition-all  ${
                activeTab === tab.key
                  ? 'border-blue-600 text-blue-500'
                  : 'border-gray-200 text-gray-500 hover:text-blue-600'
              }`}
            >
              {tab.label}
            </button>
          ))}
        </nav>
      </div>

      <div className="flex flex-col flex-1 min-h-0 overflow-y-auto p-6">
        {/* Displays tab */}
        {activeTab === 'displays' && (
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
            warningMessage={
              isDynamic ? t('Displays cannot be manually assigned to a Dynamic Group.') : undefined
            }
          />
        )}

        {/* Display Groups tab */}
        {activeTab === 'displayGroups' && (
          <SearchAssignPanel<DisplayGroup>
            assignedItems={assignedGroups}
            isLoadingAssigned={isLoadingGroups}
            onAddItem={handleAddGroup}
            onRemoveItem={handleRemoveGroup}
            onClearAll={handleClearGroups}
            assignedLabel={t('Member Display Groups')}
            noAssignedText={t('No display groups assigned.')}
            getItemId={(g) => g.displayGroupId}
            getItemLabel={(g) => g.displayGroup}
            keyword={groupKeyword}
            onKeywordChange={setGroupKeyword}
            searchLabel={t('Search')}
            searchPlaceholder={t('Search display groups\u2026')}
            columns={groupColumns}
            searchRows={groupSearchRows}
            pageCount={groupPageCount}
            pagination={groupPagination}
            onPaginationChange={setGroupPagination}
            sorting={groupSorting}
            onSortingChange={setGroupSorting}
            isSearching={isLoadingGroups || isFetchingGroups}
            warningMessage={
              isDynamic
                ? t('Display groups cannot be manually assigned to a Dynamic Group.')
                : undefined
            }
          />
        )}

        {/* Relationship Tree tab */}
        {activeTab === 'tree' && (
          <div className="flex flex-col gap-3">
            <p className="text-sm text-gray-600">
              {t('Below is the family tree for this Display Group.')}
            </p>
            <p className="text-sm text-gray-500">
              {t(
                'The Display Group being edited is in bold. The list is ordered so that items above the current Display Group are its ancestors and items below are its descendants.',
              )}
            </p>
            <ul className="list-disc list-inside mt-2 text-sm">
              {(treeData ?? []).map((group) => (
                <li
                  key={group.displayGroupId}
                  className={group.displayGroupId === groupId ? 'font-bold' : 'text-gray-700'}
                >
                  {group.displayGroup}
                </li>
              ))}
            </ul>
          </div>
        )}
      </div>
    </Modal>
  );
}
