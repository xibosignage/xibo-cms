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
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import type { ModalType } from './TransitionsConfig';
import { getTransitionColumns } from './TransitionsConfig';
import { TransitionModals } from './components/TransitionModals';
import { useTransitionActions } from './hooks/useTransitionActions';
import { useTransitionData } from './hooks/useTransitionData';

import InfoBanner from '@/components/ui/InfoBanner';
import TabNav from '@/components/ui/TabNav';
import { DataTable } from '@/components/ui/table/DataTable';
import { useFilteredTabs } from '@/hooks/useFilteredTabs';
import { useTableState } from '@/hooks/useTableState';
import type { Transition } from '@/types/transition';

export default function Transitions() {
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
    isHydrated,
  } = useTableState<Record<string, never>>('transition_page', {
    pagination: { pageIndex: 0, pageSize: 10 },
    sorting: [],
    columnVisibility: {
      transitionId: false,
      transition: true,
      code: true,
      hasDirection: true,
      hasDuration: true,
      availableAsIn: true,
      availableAsOut: true,
    },
    viewMode: 'table',
    globalFilter: '',
    filterInputs: {},
  });

  const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
  const [activeModal, setActiveModal] = useState<ModalType>(null);
  const [selectedTransition, setSelectedTransition] = useState<Transition | null>(null);

  const openModal = (name: ModalType) => setActiveModal(name);
  const closeModal = () => setActiveModal(null);

  const {
    data: queryData,
    isFetching,
    isError,
    isPaused,
    error: queryError,
  } = useTransitionData({
    pagination,
    sorting,
    enabled: isHydrated,
  });

  const data = queryData?.rows;
  const pageCount = Math.ceil((queryData?.totalCount || 0) / pagination.pageSize);
  const error = isError && queryError instanceof Error ? queryError.message : '';
  const transitionList = data ?? [];

  const handleRefresh = () => {
    queryClient.invalidateQueries({ queryKey: ['transition'] });
  };

  const { isSaving, saveError, setSaveError, confirmEdit } = useTransitionActions({
    t,
    handleRefresh,
    closeModal,
  });

  const handleEdit = (transition: Transition) => {
    setSelectedTransition(transition);
    setSaveError(null);
    openModal('edit');
  };

  const columns = getTransitionColumns({ t, onEdit: handleEdit });

  const administrationTabs = useFilteredTabs('administration');

  return (
    <section className="flex h-full w-full min-h-0 relative outline-none overflow-hidden">
      <div className="flex-1 flex flex-col min-h-0 min-w-0 px-5 pb-5">
        <div className="flex flex-row justify-between py-4 items-center gap-4">
          <TabNav activeTab="Transitions" navigation={administrationTabs} />
        </div>

        {error && (
          <InfoBanner type="danger" className="w-full! mt-2 items-center">
            {error}
          </InfoBanner>
        )}

        {isPaused && (
          <InfoBanner type="warning" className="w-full! mt-2 items-center">
            {t(
              "You're offline. Showing previously loaded results. This will update automatically once your connection is restored.",
            )}
          </InfoBanner>
        )}

        <div className="min-h-0 flex flex-col">
          {!isHydrated ? (
            <div className="flex-1 flex items-center justify-center bg-gray-50 animate-pulse rounded-lg border border-gray-200">
              <span className="text-gray-400 font-medium">{t('Loading your preferences...')}</span>
            </div>
          ) : (
            <DataTable
              columns={columns}
              data={transitionList}
              pageCount={pageCount}
              rowCount={queryData?.totalCount || 0}
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
              columnPinning={{ left: [], right: ['tableActions'] }}
              columnVisibility={columnVisibility}
              onColumnVisibilityChange={setColumnVisibility}
              viewMode={null}
            />
          )}
        </div>
      </div>

      <TransitionModals
        actions={{ activeModal, closeModal, saveError, isSaving }}
        selection={{ selectedTransition }}
        handlers={{ confirmEdit }}
      />
    </section>
  );
}
