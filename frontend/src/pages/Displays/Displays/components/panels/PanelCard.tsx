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

import { type LucideIcon } from 'lucide-react';
import { twMerge } from 'tailwind-merge';

interface PanelCardProps {
  title: string;
  /** Optional leading icon next to the title, e.g. a topic icon (faults, storage, etc). */
  icon?: LucideIcon;
  children: React.ReactNode;
  className?: string;
  /** Overrides the header row's default bg-gray-50 (e.g. a tinted "helper box" panel). */
  headerClassName?: string;
  /** Overrides the body wrapper's background (plain by default). */
  bodyClassName?: string;
  /** Right-aligned control(s) in the header row, e.g. a pause/resume toggle. */
  headerActions?: React.ReactNode;
}

/**
 * Shared card chrome for the Manage page's panels. Deliberately not reused by the legacy
 * ManageDisplayModal's private SectionCard/EmptyState — that modal has no automated test coverage
 * and is required to keep behaving exactly as it does today, so its internals stay untouched (see
 * DiagnosticsPanels.tsx's file-level comment for the full rationale).
 */
export function PanelCard({
  title,
  icon: Icon,
  children,
  className,
  headerClassName,
  bodyClassName,
  headerActions,
}: PanelCardProps) {
  return (
    <div
      className={twMerge(
        'flex h-full flex-col rounded-lg border border-gray-200 overflow-hidden',
        className,
      )}
    >
      <div
        className={twMerge(
          'bg-gray-50 px-4 py-2.5 border-b border-gray-200 flex items-center justify-between gap-3 shrink-0',
          headerClassName,
        )}
      >
        <h3 className="flex items-center gap-1.5 text-sm font-semibold text-gray-700">
          {Icon && <Icon className="size-3.5 shrink-0 text-gray-400" aria-hidden="true" />}
          {title}
        </h3>
        {headerActions}
      </div>
      <div className={twMerge('flex flex-1 min-h-0 flex-col overflow-x-auto', bodyClassName)}>
        {children}
      </div>
    </div>
  );
}

export function PanelEmptyState({ message }: { message: string }) {
  return <p className="px-4 py-3 text-sm text-gray-400 italic text-center">{message}</p>;
}

export interface SimpleDataTableColumn<T> {
  key: string;
  header: string;
  align?: 'left' | 'center' | 'right';
  /** Extra classes for this column's <td> — e.g. 'text-gray-700' for the row's primary/identifying value, or 'break-all' for long paths. Merged with the column's alignment and the default text-gray-500. */
  cellClassName?: string;
  cell: (row: T) => React.ReactNode;
}

interface SimpleDataTableProps<T> {
  columns: SimpleDataTableColumn<T>[];
  rows: T[];
  rowKey: (row: T) => React.Key;
}

const ALIGN_CLASSES: Record<'left' | 'center' | 'right', string> = {
  left: 'text-left',
  center: 'text-center',
  right: 'text-right',
};

/**
 * Small, unpaginated/unsorted data table shared by the Manage page's panels
 * (Active Faults, Dependencies, Layouts, Widgets) — these all rendered the
 * identical <table>/<thead>/<tbody> shell with only the columns differing,
 * so that shell now lives here once instead of four times.
 */
export function SimpleDataTable<T>({ columns, rows, rowKey }: SimpleDataTableProps<T>) {
  return (
    <table className="w-full text-sm">
      <thead className="bg-gray-50 border-b border-gray-200">
        <tr>
          {columns.map((column) => (
            <th
              key={column.key}
              className={twMerge(
                'px-3 py-2 font-semibold text-gray-600',
                ALIGN_CLASSES[column.align ?? 'left'],
              )}
            >
              {column.header}
            </th>
          ))}
        </tr>
      </thead>
      <tbody>
        {rows.map((row) => (
          <tr key={rowKey(row)} className="border-b border-gray-100 last:border-0 hover:bg-gray-50">
            {columns.map((column) => (
              <td
                key={column.key}
                className={twMerge(
                  'px-3 py-2 text-gray-500',
                  ALIGN_CLASSES[column.align ?? 'left'],
                  column.cellClassName,
                )}
              >
                {column.cell(row)}
              </td>
            ))}
          </tr>
        ))}
      </tbody>
    </table>
  );
}

interface PanelFieldProps {
  label: string;
  value: React.ReactNode;
  /** Optional leading icon next to the field's label. */
  icon?: LucideIcon;
}

export function PanelField({ label, value, icon: Icon }: PanelFieldProps) {
  return (
    <div className="flex flex-col gap-1 min-w-0">
      <span className="flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-gray-400">
        {Icon && <Icon className="size-3 shrink-0" aria-hidden="true" />}
        {label}
      </span>
      <span className="text-sm text-gray-700 break-words">{value}</span>
    </div>
  );
}
