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

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import type { RowSelectionState } from '@tanstack/react-table';
import { Filter, FilterX, Plus } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

import type { ModalType } from './EventsConfig';
import {
  getEventColumns,
  getBulkActions,
  INITIAL_FILTER_STATE,
  type EventFilterInput,
} from './EventsConfig';
import {
  DateRangeController,
  type DateRangeControllerState,
} from './components/DateRangeController';
import { DisplayGroupMultiSelect } from './components/DisplayGroupMultiSelect';
import { EventCalendar } from './components/EventCalendar';
import { EventModals } from './components/EventModals';
import { useEventActions } from './hooks/useEventActions';
import { useEventData } from './hooks/useEventData';
import { useEventFilterOptions } from './hooks/useEventFilterOptions';

import Button from '@/components/ui/Button';
import FilterInputs from '@/components/ui/FilterInputs';
import TabNav from '@/components/ui/TabNav';
import { DataCalendar } from '@/components/ui/table/DataCalendar';
import { DataTable } from '@/components/ui/table/DataTable';
import { useUserContext } from '@/context/UserContext';
import { useFilteredTabs } from '@/hooks/useFilteredTabs';
import { useTableState } from '@/hooks/useTableState';
import { fetchUserPreference, saveUserPreference } from '@/services/userApi';
import type { Event } from '@/types/event';

const DATE_RANGE_PREF_KEY = 'event_page_date_range';

