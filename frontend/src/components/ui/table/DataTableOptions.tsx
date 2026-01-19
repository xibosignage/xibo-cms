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
.*/

import type { Table } from '@tanstack/react-table';
import { Check, ChevronDown, Printer, FileDown, RefreshCw, X } from 'lucide-react';
import { useState, useRef } from 'react';
import { useTranslation } from 'react-i18next';

import Button from '../Button';

import { useClickOutside } from '@/hooks/useClickOutside';

interface DataTableOptionsProps<TData> {
  table: Table<TData>;
  onPrint?: () => void;
  onRefresh?: () => void;
  onCSVExport?: () => void;
}

export function DataTableOptions<TData>({
  table,
  onPrint,
  onRefresh,
  onCSVExport,
}: DataTableOptionsProps<TData>) {
  const { t } = useTranslation();
  const [isOpen, setIsOpen] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);

  useClickOutside(dropdownRef, () => setIsOpen(false));

  const columns = table.getAllLeafColumns().filter((column) => column.getCanHide());

  if (columns.length === 0) {
    return null;
  }

  return (
    <div className="flex gap-3">
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
          <div className="absolute right-0 top-10 w-52 z-50 rounded-lg overflow-hidden bg-white shadow-lg border border-gray-200">
            <div className="relative flex bg-gray-100 px-3 py-2 justify-between items-center">
              <span className="text-gray-500 text-sm font-semibold uppercase leading-normal">
                {t('Visible Columns')}
              </span>

              <div className="flex items-center justify-center size-6 absolute right-[5px] cursor-pointer">
                <X className="size-4 text-gray-500" onClick={() => setIsOpen(false)} />
              </div>
            </div>

            <div className="max-h-[300px] overflow-y-auto space-y-1">
              {columns.map((column) => {
                let label = column.id;
                const header = column.columnDef.header;
                if (typeof header === 'string') {
                  label = header;
                }

                return (
                  <label
                    key={column.id}
                    className="flex items-center gap-x-2.5 py-2 px-3 text-sm text-gray-800 hover:bg-gray-100 cursor-pointer"
                  >
                    <div className="relative flex items-center">
                      <input
                        type="checkbox"
                        checked={column.getIsVisible()}
                        onChange={(e) => column.toggleVisibility(!!e.target.checked)}
                        className="peer relative h-4 w-4 shrink-0 cursor-pointer appearance-none border bg-white checked:border-blue-600 checked:bg-blue-600"
                      />
                      <Check className="pointer-events-none absolute left-1/2 top-1/2 h-3 w-3 -translate-x-1/2 -translate-y-1/2 text-white opacity-0 peer-checked:opacity-100 transition-opacity" />
                    </div>
                    <span className="truncate">{label}</span>
                  </label>
                );
              })}
            </div>
          </div>
        )}
      </div>

      <Button type="button" onClick={onPrint} variant="tertiary" leftIcon={Printer}>
        {t('Print')}
      </Button>
      <Button type="button" onClick={onCSVExport} variant="tertiary" leftIcon={FileDown}>
        {t('CSV')}
      </Button>
      <Button type="button" onClick={onRefresh} variant="tertiary" leftIcon={RefreshCw}>
        {t('Refresh')}
      </Button>
    </div>
  );
}
