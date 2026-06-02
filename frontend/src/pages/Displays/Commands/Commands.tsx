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
import type { RowSelectionState } from '@tanstack/react-table';
import { Search, Filter, FilterX, Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import type { ModalType } from './CommandsConfig';
import {
  getCommandColumns,
  getBulkActions,
  INITIAL_FILTER_STATE,
  type CommandsFilterInput,
} from './CommandsConfig';
import { CommandModals } from './components/CommandModals';
import { useCommandActions } from './hooks/useCommandActions';
import { useCommandFilterOptions } from './hooks/useCommandFilterOptions';
import { useCommandsData } from './hooks/useCommandsData';

import Button from '@/components/ui/Button';
import FilterInputs from '@/components/ui/FilterInputs';
import TabNav from '@/components/ui/TabNav';
import { DataTable } from '@/components/ui/table/DataTable';
import { useFilteredTabs } from '@/hooks/useFilteredTabs';
import { useTableState } from '@/hooks/useTableState';
import type { Command } from '@/types/command';

export default function Commands() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();

  const {
    pagination,
    setPagination,
    sorting,
    setSorting,
    columnVisibility,
    setColumnVisibility,
    globalFilter,
    debouncedFilter,
    setGlobalFilter,
    filterInputs,
    setFilterInputs,
    isHydrated,
  } = useTableState<CommandsFilterInput>('command_page', {
    pagination: { pageIndex: 0, pageSize: 10 },
    sorting: [],
    columnVisibility: {
      commandId: true,
      command: true,
    },
    viewMode: 'table',
    globalFilter: '',
    filterInputs: INITIAL_FILTER_STATE,
  });

  const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
  const [selectionCache, setSelectionCache] = useState<Record<string, Command>>({});
  const [openFilter, setOpenFilter] = useState(false);

  const [activeModal, setActiveModal] = useState<ModalType | null>(null);
  const [itemsToDelete, setItemsToDelete] = useState<Command[]>([]);
  const [selectedCommandId, setSelectedCommandId] = useState<number | null>(null);
  const [shareEntityIds, setShareEntityIds] = useState<number | number[] | null>(null);

  const openModal = (name: ModalType) => setActiveModal(name);
  const closeModal = () => {
    setActiveModal(null);
    setShareEntityIds(null);
  };

  const handleRefresh = () => {
    queryClient.invalidateQueries({ queryKey: ['commands'] });
  };

  const {
    data: queryData,
    isFetching,
    isError,
    error: queryError,
  } = useCommandsData({
    pagination,
    sorting,
    filter: debouncedFilter,
    advancedFilters: filterInputs,
    enabled: isHydrated,
  });

  const data = queryData?.rows;
  const pageCount = Math.ceil((queryData?.totalCount || 0) / pagination.pageSize);
  const error = isError && queryError instanceof Error ? queryError.message : '';

  const [commandList, setCommandList] = useState<Command[]>([]);

  useEffect(() => {
    setCommandList(data ?? []);
  }, [data]);

  useEffect(() => {
    if (!commandList || commandList.length === 0) {
      return;
    }

    setSelectionCache((prev) => {
      const next = { ...prev };
      let hasChanges = false;

      commandList.forEach((item) => {
        const id = item.commandId.toString();
        if (rowSelection[id] && next[id] !== item) {
          next[id] = item;
          hasChanges = true;
        }
      });

      return hasChanges ? next : prev;
    });
  }, [commandList, rowSelection]);

  const getRowId = (row: Command) => {
    return row.commandId.toString();
  };

  const { isDeleting, deleteError, setDeleteError, confirmDelete } = useCommandActions({
    t,
    handleRefresh,
    closeModal,
    setRowSelection,
  });

  const handleDelete = (id: number) => {
    const command = commandList.find((m) => m.commandId === id);
    if (!command) {
      return;
    }

    setItemsToDelete([command]);
    setDeleteError(null);
    openModal('delete');
  };

  const openAddModal = () => {
    setSelectedCommandId(null);
    openModal('add');
  };

  const openEditModal = (command: Command) => {
    setSelectedCommandId(command.commandId);
    openModal('edit');
  };

  const openShareModal = (command: Command) => {
    setShareEntityIds(command.commandId);
    openModal('share');
  };

  const { filterOptions } = useCommandFilterOptions(t);

  const handleResetFilters = () => {
    setFilterInputs(INITIAL_FILTER_STATE);
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
  };

  const columns = getCommandColumns({
    t,
    onDelete: handleDelete,
    openEditModal,
    openShareModal,
  });

  const getAllSelectedItems = (): Command[] => {
    return Object.keys(rowSelection)
      .map((id) => selectionCache[id])
      .filter((item): item is Command => !!item);
  };

  const bulkActions = getBulkActions({
    t,
    onDelete: () => {
      const allItems = getAllSelectedItems();
      setItemsToDelete(allItems);
      setDeleteError(null);
      openModal('delete');
    },
    onShare: () => {
      const allItems = getAllSelectedItems();
      setShareEntityIds(allItems.map((item) => item.commandId));
      openModal('share');
    },
  });

  const selectedCommand = commandList.find((m) => m.commandId === selectedCommandId) ?? null;

  const displaysTabs = useFilteredTabs('displays');

  return (
    <>
      <section className="flex h-full w-full min-h-0 relative outline-none overflow-hidden">
        <div className="flex-1 flex flex-col min-h-0 min-w-0 px-5 pb-5">
          <div className="flex flex-row justify-between py-4 items-center gap-4">
            <TabNav activeTab="Commands" navigation={displaysTabs} />
            <div className="flex items-center gap-2 md:mb-0">
              <Button
                variant="primary"
                className="font-semibold"
                disabled={!isHydrated}
                onClick={openAddModal}
                leftIcon={Plus}
              >
                {t('Add Command')}
              </Button>
            </div>
          </div>

          <div className="flex flex-col lg:flex-row justify-between items-center gap-4">
            <div className="w-full lg:flex-1 md:min-w-0" />
            <div className="flex items-center gap-2 w-full xl:w-115 lg:w-75 shrink-0">
              <div className="relative flex-1 flex">
                <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                  <Search className="w-4 h-4 text-gray-400" />
                </div>
                <input
                  name="search"
                  value={globalFilter}
                  disabled={!isHydrated}
                  onChange={(e) => {
                    setGlobalFilter(e.target.value);
                    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
                  }}
                  placeholder={t('Search commands...')}
                  className="py-2 px-3 pl-10 block h-11.25 bg-gray-100 rounded-lg w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none disabled:bg-gray-200"
                />
              </div>
              <Button
                leftIcon={!openFilter ? Filter : FilterX}
                variant="secondary"
                disabled={!isHydrated}
                onClick={() => setOpenFilter((prev) => !prev)}
                removeTextOnMobile
              >
                {t('Filters')}
              </Button>
            </div>
          </div>

          <FilterInputs
            onChange={(name, value) => {
              setFilterInputs((prev) => {
                return {
                  ...prev,
                  [name]: value === undefined || value === '' ? null : value,
                } as CommandsFilterInput;
              });
              setPagination((prev) => ({ ...prev, pageIndex: 0 }));
            }}
            isOpen={openFilter}
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
            {!isHydrated ? (
              <div className="flex-1 flex items-center justify-center bg-gray-50 animate-pulse rounded-lg border border-gray-200">
                <span className="text-gray-400 font-medium">{t('Loading commands...')}</span>
              </div>
            ) : (
              <DataTable
                columns={columns}
                data={commandList}
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
                columnPinning={{ left: ['tableSelection'], right: ['tableActions'] }}
                columnVisibility={columnVisibility}
                onColumnVisibilityChange={setColumnVisibility}
                bulkActions={bulkActions}
                viewMode="table"
                getRowId={getRowId}
              />
            )}
          </div>
        </div>
      </section>

      <CommandModals
        actions={{
          activeModal,
          closeModal,
          handleRefresh,
          setCommandList,
          deleteError,
          isDeleting,
        }}
        selection={{
          selectedCommand,
          itemsToDelete,
          shareEntityIds,
        }}
        handlers={{
          confirmDelete,
        }}
      />
    </>
  );
}