export default function Events() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const { user } = useUserContext();
  const timezone = user?.settings?.defaultTimezone ?? 'UTC';

  const {
    pagination,
    setPagination,
    sorting,
    setSorting,
    columnVisibility,
    setColumnVisibility,
    filterInputs,
    setFilterInputs,
    viewMode,
    setViewMode,
    isHydrated,
  } = useTableState<EventFilterInput>('event_page', {
    pagination: { pageIndex: 0, pageSize: 10 },
    sorting: [],
    columnVisibility: {
      eventId: true,
      eventTypeId: true,
      name: true,
      fromDt: true,
      toDt: true,
      event: true,
      campaignId: false,
      displayGroups: false,
      shareOfVoice: false,
      maxPlaysPerHour: false,
      isGeoAware: false,
      recurring: false,
      recurringEventDescription: false,
      recurrenceType: false,
      recurrenceDetail: false,
      recurrenceRepeatsOn: false,
      recurrenceRange: false,
      isPriority: false,
      criteria: true,
      createdOn: false,
      updatedOn: false,
      modifiedByName: false,
    },
    viewMode: 'table',
    globalFilter: '',
    filterInputs: INITIAL_FILTER_STATE,
  });

  const { data: dateRangePrefs, isSuccess: dateRangeReady } = useQuery({
    queryKey: ['userPref', DATE_RANGE_PREF_KEY],
    queryFn: () => fetchUserPreference<DateRangeControllerState>(DATE_RANGE_PREF_KEY),
    staleTime: Infinity,
  });

  const { mutate: saveDateRangePrefs } = useMutation({ mutationFn: saveUserPreference });

  const dateRangeDebounceRef = useRef<NodeJS.Timeout | null>(null);
  const pendingDateRangeState = useRef<DateRangeControllerState | null>(null);

  const flushDateRangePrefs = () => {
    if (dateRangeDebounceRef.current) {
      clearTimeout(dateRangeDebounceRef.current);
      dateRangeDebounceRef.current = null;
    }
    if (pendingDateRangeState.current) {
      const value = pendingDateRangeState.current as unknown as Record<string, unknown>;
      saveDateRangePrefs({ option: DATE_RANGE_PREF_KEY, value });
      queryClient.setQueryData(['userPref', DATE_RANGE_PREF_KEY], pendingDateRangeState.current);
      pendingDateRangeState.current = null;
    }
  };

  useEffect(() => {
    return () => {
      flushDateRangePrefs();
    };
  }, []);

  const handleDateRangeStateChange = (state: DateRangeControllerState) => {
    pendingDateRangeState.current = state;
    if (dateRangeDebounceRef.current) {
      clearTimeout(dateRangeDebounceRef.current);
    }
    dateRangeDebounceRef.current = setTimeout(() => {
      flushDateRangePrefs();
    }, 500);
  };

  const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
  const [selectionCache, setSelectionCache] = useState<Record<string, Event>>({});
  const [openFilter, setOpenFilter] = useState(false);
  const [calendarDate, setCalendarDate] = useState<Date>(() => new Date());

  const [activeModal, setActiveModal] = useState<ModalType | null>(null);

  const [itemsToDelete, setItemsToDelete] = useState<Event[]>([]);
  const [shareEntityIds, setShareEntityIds] = useState<number | number[] | null>(null);
  const [selectedEventId, setSelectedEventId] = useState<number | null>(null);

  const openModal = (name: ModalType) => setActiveModal(name);
  const closeModal = () => setActiveModal(null);

  const {
    data: queryData,
    isFetching,
    isError,
    error: queryError,
  } = useEventData({
    pagination,
    sorting,
    filter: '',
    advancedFilters: filterInputs,
    enabled: isHydrated,
  });

  const data = queryData?.rows ?? [];
  const pageCount = Math.ceil((queryData?.totalCount || 0) / pagination.pageSize);

  const { data: calendarQueryData, isFetching: isCalendarFetching } = useEventData({
    pagination: { pageIndex: 0, pageSize: 500 },
    sorting: [],
    filter: '',
    advancedFilters: filterInputs,
    enabled: isHydrated && viewMode === 'calendar',
  });

  const calendarEvents = calendarQueryData?.rows ?? [];
  const error = isError && queryError instanceof Error ? queryError.message : '';

  const getRowId = (row: Event) => {
    return row.eventId.toString();
  };

  const handleRowSelectionChange = (
    updaterOrValue: RowSelectionState | ((old: RowSelectionState) => RowSelectionState),
  ) => {
    const newSelection =
      typeof updaterOrValue === 'function' ? updaterOrValue(rowSelection) : updaterOrValue;

    setRowSelection(newSelection);

    setSelectionCache((prev) => {
      const next = { ...prev };
      data.forEach((item) => {
        const id = getRowId(item);
        if (newSelection[id]) {
          next[id] = item;
        }
      });
      return next;
    });
  };

  const selectedEvent =
    data.find((m) => m.eventId === selectedEventId) ??
    calendarEvents.find((m) => m.eventId === selectedEventId) ??
    null;
  const existingNames = data.map((m) => m.name ?? '');

  const handleRefresh = () => {
    queryClient.invalidateQueries({ queryKey: ['event'] });
  };

  const {
    isDeleting,
    isCloning,
    deleteError,
    setDeleteError,
    confirmDelete,
    confirmDeleteOccurrence,
    handleConfirmClone,
  } = useEventActions({
    t,
    handleRefresh,
    closeModal,
    setRowSelection,
  });

  const handleDelete = (id: number) => {
    const event = data.find((m) => m.eventId === id);
    if (!event) return;

    setItemsToDelete([event]);
    setDeleteError(null);
    openModal('delete');
  };

  const handleDeleteFromCalendar = (scheduleEvent: Event) => {
    setItemsToDelete([scheduleEvent]);
    setDeleteError(null);
    openModal('delete');
  };

  const openAddEditModal = (scheduleEvent: Event | null) => {
    if (scheduleEvent) {
      setSelectedEventId(scheduleEvent.eventId);
      openModal('edit');
    } else {
      setSelectedEventId(null);
      openModal('schedule');
    }
  };

  const handleResetFilters = () => {
    setFilterInputs((prev) => ({
      ...INITIAL_FILTER_STATE,
      fromDt: prev.fromDt,
      toDt: prev.toDt,
    }));
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
  };

  const openCopyModal = (eventId: number) => {
    setSelectedEventId(eventId);
    openModal('copy');
  };

  const columns = getEventColumns({
    t,
    timezone,
    onDelete: handleDelete,
    openAddEditModal,
    copyEvent: openCopyModal,
  });

  const getAllSelectedItems = (): Event[] => {
    return Object.keys(rowSelection)
      .map((id) => selectionCache[id])
      .filter((item): item is Event => !!item);
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

  const { filterOptions } = useEventFilterOptions(t);

  const libraryTabs = useFilteredTabs('schedule');

  return (
    <section className="flex h-full w-full min-h-0 relative outline-none overflow-hidden">
      <div className="flex-1 flex flex-col min-h-0 min-w-0 px-5 pb-5">
        <div className="flex flex-row justify-between py-4 items-center gap-4">
          <TabNav activeTab="Events" navigation={libraryTabs} />
          <div className="flex items-center gap-2 md:mb-0">
            <Button
              variant="primary"
              className="font-semibold"
              disabled={!isHydrated}
              onClick={() => openModal('schedule')}
              leftIcon={Plus}
            >
              {t('Add Event')}
            </Button>
          </div>
        </div>

        <div className="flex flex-col lg:flex-row justify-between items-center gap-4">
          <DateRangeController
            onDateRangeChange={(fromDt, toDt) => {
              setFilterInputs((prev) => ({
                ...prev,
                fromDt: fromDt ?? null,
                toDt: toDt ?? null,
              }));
              setPagination((prev) => ({ ...prev, pageIndex: 0 }));
            }}
            onDateChange={viewMode === 'calendar' ? setCalendarDate : undefined}
            lockedViewMode={viewMode === 'calendar' ? ('month' as const) : undefined}
            initialState={dateRangePrefs}
            isReady={dateRangeReady}
            onStateChange={handleDateRangeStateChange}
          />
          <div className="flex items-center gap-2 min-w-100 max-w-200 shrink-0">
            <DisplayGroupMultiSelect
              disabled={!isHydrated}
              value={{
                displaySpecificGroupIds: filterInputs.displaySpecificGroupIds ?? [],
                displayGroupIds: filterInputs.displayGroupIds ?? [],
              }}
              onChange={({ displaySpecificGroupIds, displayGroupIds }) => {
                setFilterInputs((prev) => ({
                  ...prev,
                  displaySpecificGroupIds: displaySpecificGroupIds.length
                    ? displaySpecificGroupIds
                    : null,
                  displayGroupIds: displayGroupIds.length ? displayGroupIds : null,
                }));
                setPagination((prev) => ({ ...prev, pageIndex: 0 }));
              }}
            />
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
              } as EventFilterInput;
            });
            setPagination((prev) => {
              return { ...prev, pageIndex: 0 };
            });
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

        <div className="min-h-0 flex flex-col flex-1">
          {!isHydrated ? (
            <div className="flex-1 flex items-center justify-center bg-gray-50 animate-pulse rounded-lg border border-gray-200">
              <span className="text-gray-400 font-medium">
                {t('Loading your events preferences...')}
              </span>
            </div>
          ) : viewMode === 'calendar' ? (
            <DataCalendar
              onRefresh={handleRefresh}
              viewMode="calendar"
              onViewModeChange={setViewMode}
              availableViewModes={['table', 'calendar']}
            >
              <EventCalendar
                date={calendarDate}
                events={calendarEvents}
                isLoading={isCalendarFetching}
                onEditEvent={openAddEditModal}
                onDeleteEvent={handleDeleteFromCalendar}
              />
            </DataCalendar>
          ) : (
            <DataTable
              columns={columns}
              data={data}
              pageCount={pageCount}
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
              viewMode={viewMode}
              onViewModeChange={setViewMode}
              availableViewModes={['table', 'calendar']}
              getRowId={getRowId}
            />
          )}
        </div>
      </div>

      <EventModals
        actions={{
          activeModal,
          closeModal,
          handleRefresh,
          deleteError,
          isDeleting,
          isCloning,
        }}
        selection={{
          selectedEvent,
          selectedEventId,
          itemsToDelete,
          existingNames,
          shareEntityIds,
          setShareEntityIds,
        }}
        handlers={{
          confirmDelete,
          confirmDeleteOccurrence,
          handleConfirmClone: (name) => handleConfirmClone(selectedEvent, name),
        }}
      />
    </section>
  );
}
