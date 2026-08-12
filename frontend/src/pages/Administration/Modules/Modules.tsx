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
import { Search } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import type { ModalType, ModuleFilterInput } from './ModulesConfig';
import { getModuleColumns, INITIAL_FILTER_STATE } from './ModulesConfig';
import { ModuleModals } from './components/ModuleModals';
import { useModuleActions } from './hooks/useModuleActions';
import { useModuleData } from './hooks/useModuleData';
import { useModuleFilterOptions } from './hooks/useModuleFilterOptions';

import FilterButton from '@/components/ui/FilterButton';
import FilterInputs from '@/components/ui/FilterInputs';
import InfoBanner from '@/components/ui/InfoBanner';
import TabNav from '@/components/ui/TabNav';
import { DataTable } from '@/components/ui/table/DataTable';
import { AUTO_SUBMIT_FORMS } from '@/constants/autoSubmitForms';
import { useAutoSubmit } from '@/hooks/useAutoSubmit';
import { useFilteredTabs } from '@/hooks/useFilteredTabs';
import { useTableState } from '@/hooks/useTableState';
import type { Module } from '@/types/module';
import { countActiveFilters } from '@/utils/filters';

export default function Modules() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const { guard } = useAutoSubmit();

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
  } = useTableState<ModuleFilterInput>('module_page', {
    pagination: { pageIndex: 0, pageSize: 25 },
    sorting: [{ id: 'name', desc: false }],
    columnVisibility: {
      name: true,
      description: true,
      regionSpecific: true,
      defaultDuration: true,
      previewEnabled: true,
      assignable: true,
      enabled: true,
      isError: true,
    },
    viewMode: 'table',
    globalFilter: '',
    filterInputs: INITIAL_FILTER_STATE,
  });

  const [openFilter, setOpenFilter] = useState(false);
  const [activeModal, setActiveModal] = useState<ModalType>(null);
  const [selectedModule, setSelectedModule] = useState<Module | null>(null);
  const [rowSelection, setRowSelection] = useState<RowSelectionState>({});

  const openModal = (name: ModalType) => setActiveModal(name);
  const closeModal = () => {
    setActiveModal(null);
    setSelectedModule(null);
  };

  const {
    data: queryData,
    isFetching,
    isError,
    isPaused,
    error: queryError,
  } = useModuleData({
    pagination,
    sorting,
    filter: debouncedFilter,
    advancedFilters: filterInputs,
    enabled: isHydrated,
  });

  const moduleList: Module[] = queryData?.rows ?? [];
  const totalCount = queryData?.totalCount ?? 0;
  const pageCount = Math.ceil(totalCount / pagination.pageSize);
  const error = isError && queryError instanceof Error ? queryError.message : '';

  const handleRefresh = () => {
    queryClient.invalidateQueries({ queryKey: ['module'] });
  };

  const {
    isSaving,
    saveError,
    setSaveError,
    confirmSaveSettings,
    isClearing,
    clearError,
    setClearError,
    confirmClearCache,
  } = useModuleActions({ t, handleRefresh, closeModal });

  const handleConfigure = (module: Module) => {
    setSelectedModule(module);
    setSaveError(null);
    openModal('configure');
  };

  const handleClearCache = (module: Module) =>
    guard(
      AUTO_SUBMIT_FORMS.moduleClearCache,
      () => confirmClearCache(module.moduleId, { notifyOnError: true }),
      () => {
        setSelectedModule(module);
        setClearError(null);
        openModal('clearCache');
      },
    );

  const handleResetFilters = () => {
    setFilterInputs(INITIAL_FILTER_STATE);
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
  };

  const columns = getModuleColumns({
    t,
    onConfigure: handleConfigure,
    onClearCache: handleClearCache,
  });

  const { filterOptions } = useModuleFilterOptions();
  const administrationTabs = useFilteredTabs('administration');

  const activeFilterCount = countActiveFilters(filterInputs, INITIAL_FILTER_STATE, filterOptions);

  return (
    <section className="flex h-full w-full min-h-0 relative outline-none overflow-hidden">
      <div className="flex-1 flex flex-col min-h-0 min-w-0 px-5 pb-5">
        <div className="flex flex-row justify-between py-4 items-center gap-4">
          <TabNav activeTab="Modules" navigation={administrationTabs} />
        </div>

        <div className="flex flex-col lg:flex-row justify-end items-center gap-4">
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
                placeholder={t('Search modules...')}
                className="py-2 px-3 pl-10 block h-11.25 bg-gray-100 rounded-lg w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none disabled:bg-gray-200"
              />
            </div>
            <FilterButton
              isOpen={openFilter}
              onToggle={() => setOpenFilter((prev) => !prev)}
              activeCount={activeFilterCount}
              disabled={!isHydrated}
            />
          </div>
        </div>

        <FilterInputs
          onChange={(name, value) => {
            setFilterInputs((prev) => ({
              ...prev,
              [name]: value === undefined || value === '' ? null : (value as string),
            }));
            setPagination((prev) => ({ ...prev, pageIndex: 0 }));
          }}
          isOpen={openFilter}
          values={filterInputs}
          options={filterOptions}
          onReset={handleResetFilters}
        />

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
              data={moduleList}
              pageCount={pageCount}
              rowCount={totalCount}
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
              getRowId={(row: Module) => row.moduleId}
            />
          )}
        </div>
      </div>

      <ModuleModals
        actions={{
          activeModal,
          closeModal,
          handleRefresh,
          saveError,
          isSaving,
          clearError,
          isClearing,
        }}
        selection={{ selectedModule }}
        handlers={{ confirmSaveSettings, confirmClearCache }}
      />
    </section>
  );
}
