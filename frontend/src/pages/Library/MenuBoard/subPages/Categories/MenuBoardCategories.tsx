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
import { ChevronRight, Filter, FilterX, Plus, Search } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate, useParams } from 'react-router-dom';

import {
  getCategoryBulkActions,
  getCategoryColumnDefinitions,
  INITIAL_CATEGORY_FILTER_STATE,
  getCategoryFilterKeys,
  type MenuBoardCategoryFilterInput,
} from './MenuBoardCategoriesConfig';
import { MenuBoardCategoryModals } from './components/MenuBoardCategoryModals';
import {
  MenuBoardCategoryQueryKeys,
  useMenuBoardCategoriesData,
} from './hooks/useMenuBoardCategoriesData';

import Button from '@/components/ui/Button';
import FilterInputs from '@/components/ui/FilterInputs';
import { notify } from '@/components/ui/Notification';
import TabNav from '@/components/ui/TabNav';
import { DataTable } from '@/components/ui/table/DataTable';
import { useFilteredTabs } from '@/hooks/useFilteredTabs';
import { useTableState } from '@/hooks/useTableState';
import MediaPreviewer from '@/pages/Library/Media/components/MediaPreviewer';
import {
  getMenuBoardById,
  copyMenuBoardCategory,
  deleteMenuBoardCategory,
} from '@/services/menuBoardApi';
import type { MenuBoardCategory } from '@/types/menuBoardCategory';

type CategoryModalType = 'edit' | 'copy' | 'delete' | null;

