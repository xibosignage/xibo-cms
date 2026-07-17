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

import {
  closestCenter,
  DndContext,
  type DragEndEvent,
  PointerSensor,
  useSensor,
  useSensors,
} from '@dnd-kit/core';
import { arrayMove, rectSortingStrategy, SortableContext, useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import type { ColumnDef, OnChangeFn, PaginationState, SortingState } from '@tanstack/react-table';
import { GripVertical, MinusCircle, PlusCircle, Search, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';

import { DataTable } from '@/components/ui/table/DataTable';

interface SearchAssignPanelProps<TItem> {
  assignedItems: TItem[];
  isLoadingAssigned?: boolean;
  onAddItem: (item: TItem) => void;
  onRemoveItem: (item: TItem, index: number) => void;
  onClearAll?: () => void;
  assignedLabel?: string;
  noAssignedText?: string;
  getItemId: (item: TItem) => number | string;
  getItemLabel: (item: TItem) => string;
  sortable?: boolean;
  onReorder?: (items: TItem[]) => void;
  allowMultiple?: boolean;
  getRowKey?: (item: TItem, index: number) => number | string;

  keyword: string;
  onKeywordChange: (keyword: string) => void;
  searchLabel?: string;
  searchPlaceholder?: string;
  extraFilters?: ReactNode;

  columns: ColumnDef<TItem>[];
  searchRows: TItem[];
  pageCount: number;
  rowCount?: number;
  pagination: PaginationState;
  onPaginationChange: OnChangeFn<PaginationState>;
  sorting: SortingState;
  onSortingChange: OnChangeFn<SortingState>;
  isSearching: boolean;
  pageSizeOptions?: number[];

  warningMessage?: string;
}

interface SortableChipProps {
  id: number | string;
  label: string;
  onRemove: () => void;
  removeAriaLabel: string;
}

function SortableChip({ id, label, onRemove, removeAriaLabel }: SortableChipProps) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id,
  });

  return (
    <span
      ref={setNodeRef}
      style={{
        transform: CSS.Translate.toString(transform),
        transition,
        opacity: isDragging ? 0.4 : 1,
      }}
      className="inline-flex items-center justify-center gap-1 rounded-full border border-gray-400 p-1.5"
    >
      <span
        className="cursor-grab active:cursor-grabbing text-gray-400 hover:text-gray-600 touch-none"
        {...listeners}
        {...attributes}
      >
        <GripVertical size={12} />
      </span>
      <span className="px-1 text-[12px] text-gray-800">{label}</span>
      <button
        type="button"
        onClick={onRemove}
        className="flex justify-center items-center size-3.75 bg-gray-200 text-gray-500 hover:text-gray-600 hover:bg-gray-300 rounded-full"
        aria-label={removeAriaLabel}
      >
        <X size={10} />
      </button>
    </span>
  );
}

