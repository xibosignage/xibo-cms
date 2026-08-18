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
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import type { ModalType, NotificationFilterInput } from './NotificationConfig';
import {
  getBulkActions,
  getBaseFilterKeys,
  getNotificationColumns,
  NOTIFICATION_INITIAL_FILTER_STATE,
} from './NotificationConfig';
import { NotificationModals } from './components/NotificationModals';
import { useNotificationActions } from './hooks/useNotificationActions';
import { useNotificationData } from './hooks/useNotificationData';

import Button from '@/components/ui/Button';
import FilterButton from '@/components/ui/FilterButton';
import FilterInputs from '@/components/ui/FilterInputs';
import QueryStatusBanner from '@/components/ui/QueryStatusBanner';
import { DataTable } from '@/components/ui/table/DataTable';
import { useUserContext } from '@/context/UserContext';
import { useDateFormatter } from '@/hooks/useDateFormatter';
import { useTableState } from '@/hooks/useTableState';
import type { Notification } from '@/types/notification';
import { countActiveFilters } from '@/utils/filters';
import { hasFeature } from '@/utils/permissions';

export default function NotificationCentre() {
  const { t } = useTranslation();
  const { formatDateTime } = useDateFormatter();
  const queryClient = useQueryClient();
  const { user } = useUserContext();
  const canAdd = hasFeature(user, 'notification.add');
  const {
    pagination,
    setPagination,
    sorting,
    setSorting,
    columnVisibility,
    setColumnVisibility,
    filterInputs,
    setFilterInputs,
    isHydrated,
  } = useTableState<NotificationFilterInput>('notification_page', {
    pagination: { pageIndex: 0, pageSize: 10 },
    sorting: [],
    columnVisibility: {
      subject: true,
      type: true,
      releaseDt: true,
      isInterrupt: true,
    },
    viewMode: 'table',
    globalFilter: '',
    filterInputs: NOTIFICATION_INITIAL_FILTER_STATE,
  });

  const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
  const [selectionCache, setSelectionCache] = useState<Record<string, Notification>>({});
  const [openFilter, setOpenFilter] = useState(false);
  const [activeModal, setActiveModal] = useState<ModalType | null>(null);
  const [itemsToDelete, setItemsToDelete] = useState<Notification[]>([]);
  const [selectedNotification, setSelectedNotification] = useState<Notification | null>(null);
  const [selectedEditId, setSelectedEditId] = useState<number | null>(null);

  const openModal = (name: ModalType) => setActiveModal(name);
  const closeModal = () => setActiveModal(null);

  const {
    data: queryData,
    isFetching,
    isError,
    isPaused,
    error: queryError,
  } = useNotificationData({
    pagination,
    sorting,
    filter: '',
    advancedFilters: filterInputs,
    enabled: isHydrated,
  });

  const data = queryData?.rows;
  const pageCount = Math.ceil((queryData?.totalCount || 0) / pagination.pageSize);
  const error = isError && queryError instanceof Error ? queryError.message : '';
  const notificationList = data ?? [];

  const getRowId = (row: Notification) => row.notificationId!.toString();

  const handleRowSelectionChange = (
    updaterOrValue: RowSelectionState | ((old: RowSelectionState) => RowSelectionState),
  ) => {
    const newSelection =
      typeof updaterOrValue === 'function' ? updaterOrValue(rowSelection) : updaterOrValue;

    setRowSelection(newSelection);

    setSelectionCache((prev) => {
      const next = { ...prev };
      notificationList.forEach((item) => {
        const id = getRowId(item);
        if (newSelection[id]) {
          next[id] = item;
        }
      });
      return next;
    });
  };

  const handleRefresh = () => {
    queryClient.invalidateQueries({ queryKey: ['notification'] });
  };

  const { isDeleting, deleteError, setDeleteError, confirmDelete } = useNotificationActions({
    t,
    handleRefresh,
    closeModal,
    setRowSelection,
  });

  const handleDelete = (id: number) => {
    const notification = notificationList.find((n) => n.notificationId === id);
    if (!notification) return;

    setItemsToDelete([notification]);
    setDeleteError(null);
    openModal('delete');
  };

  const handleView = (notification: Notification) => {
    setSelectedNotification(notification);
    openModal('show');
  };

  const handleEdit = (notification: Notification) => {
    setSelectedEditId(notification.notificationId);
    openModal('edit');
  };

  const handleResetFilters = () => {
    setFilterInputs(NOTIFICATION_INITIAL_FILTER_STATE);
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
  };

  const columns = getNotificationColumns({
    t,
    canModify: hasFeature(user, 'notification.modify'),
    onDelete: handleDelete,
    onView: handleView,
    onEdit: handleEdit,
    formatDateTime,
  });

  const getAllSelectedItems = (): Notification[] => {
    return Object.keys(rowSelection)
      .map((id) => selectionCache[id])
      .filter((item): item is Notification => !!item);
  };

  const bulkActions = getBulkActions({
    t,
    onDelete: () => {
      const allItems = getAllSelectedItems();
      setItemsToDelete(allItems);
      setDeleteError(null);
      openModal('delete');
    },
  });

  const filterOptions = getBaseFilterKeys(t);

  const activeFilterCount = countActiveFilters(
    filterInputs,
    NOTIFICATION_INITIAL_FILTER_STATE,
    filterOptions,
  );

  return (
    <section className="flex h-full w-full min-h-0 relative outline-none overflow-hidden">
      <div className="flex-1 flex flex-col min-h-0 min-w-0 px-5 pb-5">
        <div className="flex flex-row justify-between py-4 items-center gap-4">
          <div />
          <div className="flex items-center gap-2">
            {canAdd && (
              <Button
                variant="primary"
                className="font-semibold"
                disabled={!isHydrated}
                onClick={() => openModal('add')}
                leftIcon={Plus}
              >
                {t('Add Notification')}
              </Button>
            )}
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
            setFilterInputs(
              (prev) =>
                ({
                  ...prev,
                  [name]: value,
                }) as NotificationFilterInput,
            );
            setPagination((prev) => ({ ...prev, pageIndex: 0 }));
          }}
          isOpen={openFilter}
          values={filterInputs}
          options={filterOptions}
          onReset={handleResetFilters}
        />

        <QueryStatusBanner error={error} isPaused={isPaused} />

        <div
          className="flex-1 min-h-0 flex flex-col"
          onDoubleClick={(e) => {
            const el = (e.target as Element).closest('[data-notification-id]');
            if (!el) return;
            const id = Number(el.getAttribute('data-notification-id'));
            const notif = notificationList.find((n) => n.notificationId === id);
            if (notif) handleView(notif);
          }}
        >
          {!isHydrated ? (
            <div className="flex-1 flex items-center justify-center bg-gray-50 animate-pulse rounded-lg border border-gray-200">
              <span className="text-gray-400 font-medium">{t('Loading...')}</span>
            </div>
          ) : (
            <DataTable
              columns={columns}
              data={notificationList}
              pageCount={pageCount}
              rowCount={queryData?.totalCount || 0}
              pagination={pagination}
              onPaginationChange={setPagination}
              sorting={sorting}
              onSortingChange={setSorting}
              globalFilter=""
              onGlobalFilterChange={() => {}}
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
            />
          )}
        </div>
      </div>

      <NotificationModals
        actions={{
          activeModal,
          closeModal,
          handleRefresh,
          deleteError,
          isDeleting,
        }}
        selection={{
          selectedNotification,
          itemsToDelete,
          selectedEditId,
        }}
        handlers={{
          confirmDelete,
        }}
      />
    </section>
  );
}
