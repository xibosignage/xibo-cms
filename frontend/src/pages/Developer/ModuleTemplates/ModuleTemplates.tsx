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

import { useQuery, useQueryClient } from '@tanstack/react-query';
import type { RowSelectionState } from '@tanstack/react-table';
import { isAxiosError } from 'axios';
import { Plus, Search, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';

import type { ModalType, ModuleTemplateFilterInput } from './ModuleTemplatesConfig';
import {
  getBulkActions,
  getFilterKeys,
  getModuleTemplateColumns,
  INITIAL_FILTER_STATE,
} from './ModuleTemplatesConfig';
import { ModuleTemplateModals } from './components/ModuleTemplateModals';
import { moduleTemplateQueryKeys, useModuleTemplatesData } from './hooks/useModuleTemplatesData';

import Button from '@/components/ui/Button';
import FilterButton from '@/components/ui/FilterButton';
import FilterInputs from '@/components/ui/FilterInputs';
import { notify } from '@/components/ui/Notification';
import TabNav from '@/components/ui/TabNav';
import ShareModal from '@/components/ui/modals/ShareModal';
import { DataTable } from '@/components/ui/table/DataTable';
import { useUserContext } from '@/context/UserContext';
import { useFilteredTabs } from '@/hooks/useFilteredTabs';
import { useTableState } from '@/hooks/useTableState';
import { fetchDataTypes, importModuleTemplateXml } from '@/services/moduleTemplatesApi';
import type { ModuleTemplate } from '@/types/moduleTemplates';
import { UserType } from '@/types/user';
import { countActiveFilters } from '@/utils/filters';
import { hasFeature } from '@/utils/permissions';

export default function ModuleTemplates() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const fileInputRef = useRef<HTMLInputElement>(null);
  const { user } = useUserContext();
  const isSuperAdmin = user?.userTypeId === UserType.SuperAdmin;
  const canManageTemplates = hasFeature(user, 'developer.edit');
  const canDeleteTemplates = hasFeature(user, 'developer.delete');

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
  } = useTableState<ModuleTemplateFilterInput>('module_template_page', {
    pagination: { pageIndex: 0, pageSize: 25 },
    sorting: [{ id: 'templateId', desc: false }],
    columnVisibility: {
      id: true,
      templateId: true,
      dataType: true,
      title: true,
      type: true,
      groupsWithPermissions: true,
    },
    viewMode: 'table',
    globalFilter: '',
    filterInputs: INITIAL_FILTER_STATE,
  });

  const [openFilter, setOpenFilter] = useState(false);
  const [activeModal, setActiveModal] = useState<ModalType>(null);
  const [selectedTemplate, setSelectedTemplate] = useState<ModuleTemplate | null>(null);
  const [shareEntityId, setShareEntityId] = useState<number | number[] | null>(null);
  const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
  const [selectionCache, setSelectionCache] = useState<Record<string, ModuleTemplate>>({});

  const openModal = (name: ModalType) => setActiveModal(name);
  const closeModal = () => {
    setActiveModal(null);
    setSelectedTemplate(null);
    setShareEntityId(null);
  };

  const {
    data: queryData,
    isFetching,
    isError,
    error: queryError,
  } = useModuleTemplatesData({
    pagination,
    sorting,
    filter: debouncedFilter,
    advancedFilters: filterInputs,
    enabled: isHydrated,
  });

  const { data: dataTypes = [] } = useQuery({
    queryKey: ['moduleTemplateDataTypes'],
    queryFn: fetchDataTypes,
    staleTime: Infinity,
  });

  const dataTypeOptions = dataTypes.map((dt) => ({ label: dt.name, value: dt.id }));

  const templateList: ModuleTemplate[] = queryData?.rows ?? [];
  const totalCount = queryData?.totalCount ?? 0;
  const pageCount = Math.ceil(totalCount / pagination.pageSize);
  const error = isError && queryError instanceof Error ? queryError.message : '';

  const developerTabs = useFilteredTabs('developer');

  const getRowId = (row: ModuleTemplate) => String(row.id);

  const handleRowSelectionChange: typeof setRowSelection = (updaterOrValue) => {
    const newSelection =
      typeof updaterOrValue === 'function' ? updaterOrValue(rowSelection) : updaterOrValue;

    setRowSelection(newSelection);

    setSelectionCache((prev) => {
      const next = { ...prev };
      templateList.forEach((item) => {
        const id = getRowId(item);
        if (newSelection[id]) {
          next[id] = item;
        }
      });
      return next;
    });
  };

  const getAllSelectedItems = (): ModuleTemplate[] =>
    Object.keys(rowSelection)
      .map((id) => selectionCache[id])
      .filter((item): item is ModuleTemplate => !!item);

  const handleRefresh = () => {
    queryClient.invalidateQueries({ queryKey: moduleTemplateQueryKeys.all });
  };

  const handleEdit = (template: ModuleTemplate) => {
    navigate(`/developer/template/${template.id}/edit`);
  };

  const handleAdd = () => {
    openModal('add');
  };

  const handleCopy = (template: ModuleTemplate) => {
    setSelectedTemplate(template);
    openModal('copy');
  };

  const handleDelete = (template: ModuleTemplate) => {
    setSelectedTemplate(template);
    openModal('delete');
  };

  const handleShare = (template: ModuleTemplate) => {
    setShareEntityId(template.id);
    openModal('share');
  };

  const handleBulkDelete = () => {
    openModal('bulkDelete');
  };

  const handleBulkShare = () => {
    setShareEntityId(getAllSelectedItems().map((item) => item.id));
    openModal('share');
  };

  const handleBulkSuccess = () => {
    setRowSelection({});
    closeModal();
    handleRefresh();
  };

  // A bulk delete batch can partially succeed: keep the modal open (it still shows the
  // error for the templates that failed) but drop the already-deleted ids from the
  // selection and refresh the table so they don't linger as if nothing happened.
  const handleBulkPartialSuccess = (deletedIds: number[]) => {
    setRowSelection((prev) => {
      const next = { ...prev };
      deletedIds.forEach((id) => {
        delete next[String(id)];
      });
      return next;
    });
    handleRefresh();
  };

  const handleImportXml = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (!file) return;
    try {
      await importModuleTemplateXml(file);
      notify.success(t('Template imported successfully'));
      handleRefresh();
    } catch (err: unknown) {
      const message =
        (isAxiosError(err) && err.response?.data?.message) ||
        (err instanceof Error && err.message) ||
        t('Failed to import template');
      notify.error(message);
    } finally {
      if (fileInputRef.current) fileInputRef.current.value = '';
    }
  };

  const handleResetFilters = () => {
    setFilterInputs(INITIAL_FILTER_STATE);
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
  };

  const columns = getModuleTemplateColumns({
    t,
    onEdit: handleEdit,
    onCopy: handleCopy,
    onDelete: handleDelete,
    onShare: handleShare,
    currentUserId: user?.userId ?? 0,
    isSuperAdmin,
    canManageTemplates,
    canDeleteTemplates,
  });

  const bulkActions = getBulkActions({
    t,
    onBulkDelete: handleBulkDelete,
    onBulkShare: handleBulkShare,
    isSuperAdmin,
    canDeleteTemplates,
  });

  const filterOptions = getFilterKeys(t, dataTypeOptions);

  const activeFilterCount = countActiveFilters(filterInputs, INITIAL_FILTER_STATE, filterOptions);

  return (
    <section className="flex h-full w-full min-h-0 relative outline-none overflow-hidden">
      <div className="flex-1 flex flex-col min-h-0 min-w-0 px-5 pb-5">
        <div className="flex flex-row justify-between py-4 items-center gap-4">
          <TabNav activeTab="Module Templates" navigation={developerTabs} />
          <div className="flex items-center gap-2 shrink-0">
            <input
              ref={fileInputRef}
              type="file"
              accept=".xml"
              className="hidden"
              onChange={handleImportXml}
            />
            <Button leftIcon={Plus} variant="primary" onClick={handleAdd}>
              {t('Add Module Template')}
            </Button>
            <Button
              leftIcon={Upload}
              variant="secondary"
              onClick={() => fileInputRef.current?.click()}
            >
              {t('Import XML')}
            </Button>
          </div>
        </div>

        <div className="flex flex-col gap-2">
          {/* Search + Filter - right-aligned */}
          <div className="flex justify-end">
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
                  placeholder={t('Search module templates...')}
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
                [name]: value === undefined || value === '' ? null : value,
              }));
              setPagination((prev) => ({ ...prev, pageIndex: 0 }));
            }}
            isOpen={openFilter}
            values={filterInputs}
            options={filterOptions}
            onReset={handleResetFilters}
          />
        </div>

        {error && (
          <div className="bg-red-50 border border-red-200 text-red-800 p-4" role="alert">
            {error}
          </div>
        )}

        <div className="min-h-0 flex flex-col">
          {!isHydrated ? (
            <div className="flex-1 flex items-center justify-center bg-gray-50 animate-pulse rounded-lg border border-gray-200">
              <span className="text-gray-400 font-medium">{t('Loading your preferences...')}</span>
            </div>
          ) : (
            <DataTable
              columns={columns}
              data={templateList}
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
              onRowSelectionChange={handleRowSelectionChange}
              bulkActions={bulkActions}
              onRefresh={handleRefresh}
              columnPinning={{ left: [], right: ['tableActions'] }}
              columnVisibility={columnVisibility}
              onColumnVisibilityChange={setColumnVisibility}
              viewMode={null}
              getRowId={getRowId}
            />
          )}
        </div>
      </div>

      <ShareModal
        title={t('Share Module Template')}
        entityType="ModuleTemplate"
        entityId={shareEntityId}
        isOpen={activeModal === 'share'}
        onClose={handleBulkSuccess}
      />

      <ModuleTemplateModals
        activeModal={activeModal}
        selectedTemplate={selectedTemplate}
        selectedTemplates={getAllSelectedItems()}
        onClose={closeModal}
        onSuccess={handleBulkSuccess}
        onPartialSuccess={handleBulkPartialSuccess}
      />
    </section>
  );
}
