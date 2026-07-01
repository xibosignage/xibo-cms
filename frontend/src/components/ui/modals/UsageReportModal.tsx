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

import type { PaginationState, SortingState, ColumnDef, Row } from '@tanstack/react-table';
import type { TFunction } from 'i18next';
import { Palette, Eye } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useLocation, useNavigate } from 'react-router-dom';
import type { NavigateFunction } from 'react-router-dom';

import DatePickerInput from '@/components/ui/forms/DatePickerInput';
import Modal from '@/components/ui/modals/Modal';
import { DataTable } from '@/components/ui/table/DataTable';
import { TextCell, ActionsCell } from '@/components/ui/table/cells';
import {
  fetchMediaUsageDisplays,
  fetchMediaUsageLayouts,
  fetchPlaylistUsageDisplays,
  fetchPlaylistUsageLayouts,
} from '@/services/usageReportApi';
import type { Display } from '@/types/display';
import type { Layout } from '@/types/layout';

interface UsageReportModalProps {
  entityType: 'media' | 'playlist';
  entityId: number;
  entityName: string;
  isOpen?: boolean;
  onClose: () => void;
}

type Tab = 'displays' | 'layouts';

const PAGE_SIZE = 10;

const getDisplaysColumns = (t: TFunction): ColumnDef<Display>[] => [
  {
    accessorKey: 'displayId',
    header: t('ID'),
    size: 60,
    enableSorting: false,
    cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
  },
  {
    accessorKey: 'display',
    header: t('Display'),
    size: 200,
    enableSorting: false,
    cell: (info) => <TextCell weight="bold">{info.getValue<string>()}</TextCell>,
  },
  {
    accessorKey: 'description',
    header: t('Description'),
    size: 250,
    enableSorting: false,
    cell: (info) => <TextCell>{info.getValue<string>() ?? ''}</TextCell>,
  },
];

const getLayoutsColumns = (
  t: TFunction,
  navigate: NavigateFunction,
  from: string,
): ColumnDef<Layout>[] => [
  {
    accessorKey: 'layoutId',
    header: t('ID'),
    size: 60,
    enableSorting: false,
    cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
  },
  {
    accessorKey: 'layout',
    header: t('Layout'),
    size: 200,
    enableSorting: false,
    cell: (info) => <TextCell weight="bold">{info.getValue<string>()}</TextCell>,
  },
  {
    accessorKey: 'description',
    header: t('Description'),
    size: 250,
    enableSorting: false,
    cell: (info) => <TextCell>{info.getValue<string>() ?? ''}</TextCell>,
  },
  {
    id: 'tableActions',
    header: '',
    size: 110,
    minSize: 110,
    maxSize: 110,
    enableResizing: false,
    enableSorting: false,
    cell: (info: { row: Row<Layout> }) => {
      const row = info.row.original;
      const isFullscreen = row.campaignType === 'media' || row.campaignType === 'playlist';
      return (
        <ActionsCell
          row={info.row}
          actions={[
            ...(!isFullscreen
              ? [
                  {
                    label: t('Design'),
                    icon: Palette,
                    isQuickAction: true,
                    onClick: () =>
                      navigate(`/design/layout/${row.layoutId}/editor`, { state: { from } }),
                  } as const,
                ]
              : []),
            {
              label: t('Preview'),
              icon: Eye,
              isQuickAction: true,
              onClick: () => row.previewUrl && window.open(row.previewUrl, '_blank'),
            },
          ]}
        />
      );
    },
  },
];

const slicePage = <T,>(data: T[], pagination: PaginationState): T[] => {
  const { pageIndex, pageSize } = pagination;
  return data.slice(pageIndex * pageSize, (pageIndex + 1) * pageSize);
};

