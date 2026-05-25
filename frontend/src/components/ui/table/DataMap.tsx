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

import { useReactTable, getCoreRowModel } from '@tanstack/react-table';
import { useTranslation } from 'react-i18next';

import { DataTableOptions } from './DataTableOptions';
import type { ViewMode } from './types';

interface DataMapProps {
  onRefresh?: () => void;
  viewMode: Extract<ViewMode, 'map'>;
  onViewModeChange: (mode: ViewMode) => void;
  availableViewModes?: ViewMode[];
  children: React.ReactNode;
}

export function DataMap({
  onRefresh,
  viewMode,
  onViewModeChange,
  availableViewModes,
  children,
}: DataMapProps) {
  const { t } = useTranslation();

  const table = useReactTable({
    data: [],
    columns: [],
    getCoreRowModel: getCoreRowModel(),
  });

  return (
    <div className="flex flex-col pt-5 gap-y-3 flex-1 min-h-0">
      <div className="flex justify-between data-table-header flex-none">
        <div className="flex items-center gap-3">
          <span className="text-gray-500 font-sans text-sm font-semibold leading-normal tracking-tight uppercase">
            {t('Map View')}
          </span>
        </div>
        <div className="ml-auto">
          <DataTableOptions
            table={table}
            onRefresh={onRefresh}
            viewMode={viewMode}
            onViewModeChange={onViewModeChange}
            availableViewModes={availableViewModes}
          />
        </div>
      </div>

      <div className="isolate flex flex-col flex-1 min-h-0">{children}</div>
    </div>
  );
}
