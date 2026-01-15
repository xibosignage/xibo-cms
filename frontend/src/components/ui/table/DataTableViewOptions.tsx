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
import { Settings2, Check } from 'lucide-react';
import { useState, useRef, useEffect } from 'react';
import { useTranslation } from 'react-i18next';

interface DataTableViewOptionsProps<TData> {
  table: Table<TData>;
}

export function DataTableViewOptions<TData>({ table }: DataTableViewOptionsProps<TData>) {
  const { t } = useTranslation();
  const [isOpen, setIsOpen] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    }
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const columns = table.getAllLeafColumns().filter((column) => column.getCanHide());

  if (columns.length === 0) {
    return null;
  }

  return (
    <div className="relative inline-flex" ref={dropdownRef}>
      <button
        type="button"
        onClick={() => setIsOpen(!isOpen)}
        className="py-2 px-3 inline-flex items-center gap-x-2 bg-white text-gray-800 hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none"
        aria-haspopup="menu"
        aria-expanded={isOpen}
        aria-label={t('Toggle columns')}
      >
        <Settings2 className="w-4 h-4" />
        {t('View')}
      </button>

      {isOpen && (
        <div className="absolute right-0 top-full mt-2 min-w-[200px] z-50">
          <div className="bg-white shadow-md p-2 space-y-1 border ">
            <div className="px-3 py-2 border-b mb-1">
              <span className="text-gray-500">{t('Toggle Columns')}</span>
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
        </div>
      )}
    </div>
  );
}