export function SearchAssignPanel<TItem>({
  assignedItems,
  isLoadingAssigned = false,
  onAddItem,
  onRemoveItem,
  onClearAll,
  assignedLabel,
  noAssignedText,
  getItemId,
  getItemLabel,
  sortable = false,
  onReorder,
  allowMultiple = false,
  getRowKey,
  keyword,
  onKeywordChange,
  searchLabel,
  searchPlaceholder,
  extraFilters,
  columns,
  searchRows,
  pageCount,
  rowCount,
  pagination,
  onPaginationChange,
  sorting,
  onSortingChange,
  isSearching,
  pageSizeOptions,
  warningMessage,
}: SearchAssignPanelProps<TItem>) {
  const { t } = useTranslation();

  const rowKey = getRowKey ?? ((item: TItem) => getItemId(item));

  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 5 } }));

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;
    if (!over || active.id === over.id) {
      return;
    }
    const oldIndex = assignedItems.findIndex((item, index) => rowKey(item, index) === active.id);
    const newIndex = assignedItems.findIndex((item, index) => rowKey(item, index) === over.id);
    if (oldIndex === -1 || newIndex === -1) {
      return;
    }
    onReorder?.(arrayMove(assignedItems, oldIndex, newIndex));
  };

  const handleKeywordChange = (value: string) => {
    onKeywordChange(value);
    onPaginationChange((prev) => ({ ...prev, pageIndex: 0 }));
  };

  const actionColumn: ColumnDef<TItem> = {
    id: '_assign_action',
    header: '',
    size: 20,
    enableSorting: false,
    cell: ({ row }) => {
      const assignedIndex = allowMultiple
        ? -1
        : assignedItems.findIndex((item) => getItemId(item) === getItemId(row.original));
      const isAssigned = assignedIndex !== -1;
      return (
        <div className="flex justify-center">
          <button
            type="button"
            onClick={() =>
              isAssigned ? onRemoveItem(row.original, assignedIndex) : onAddItem(row.original)
            }
            className={`w-5 h-5 rounded-full flex items-center justify-center transition-colors cursor-pointer ${
              isAssigned
                ? 'text-red-600 hover:text-red-800 hover:bg-red-50'
                : 'text-xibo-blue-600 hover:text-xibo-blue-80 hover:bg-xibo-blue-50'
            }`}
            title={isAssigned ? t('Remove') : t('Add')}
          >
            {isAssigned ? <MinusCircle size={20} /> : <PlusCircle size={20} />}
          </button>
        </div>
      );
    },
  };

  const allColumns = [...columns, actionColumn];

  return (
    <div className="flex flex-col gap-4">
      {warningMessage !== undefined && (
        <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700">
          {warningMessage}
        </div>
      )}

      {/* Assigned items zone */}
      <div>
        <h3 className="text-sm font-semibold text-gray-500 mb-2">
          {assignedLabel ?? t('Assigned Items')}
        </h3>
        <div className="flex items-center gap-2 min-h-11 rounded-lg bg-gray-100 px-3 py-2">
          {isLoadingAssigned ? (
            <p className="text-sm text-gray-400 flex-1">{t('Loading\u2026')}</p>
          ) : assignedItems.length === 0 ? (
            <p className="text-sm text-gray-400 flex-1">
              {noAssignedText ?? t('No items assigned.')}
            </p>
          ) : sortable ? (
            <DndContext
              sensors={sensors}
              collisionDetection={closestCenter}
              onDragEnd={handleDragEnd}
            >
              <SortableContext
                items={assignedItems.map((item, index) => rowKey(item, index))}
                strategy={rectSortingStrategy}
              >
                <div className="flex flex-wrap gap-2 flex-1">
                  {assignedItems.map((item, index) => (
                    <SortableChip
                      key={rowKey(item, index)}
                      id={rowKey(item, index)}
                      label={getItemLabel(item)}
                      onRemove={() => onRemoveItem(item, index)}
                      removeAriaLabel={t('Remove {{name}}', { name: getItemLabel(item) })}
                    />
                  ))}
                </div>
              </SortableContext>
            </DndContext>
          ) : (
            <div className="flex flex-wrap gap-2 flex-1">
              {assignedItems.map((item, index) => (
                <span
                  key={rowKey(item, index)}
                  className="inline-flex items-center justify-center gap-1 rounded-full border border-gray-400 p-1.5"
                >
                  <span className="px-1 text-[12px] text-gray-800">{getItemLabel(item)}</span>
                  <button
                    type="button"
                    onClick={() => onRemoveItem(item, index)}
                    className="flex justify-center items-center size-3.75 bg-gray-200 text-gray-500 hover:text-gray-600 hover:bg-gray-300 rounded-full"
                    aria-label={t('Remove {{name}}', { name: getItemLabel(item) })}
                  >
                    <X size={10} />
                  </button>
                </span>
              ))}
            </div>
          )}
          {onClearAll !== undefined && assignedItems.length > 0 && (
            <button
              type="button"
              onClick={onClearAll}
              className="shrink-0 text-xs text-gray-500 hover:text-gray-700 ml-2"
            >
              {t('Clear')}
            </button>
          )}
        </div>
      </div>

      {/* Search input (+ optional extra filters in a flex row) */}
      <div className={extraFilters !== undefined ? 'flex gap-3 items-end' : undefined}>
        <div className={extraFilters !== undefined ? 'flex-1' : undefined}>
          {searchLabel !== undefined && (
            <label
              htmlFor="searchAssignPanelKeyword"
              className="block text-sm font-medium text-gray-700 mb-1"
            >
              {searchLabel}
            </label>
          )}
          <div className="relative flex">
            <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
              <Search className="w-4 h-4 text-gray-400" />
            </div>
            <input
              id="searchAssignPanelKeyword"
              type="text"
              value={keyword}
              onChange={(e) => handleKeywordChange(e.target.value)}
              placeholder={searchPlaceholder ?? t('Search\u2026')}
              className="w-full py-2 px-3 pl-10 h-11 bg-gray-100 rounded-lg text-sm border-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none"
            />
          </div>
        </div>
        {extraFilters}
      </div>

      {/* Results table */}
      <div className="flex flex-col overflow-hidden">
        <DataTable
          columns={allColumns}
          data={searchRows}
          pageCount={pageCount}
          rowCount={rowCount}
          pagination={pagination}
          onPaginationChange={onPaginationChange}
          sorting={sorting}
          onSortingChange={onSortingChange}
          globalFilter=""
          onGlobalFilterChange={() => {}}
          rowSelection={{}}
          onRowSelectionChange={() => {}}
          loading={isSearching}
          enableSelection={false}
          hideToolbar={true}
          pageSizeOptions={pageSizeOptions ?? [5, 10, 25]}
        />
      </div>
    </div>
  );
}
