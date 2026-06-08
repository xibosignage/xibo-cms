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

import type { ColumnDef, PaginationState, SortingState } from '@tanstack/react-table';
import { FolderPlus, Loader2, RefreshCw } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import Button from '@/components/ui/Button';
import FolderBreadcrumb from '@/components/ui/FolderBreadCrumb';
import { DataTable } from '@/components/ui/table/DataTable';
import type { ActionType } from '@/hooks/useFolderActions';
import type { FolderPermissions } from '@/services/folderApi';
import { fetchFolderById } from '@/services/folderApi';
import type { Folder } from '@/types/folder';

interface FolderSharingEntry {
  name: string;
  isGroup: boolean;
}

interface FolderUsageEntry {
  type: string;
  count: number;
  sizeBytes: number;
  size: string;
}

interface DecoratedFolder extends Folder {
  buttons: FolderPermissions;
  homeFolderCount: number;
  sharing: FolderSharingEntry[];
  usage: FolderUsageEntry[];
}

interface FolderInfoPanelProps {
  folderId: number;
  refreshTrigger: number;
  onCreateFolder: () => void;
  onNavigate: (folder: { id: number; text: string }) => void;
  onAction: (action: ActionType, folder: Folder) => void;
}

export default function FolderInfoPanel({
  folderId,
  refreshTrigger,
  onCreateFolder,
  onNavigate,
  onAction,
}: FolderInfoPanelProps) {
  const { t } = useTranslation();
  const [folder, setFolder] = useState<DecoratedFolder | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isTableLoading, setIsTableLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [localRefresh, setLocalRefresh] = useState(0);

  const [pagination, setPagination] = useState<PaginationState>({ pageIndex: 0, pageSize: 10 });
  const [sorting, setSorting] = useState<SortingState>([]);

  const usageColumns: ColumnDef<FolderUsageEntry>[] = [
    {
      accessorKey: 'type',
      header: t('Section'),
    },
    {
      accessorKey: 'count',
      header: t('Number of Items'),
    },
    {
      accessorKey: 'size',
      header: t('Size'),
      cell: ({ row }) => (row.original.sizeBytes > 0 ? row.original.size : '0 MiB'),
    },
  ];

  useEffect(() => {
    const isRefresh = folder !== null;
    if (isRefresh) {
      setIsTableLoading(true);
    } else {
      setIsLoading(true);
    }
    setError(null);

    const controller = new AbortController();
    (fetchFolderById(folderId, controller.signal) as Promise<DecoratedFolder>)
      .then((data) => {
        setFolder(data);
      })
      .catch((err) => {
        if (!controller.signal.aborted) {
          setError(err instanceof Error ? err.message : t('Failed to load folder details'));
        }
      })
      .finally(() => {
        if (!controller.signal.aborted) {
          setIsLoading(false);
          setIsTableLoading(false);
        }
      });

    return () => controller.abort();
  }, [folderId, refreshTrigger, localRefresh, t]);

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-full">
        <Loader2 className="w-6 h-6 animate-spin text-gray-400" />
      </div>
    );
  }

  if (error || !folder) {
    return (
      <div className="flex items-center justify-center h-full text-sm text-red-500">
        {error || t('Folder not found')}
      </div>
    );
  }

  const childrenRaw = folder.children as unknown;
  const subfolderCount =
    typeof childrenRaw === 'string' && childrenRaw.length > 0
      ? childrenRaw.split(',').length
      : Array.isArray(childrenRaw)
        ? childrenRaw.length
        : 0;
  const totalItems = folder.usage.reduce((sum, entry) => sum + entry.count, 0);

  return (
    <div className="flex flex-col h-full">
      {/* Header */}
      <div className="p-6 pb-0 flex flex-col gap-4">
        <div className="flex items-center justify-between">
          <div className="w-full lg:flex-1 md:min-w-0">
            <FolderBreadcrumb
              currentFolderId={folderId}
              onNavigate={onNavigate}
              isSidebarOpen={true}
              onToggleSidebar={() => {}}
              onAction={onAction}
              refreshTrigger={refreshTrigger}
            />
          </div>
          <Button variant="primary" leftIcon={FolderPlus} onClick={onCreateFolder}>
            {t('New Folder')}
          </Button>
        </div>

        {/* Folder name + stats */}
        <div className="flex items-start justify-between">
          <h2 className="text-xl font-semibold">{folder.text}</h2>
          <div className="flex items-center gap-4 text-sm text-gray-500">
            <div className="text-right p-2 bg-slate-50 rounded-lg">
              <div className="text-xs text-gray-400">{t('Subfolders')}</div>
              <div className="font-semibold text-gray-700">{subfolderCount}</div>
            </div>
            <div className="text-right p-2 bg-slate-50 rounded-lg">
              <div className="text-xs text-gray-400">{t('Items')}</div>
              <div className="font-semibold text-gray-700">{totalItems}</div>
            </div>
            <div className="text-right p-2 bg-slate-50 rounded-lg">
              <div className="text-xs text-gray-400">{t('Used as Home Folder')}</div>
              <div className="font-semibold text-gray-700">{folder.homeFolderCount}</div>
            </div>
          </div>
        </div>

        {/* Shared with */}
        {folder.sharing.length > 0 && (
          <div className="flex items-center gap-2">
            <span className="text-sm text-gray-500">{t('Shared with')}</span>
            <div className="flex flex-wrap gap-1.5">
              {folder.sharing.map((entry, i) => (
                <span
                  key={i}
                  className={twMerge(
                    'text-xs px-2.5 py-1 rounded-full',
                    entry.isGroup ? 'bg-blue-50 text-blue-700' : 'bg-gray-100 text-gray-700',
                  )}
                >
                  {entry.name}
                </span>
              ))}
            </div>
          </div>
        )}
      </div>

      {/* Contents */}
      <div className="flex-1 flex flex-col p-6 min-h-0">
        <div className="flex items-center justify-between mb-3">
          <h3 className="text-gray-500 font-sans text-sm font-semibold leading-normal tracking-tight uppercase">
            {t('Contents')}
          </h3>
          <Button
            variant="tertiary"
            leftIcon={RefreshCw}
            onClick={() => setLocalRefresh((n) => n + 1)}
          >
            {t('Refresh')}
          </Button>
        </div>
        <DataTable
          columns={usageColumns}
          data={folder.usage}
          pageCount={Math.ceil(folder.usage.length / pagination.pageSize)}
          pagination={pagination}
          onPaginationChange={setPagination}
          sorting={sorting}
          onSortingChange={setSorting}
          globalFilter=""
          onGlobalFilterChange={() => {}}
          rowSelection={{}}
          onRowSelectionChange={() => {}}
          enableSelection={false}
          loading={isTableLoading}
          hideToolbar
          getRowId={(row, index) => String(index)}
        />
      </div>
    </div>
  );
}