export default function UsageReportModal({
  entityType,
  entityId,
  entityName,
  isOpen = true,
  onClose,
}: UsageReportModalProps) {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const location = useLocation();

  const [activeTab, setActiveTab] = useState<Tab>('displays');

  const [fromDate, setFromDate] = useState('');
  const [toDate, setToDate] = useState('');

  const [displays, setDisplays] = useState<Display[]>([]);
  const [displaysPagination, setDisplaysPagination] = useState<PaginationState>({
    pageIndex: 0,
    pageSize: PAGE_SIZE,
  });
  const [displaysSorting, setDisplaysSorting] = useState<SortingState>([]);
  const [displaysLoading, setDisplaysLoading] = useState(false);

  const [layouts, setLayouts] = useState<Layout[]>([]);
  const [layoutsPagination, setLayoutsPagination] = useState<PaginationState>({
    pageIndex: 0,
    pageSize: PAGE_SIZE,
  });
  const [layoutsSorting, setLayoutsSorting] = useState<SortingState>([]);
  const [layoutsLoading, setLayoutsLoading] = useState(false);

  useEffect(() => {
    if (!entityId) {
      return;
    }

    const controller = new AbortController();

    const load = async () => {
      try {
        setDisplaysLoading(true);
        setDisplaysPagination((prev) => ({ ...prev, pageIndex: 0 }));

        const results =
          entityType === 'media'
            ? await fetchMediaUsageDisplays(entityId, { fromDate, toDate }, controller.signal)
            : await fetchPlaylistUsageDisplays(entityId, { fromDate, toDate }, controller.signal);

        setDisplays(results);
      } catch (err) {
        if (!(err instanceof Error) || err.name !== 'CanceledError') {
          console.error(err);
          setDisplays([]);
        }
      } finally {
        setDisplaysLoading(false);
      }
    };

    load();

    return () => controller.abort();
  }, [entityId, entityType, fromDate, toDate]);

  useEffect(() => {
    if (!entityId || activeTab !== 'layouts') {
      return;
    }

    const controller = new AbortController();

    const load = async () => {
      try {
        setLayoutsLoading(true);
        setLayoutsPagination((prev) => ({ ...prev, pageIndex: 0 }));

        const results =
          entityType === 'media'
            ? await fetchMediaUsageLayouts(entityId, controller.signal)
            : await fetchPlaylistUsageLayouts(entityId, controller.signal);

        setLayouts(results);
      } catch (err) {
        if (!(err instanceof Error) || err.name !== 'CanceledError') {
          console.error(err);
          setLayouts([]);
        }
      } finally {
        setLayoutsLoading(false);
      }
    };

    load();

    return () => controller.abort();
  }, [entityId, entityType, activeTab]);

  const displaysColumns = getDisplaysColumns(t);
  const layoutsColumns = getLayoutsColumns(t, navigate, `${location.pathname}${location.search}`);

  const tabs: { key: Tab; label: string }[] = [
    { key: 'displays', label: t('Displays') },
    { key: 'layouts', label: t('Layouts') },
  ];

  return (
    <Modal
      variant="tabbed"
      isOpen={isOpen}
      title={t('Usage Report for "{{name}}"', { name: entityName })}
      onClose={onClose}
      size="xl"
      scrollable={false}
      actions={[
        {
          label: t('Close'),
          onClick: onClose,
          variant: 'secondary',
        },
      ]}
    >
      <div className="flex flex-col flex-1 min-h-0 overflow-hidden px-4">
        <div role="tablist" className="flex overflow-x-auto shrink-0">
          {tabs.map(({ key, label }) => (
            <button
              key={key}
              role="tab"
              type="button"
              aria-selected={activeTab === key}
              onClick={() => setActiveTab(key)}
              className={`py-2 px-3 inline-flex items-center gap-2 border-b-2 text-sm font-semibold whitespace-nowrap focus:outline-none transition-all ${
                activeTab === key
                  ? 'border-blue-600 text-blue-500'
                  : 'border-gray-200 text-gray-500 hover:text-blue-600'
              }`}
            >
              {label}
            </button>
          ))}
        </div>

        <div
          role="tabpanel"
          aria-labelledby={`tab-${activeTab}`}
          className="flex-1 min-h-0 overflow-hidden flex flex-col"
        >
          {activeTab === 'displays' && (
            <div className="flex flex-col flex-1 min-h-0 pt-4 px-4 gap-3">
              <p className="text-sm text-gray-500 shrink-0">
                {entityType === 'media'
                  ? t(
                      'This media is directly assigned to displays. Direct assignment is where Layouts/Media are assigned to a Display/DisplayGroup without being in a Schedule. If the media is used in scheduled events it is also shown below. To restrict to a specific time enter a date in the filter below.',
                    )
                  : t(
                      'If the playlist is used in scheduled events it is shown below. To restrict to a specific time enter a date in the filter below.',
                    )}
              </p>

              <div className="flex gap-4 shrink-0">
                <div className="flex-1">
                  <DatePickerInput
                    label={t('From Date')}
                    value={fromDate}
                    onChange={setFromDate}
                    optional
                  />
                </div>
                <div className="flex-1">
                  <DatePickerInput
                    label={t('To Date')}
                    value={toDate}
                    onChange={setToDate}
                    optional
                  />
                </div>
              </div>

              <div className="flex-1 min-h-0 flex flex-col">
                <DataTable
                  data={slicePage(displays, displaysPagination)}
                  columns={displaysColumns}
                  pageCount={Math.ceil(displays.length / displaysPagination.pageSize)}
                  pagination={displaysPagination}
                  onPaginationChange={setDisplaysPagination}
                  sorting={displaysSorting}
                  onSortingChange={setDisplaysSorting}
                  globalFilter=""
                  onGlobalFilterChange={() => {}}
                  enableSelection={false}
                  rowSelection={{}}
                  onRowSelectionChange={() => {}}
                  loading={displaysLoading}
                  hideToolbar={true}
                />
              </div>
            </div>
          )}

          {activeTab === 'layouts' && (
            <div className="flex flex-col flex-1 min-h-0 pt-4 px-4">
              <div className="flex-1 min-h-0 flex flex-col">
                <DataTable
                  data={slicePage(layouts, layoutsPagination)}
                  columns={layoutsColumns}
                  pageCount={Math.ceil(layouts.length / layoutsPagination.pageSize)}
                  pagination={layoutsPagination}
                  onPaginationChange={setLayoutsPagination}
                  sorting={layoutsSorting}
                  onSortingChange={setLayoutsSorting}
                  globalFilter=""
                  onGlobalFilterChange={() => {}}
                  enableSelection={false}
                  rowSelection={{}}
                  onRowSelectionChange={() => {}}
                  loading={layoutsLoading}
                  hideToolbar={true}
                />
              </div>
            </div>
          )}
        </div>
      </div>
    </Modal>
  );
}