export default function MenuBoardCategories() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { menuId } = useParams<{ menuId: string }>();

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
  } = useTableState<MenuBoardCategoryFilterInput>('menuBoardCategories', {
    pagination: { pageIndex: 0, pageSize: 10 },
    sorting: [],
    columnVisibility: {
      menuCategoryId: true,
      name: true,
      thumbnail: true,
      description: true,
      code: true,
    },
    viewMode: 'table',
    globalFilter: '',
    filterInputs: INITIAL_CATEGORY_FILTER_STATE,
    folderId: null,
  });

  const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
  const [selectionCache, setSelectionCache] = useState<Record<string, MenuBoardCategory>>({});
  const [openFilter, setOpenFilter] = useState(false);
  const [activeModal, setActiveModal] = useState<CategoryModalType>(null);
  const [previewItem, setPreviewItem] = useState<MenuBoardCategory | null>(null);
  const [selectedCategory, setSelectedCategory] = useState<MenuBoardCategory | null>(null);
  const [itemsToDelete, setItemsToDelete] = useState<MenuBoardCategory[]>([]);
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const [isDeleting, setIsDeleting] = useState(false);
  const [isCloning, setIsCloning] = useState(false);

  const openModal = (name: CategoryModalType) => setActiveModal(name);
  const closeModal = () => {
    setActiveModal(null);
    setSelectedCategory(null);
    setItemsToDelete([]);
    setDeleteError(null);
  };

  const { data: menuBoard } = useQuery({
    queryKey: ['menuBoard', menuId],
    queryFn: () => getMenuBoardById(menuId!),
    enabled: !!menuId,
    staleTime: 1000 * 60 * 5,
  });

  const {
    data: queryData,
    isFetching,
    isError,
    error: queryError,
  } = useMenuBoardCategoriesData({
    menuId: menuId!,
    pagination,
    sorting,
    filter: debouncedFilter,
    advancedFilters: filterInputs,
    enabled: isHydrated && !!menuId,
  });

  const categoryList = queryData?.rows ?? [];
  const pageCount = Math.ceil((queryData?.totalCount || 0) / pagination.pageSize);
  const error = isError && queryError instanceof Error ? queryError.message : '';
  const existingNames = categoryList.map((c) => c.name);

  const handleRefresh = () => {
    queryClient.invalidateQueries({ queryKey: MenuBoardCategoryQueryKeys.all });
  };

  const getRowId = (row: MenuBoardCategory) => row.menuCategoryId.toString();

  const handleRowSelectionChange = (
    updaterOrValue: RowSelectionState | ((old: RowSelectionState) => RowSelectionState),
  ) => {
    const newSelection =
      typeof updaterOrValue === 'function' ? updaterOrValue(rowSelection) : updaterOrValue;

    setRowSelection(newSelection);

    setSelectionCache((prev) => {
      const next = { ...prev };
      categoryList.forEach((item) => {
        const id = getRowId(item);
        if (newSelection[id]) {
          next[id] = item;
        }
      });
      return next;
    });
  };

  const handleDelete = (id: number) => {
    const category = categoryList.find((c) => c.menuCategoryId === id);
    if (!category) {
      return;
    }
    setItemsToDelete([category]);
    setDeleteError(null);
    openModal('delete');
  };

  const confirmDelete = async () => {
    if (itemsToDelete.length === 0 || isDeleting) {
      return;
    }

    try {
      setIsDeleting(true);
      const results = await Promise.allSettled(
        itemsToDelete.map((item) => deleteMenuBoardCategory(item.menuCategoryId)),
      );

      const failed = results.filter((r) => r.status === 'rejected');

      if (failed.length > 0) {
        const firstRejected = failed[0] as PromiseRejectedResult;
        const reason = firstRejected.reason;
        const message =
          isAxiosError(reason) && reason.response?.data?.message
            ? reason.response.data.message
            : t('{{count}} item(s) could not be deleted.', { count: failed.length });
        setDeleteError(message);
        setRowSelection({});
        handleRefresh();
        return;
      }

      setRowSelection({});
      handleRefresh();
      closeModal();
    } catch (err) {
      const message =
        isAxiosError(err) && err.response?.data?.message
          ? err.response.data.message
          : t('Some selected items could not be deleted.');
      setDeleteError(message);
    } finally {
      setIsDeleting(false);
    }
  };

  const handleConfirmCopy = async (name: string, description: string, code: string) => {
    if (!selectedCategory || !menuId) {
      return;
    }

    try {
      setIsCloning(true);
      await copyMenuBoardCategory({
        menuCategoryId: selectedCategory.menuCategoryId,
        name,
        description: description || undefined,
        code: code || undefined,
      });
      notify.success(t('Category copied successfully'));
      handleRefresh();
      closeModal();
    } catch (err) {
      console.error('Copy category failed', err);
      notify.error(t('Failed to copy Category'));
    } finally {
      setIsCloning(false);
    }
  };

  const getAllSelectedItems = (): MenuBoardCategory[] =>
    Object.keys(rowSelection)
      .map((id) => selectionCache[id])
      .filter((item): item is MenuBoardCategory => !!item);

  const columns = getCategoryColumnDefinitions({
    t,
    onEdit: (category) => {
      setSelectedCategory(category);
      openModal('edit');
    },
    onCopy: (category) => {
      setSelectedCategory(category);
      openModal('copy');
    },
    onDelete: handleDelete,
    onViewProducts: (category) => {
      navigate(`/library/menu-boards/${menuId}/categories/${category.menuCategoryId}/products`);
    },
    onPreview: (category) => setPreviewItem(category),
  });

  const bulkActions = getCategoryBulkActions({
    t,
    onDelete: () => {
      const allItems = getAllSelectedItems();
      setItemsToDelete(allItems);
      setDeleteError(null);
      openModal('delete');
    },
  });

  const filterOptions = getCategoryFilterKeys(t);
  const libraryTabs = useFilteredTabs('library');

  const handleResetFilters = () => {
    setFilterInputs(INITIAL_CATEGORY_FILTER_STATE);
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
  };

  return (
    <section className="flex h-full w-full min-h-0 relative outline-none overflow-hidden">
      <div className="flex-1 flex flex-col min-h-0 min-w-0 px-5 pb-5">
        <div className="flex flex-row justify-between py-4 items-center gap-4">
          <TabNav activeTab="Menu Boards" navigation={libraryTabs} />
          <div className="flex items-center gap-2 md:mb-0">
            <Button
              variant="primary"
              className="font-semibold"
              disabled={!isHydrated}
              onClick={() => {
                setSelectedCategory(null);
                openModal('edit');
              }}
              leftIcon={Plus}
            >
              {t('Add Category')}
            </Button>
          </div>
        </div>

        <div className="flex flex-col lg:flex-row justify-between items-center gap-4">
          <div className="w-full lg:flex-1 md:min-w-0">
            <nav className="flex items-center gap-1 text-sm font-medium text-gray-500">
              <button
                className="px-3 py-2 hover:text-gray-900 transition-colors cursor-pointer"
                onClick={() => navigate('/library/menu-boards')}
              >
                {t('Menu Boards')}
              </button>
              <ChevronRight size={24} className="p-1 text-gray-500" />
              <span
                className="px-3 py-2 text-xibo-blue-500 text-sm font-semibold truncate max-w-xs"
                title={menuBoard?.name}
              >
                {menuBoard?.name ?? `${t('Menu Board')} #${menuId}`}
              </span>
            </nav>
          </div>

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
                placeholder={t('Search categories...')}
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
            setFilterInputs((prev) => ({ ...prev, [name]: value }));
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
              <span className="text-gray-400 font-medium">{t('Loading...')}</span>
            </div>
          ) : (
            <DataTable
              columns={columns}
              data={categoryList}
              pageCount={pageCount}
              pagination={pagination}
              onPaginationChange={setPagination}
              sorting={sorting}
              onSortingChange={setSorting}
              globalFilter={globalFilter}
              onGlobalFilterChange={setGlobalFilter}
              loading={isFetching}
              rowSelection={rowSelection}
              onRowSelectionChange={handleRowSelectionChange}
              onRefresh={handleRefresh}
              columnPinning={{ left: ['tableSelection'], right: ['tableActions'] }}
              columnVisibility={columnVisibility}
              onColumnVisibilityChange={setColumnVisibility}
              bulkActions={bulkActions}
              viewMode={null}
              getRowId={getRowId}
              tableLabel={t('Categories')}
            />
          )}
        </div>
      </div>

      <MenuBoardCategoryModals
        menuId={menuId!}
        actions={{
          activeModal,
          closeModal,
          handleRefresh,
          deleteError,
          isDeleting,
          isCloning,
        }}
        selection={{
          selectedCategory,
          itemsToDelete,
          existingNames,
        }}
        handlers={{
          confirmDelete,
          handleConfirmCopy,
        }}
      />

      <MediaPreviewer
        mediaId={previewItem?.mediaId ?? null}
        mediaType={previewItem?.mediaType}
        fileName={previewItem?.name}
        onClose={() => setPreviewItem(null)}
      />
    </section>
  );
}
