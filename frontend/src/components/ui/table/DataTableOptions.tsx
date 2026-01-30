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

import type { Table, VisibilityState } from '@tanstack/react-table';
import { ChevronDown, Printer, FileDown, RefreshCw, X, List, LayoutGrid } from 'lucide-react';
import { useState, useRef } from 'react';
import { useTranslation } from 'react-i18next';

import Button from '../Button';
import Checkbox from '../forms/Checkbox';

import { useClickOutside } from '@/hooks/useClickOutside';

interface DataTableOptionsProps<TData> {
  table: Table<TData>;
  onPrint?: () => void;
  onRefresh?: () => void;
  onCSVExport?: () => void;
  columnVisibility?: VisibilityState;
  viewMode?: 'table' | 'grid';
  onViewModeChange?: (mode: 'table' | 'grid') => void;
}

const getToggleButtonStyle = (active: boolean) => {
  return active ? 'text-xibo-blue-800 bg-gray-100' : 'text-xibo-blue-600 bg-transparent';
};

export function DataTableOptions<TData>({
  table,
  onPrint,
  onRefresh,
  onCSVExport,
  columnVisibility,
  viewMode = 'table',
  onViewModeChange,
}: DataTableOptionsProps<TData>) {
  const { t } = useTranslation();
  const [isOpen, setIsOpen] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);

  useClickOutside(dropdownRef, () => setIsOpen(false));

  const currentVisibility = columnVisibility ?? table.getState().columnVisibility;

  const columns = table.getAllLeafColumns().filter((column) => column.getCanHide());

  const isTableMode = viewMode === 'table';

  return (
    <div className="flex gap-3">
      {columns.length > 0 && isTableMode && (
        <div className="relative" ref={dropdownRef}>
          <Button
            type="button"
            variant="tertiary"
            onClick={() => setIsOpen(!isOpen)}
            rightIcon={ChevronDown}
            aria-haspopup="menu"
            aria-expanded={isOpen}
            aria-label={t('Toggle columns')}
          >
            {t('Columns')}
          </Button>

          {isOpen && (
            <div className="absolute right-0 top-11 w-52 z-50 max-h-[354px] flex flex-col rounded-lg overflow-hidden bg-white shadow-lg border border-gray-200">
              <div className="relative flex bg-gray-100 px-3 py-2 justify-between items-center">
                <span className="text-gray-500 text-sm font-semibold uppercase leading-normal">
                  {t('Visible Columns')}
                </span>
                <div
                  className="flex items-center justify-center size-6 absolute right-[5px] cursor-pointer"
                  onClick={() => setIsOpen(false)}
                >
                  <X className="size-4 text-gray-500" />
                </div>
              </div>

              <div className="overflow-y-auto space-y-1 px-2">
                {columns.map((column) => {
                  let label = column.id;
                  const header = column.columnDef.header;

                  if (typeof header === 'string') {
                    label = header;
                  }

                  const uniqueCheckboxId = `col-toggle-${column.id}`;

                  const isVisible = currentVisibility[column.id] ?? true;

                  return (
                    <Checkbox
                      key={column.id}
                      id={uniqueCheckboxId}
                      label={label}
                      checked={isVisible}
                      className="px-3 py-2.5 gap-4"
                      classNameLabel="m-0 font-semibold text-gray-800"
                      onChange={(e) => {
                        column.toggleVisibility(!!e.target.checked);
                      }}
                    />
                  );
                })}
              </div>
            </div>
          )}
        </div>
      )}

      {/* Show Print and CSV only on table mode */}
      {isTableMode && (
        <>
          <Button type="button" onClick={onPrint} variant="tertiary" leftIcon={Printer}>
            {t('Print')}
          </Button>
          <Button type="button" onClick={onCSVExport} variant="tertiary" leftIcon={FileDown}>
            {t('CSV')}
          </Button>
        </>
      )}

      <Button type="button" onClick={onRefresh} variant="tertiary" leftIcon={RefreshCw}>
        {t('Refresh')}
      </Button>

      {onViewModeChange && (
        <div className="flex items-center rounded-lg bg-gray-50">
          <Button
            variant="tertiary"
            onClick={() => onViewModeChange('table')}
            className={getToggleButtonStyle(isTableMode)}
            title={t('Table View')}
          >
            <List className="size-4" />
          </Button>
          <Button
            variant="tertiary"
            onClick={() => onViewModeChange('grid')}
            className={getToggleButtonStyle(!isTableMode)}
            title={t('Grid View')}
          >
            <LayoutGrid className="size-4" />
          </Button>
        </div>
      )}
    </div>
  );
}
