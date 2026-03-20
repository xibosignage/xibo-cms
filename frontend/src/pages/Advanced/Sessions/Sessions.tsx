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
import { Filter, FilterX } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import type { ModalType } from './SessionsConfig';
import {
  getSessionColumns,
  getBulkActions,
  INITIAL_FILTER_STATE,
  type SessionFilterInput,
} from './SessionsConfig';
import { SessionModals } from './components/SessionModals';
import { useSessionActions } from './hooks/useSessionActions';
import { useSessionData } from './hooks/useSessionData';
import { useSessionFilterOptions } from './hooks/useSessionFilterOptions';

import Button from '@/components/ui/Button';
import FilterInputs from '@/components/ui/FilterInputs';
import TabNav from '@/components/ui/TabNav';
import { DataTable } from '@/components/ui/table/DataTable';
import { useFilteredTabs } from '@/hooks/useFilteredTabs';
import { useTableState } from '@/hooks/useTableState';
import type { Session } from '@/types/session';

export default function Sessions() {
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
    setGlobalFilter,
    filterInputs,
    setFilterInputs,
    isHydrated,
  } = useTableState<SessionFilterInput>('session_page', {
    pagination: { pageIndex: 0, pageSize: 10 },
    sorting: [],
    columnVisibility: {
      lastAccessed: true,
      isExpired: true,
      userName: true,
      remoteAddress: true,
      userAgent: true,
      expiresAt: true,
    },
    viewMode: 'table',
    globalFilter: '',
    filterInputs: INITIAL_FILTER_STATE,
  });

  const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
  const [selectionCache, setSelectionCache] = useState<Record<string, Session>>({});
  const [openFilter, setOpenFilter] = useState(false);

  const [activeModal, setActiveModal] = useState<ModalType | null>(null);

  const [sessionToLogout, setSessionToLogout] = useState<Session[]>([]);

  const openModal = (name: ModalType) => setActiveModal(name);
  const closeModal = () => setActiveModal(null);

  // Data fetching
  const {
    data: queryData,
    isFetching,
    isError,
    error: queryError,
  } = useSessionData({
    pagination,
    sorting,
    advancedFilters: filterInputs,
    enabled: isHydrated,
  });

  // Computed values
  const data = queryData?.rows;
  const pageCount = Math.ceil((queryData?.totalCount || 0) / pagination.pageSize);
  const error = isError && queryError instanceof Error ? queryError.message : '';

  const [sessionList, setSessionList] = useState<Session[]>([]);

  useEffect(() => {
    setSessionList(data ?? []);
  }, [data]);

  // Update the selected cache when data loads or selection changes
  useEffect(() => {
    if (!sessionList || sessionList.length === 0) {
      return;
    }

    setSelectionCache((prev) => {
      const next = { ...prev };
      let hasChanges = false;

      sessionList.forEach((item: Session) => {
        const id = item.userId.toString();

        if (rowSelection[id]) {
          if (!next[id]) {
            next[id] = item;
            hasChanges = true;
          }
        }
      });

      return hasChanges ? next : prev;
    });
  }, [sessionList, rowSelection]);

  const getRowId = (row: Session) => {
    return row.expiresAt.toString();
  };

  // Event handlers
  const handleRefresh = () => {
    queryClient.invalidateQueries({ queryKey: ['session'] });
  };

  const { isLoggingOut, logoutError, setLogoutError, confirmLogout } = useSessionActions({
    t,
    handleRefresh,
    closeModal,
    setRowSelection,
  });

  const handleLogout = (id: number) => {
    const session = sessionList.find((m) => m.userId === id);

    if (!session) {
      return;
    }

    setSessionToLogout([session]);
    setLogoutError(null);
    openModal('logout');
  };

  const handleResetFilters = () => {
    setFilterInputs(INITIAL_FILTER_STATE);
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
  };

  const columns = getSessionColumns({
    t,
    onLogout: handleLogout,
  });

  const getAllSelectedItems = (): Session[] => {
    return Object.keys(rowSelection)
      .map((id) => selectionCache[id])
      .filter((item): item is Session => !!item);
  };

  const bulkActions = getBulkActions({
    t,
    onLogout: () => {
      const allItems = getAllSelectedItems();
      setSessionToLogout(allItems);
      setLogoutError(null);
      openModal('logout');
    },
  });

  const { filterOptions } = useSessionFilterOptions(t);

  const libraryTabs = useFilteredTabs('advanced');

  return (
    <section className="flex h-full w-full min-h-0 relative outline-none overflow-hidden">
      <div className="flex-1 flex flex-col min-h-0 min-w-0 px-5 pb-5">
        <div className="flex flex-row justify-between py-4 items-center gap-4">
          <TabNav activeTab="Sessions" navigation={libraryTabs} />
        </div>

        <div className="flex flex-col items-end">
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

        <FilterInputs
          onChange={(name, value) => {
            setFilterInputs((prev) => ({ ...prev, [name]: value }));
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
          {!isHydrated ? (
            <div className="flex-1 flex items-center justify-center bg-gray-50 animate-pulse rounded-lg border border-gray-200">
              <span className="text-gray-400 font-medium">{t(`Loading your sessions...`)}</span>
            </div>
          ) : (
            <DataTable
              columns={columns}
              data={sessionList}
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
              enableSelection={false} // remove this for bulk session logout
              columnPinning={{ left: ['tableSelection'], right: ['tableActions'] }}
              columnVisibility={columnVisibility}
              onColumnVisibilityChange={setColumnVisibility}
              bulkActions={bulkActions}
              viewMode={null}
              getRowId={getRowId}
            />
          )}
        </div>
      </div>

      <SessionModals
        actions={{
          activeModal,
          closeModal,
          handleRefresh,
          setSessionList,
          logoutError,
          isLoggingOut,
        }}
        selection={{
          sessionToLogout,
        }}
        handlers={{
          confirmLogout,
        }}
      />
    </section>
  );
}
