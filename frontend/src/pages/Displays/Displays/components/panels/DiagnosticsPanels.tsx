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

// These four panels (Dependencies, Layouts, Widgets, Bandwidth) intentionally
// duplicate the presentational JSX from the legacy Manage modal
// (frontend/src/pages/Displays/Displays/components/ManageDisplayModal.tsx)
// rather than importing it. The legacy modal has no automated test coverage
// and is explicitly required to keep behaving exactly as it does today, so
// extracting its private, unexported render helpers into a shared module
// would mean editing that file — a needless regression risk for a handful of
// small <table> renderers. The DATA layer is reused unmodified: both this
// file and the legacy modal call the same `useDisplayManageData` /
// `useBandwidthData` hooks and the same `/display/manage/{id}` endpoint. The
// three table shells below (Dependencies/Layouts/Widgets) do reuse this
// feature's own SimpleDataTable primitive, so they don't duplicate that
// markup a third/fourth time from each other. Same reasoning covers the
// small CompletionIcon helper just below.

import { ArrowUpDown, Blocks, Check, Files, LayoutTemplate, Loader2, X } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Bar, BarChart, CartesianGrid, Legend, ResponsiveContainer, XAxis, YAxis } from 'recharts';

import { PanelEmptyState, SimpleDataTable, type SimpleDataTableColumn } from './PanelCard';

import Accordion from '@/components/ui/Accordion';
import Badge from '@/components/ui/Badge';
import { useBandwidthData } from '@/pages/Displays/Displays/hooks/useDisplayManageData';
import { BRAND_PRIMARY } from '@/styles/brandColors';
import type { ManageDependency, ManageLayout, ManageWidget } from '@/types/displayManage';

function CompletionIcon({ complete }: { complete: number }) {
  return complete === 1 ? (
    <Check className="w-4 h-4 text-green-600" />
  ) : (
    <X className="w-4 h-4 text-red-500" />
  );
}

// "{complete}/{total}" pill for a sub-accordion's header — green when
// everything's synced, yellow (the same scheme Needs Attention uses
// elsewhere on this page) when anything's still missing. No badge at all
// when there's nothing to count.
function CompletionBadge({ complete, total }: { complete: number; total: number }) {
  if (total === 0) {
    return null;
  }
  return (
    <Badge type={complete === total ? 'success' : 'warning'} className="w-fit">
      {complete}/{total}
    </Badge>
  );
}

export function DependenciesPanel({ data }: { data: ManageDependency[] }) {
  const { t } = useTranslation();
  const complete = data.filter((dep) => dep.complete === 1).length;

  const columns: SimpleDataTableColumn<ManageDependency>[] = [
    {
      key: 'path',
      header: t('Path'),
      cellClassName: 'text-gray-700 break-all',
      cell: (dep) => dep.path,
    },
    { key: 'fileType', header: t('File Type'), cell: (dep) => dep.fileType },
    { key: 'bytes', header: t('Bytes'), align: 'right', cell: (dep) => dep.bytesRequested },
    {
      key: 'complete',
      header: t('Complete'),
      align: 'center',
      cell: (dep) => <CompletionIcon complete={dep.complete} />,
    },
  ];

  return (
    <Accordion
      title={t('Dependencies')}
      icon={Files}
      badge={<CompletionBadge complete={complete} total={data.length} />}
      headerClassName="bg-white hover:bg-gray-50"
      contentClassName="overflow-x-auto"
    >
      {data.length === 0 ? (
        <PanelEmptyState message={t('No dependencies')} />
      ) : (
        <SimpleDataTable
          columns={columns}
          rows={data}
          rowKey={(dep) => `${dep.path}-${dep.fileType}`}
        />
      )}
    </Accordion>
  );
}

export function LayoutsPanel({ data }: { data: ManageLayout[] }) {
  const { t } = useTranslation();
  const complete = data.filter((layout) => layout.complete === 1).length;

  const columns: SimpleDataTableColumn<ManageLayout>[] = [
    { key: 'id', header: t('ID'), cell: (layout) => layout.itemId },
    {
      key: 'layout',
      header: t('Layout'),
      cellClassName: 'text-gray-700',
      cell: (layout) => layout.layout,
    },
    { key: 'size', header: t('Size'), align: 'right', cell: (layout) => layout.size },
    {
      key: 'complete',
      header: t('Complete'),
      align: 'center',
      cell: (layout) => <CompletionIcon complete={layout.complete} />,
    },
    { key: 'bytes', header: t('Bytes'), align: 'right', cell: (layout) => layout.bytesRequested },
  ];

  return (
    <Accordion
      title={t('Layouts')}
      icon={LayoutTemplate}
      badge={<CompletionBadge complete={complete} total={data.length} />}
      headerClassName="bg-white hover:bg-gray-50"
      contentClassName="overflow-x-auto"
    >
      {data.length === 0 ? (
        <PanelEmptyState message={t('No layouts')} />
      ) : (
        <SimpleDataTable columns={columns} rows={data} rowKey={(layout) => layout.itemId} />
      )}
    </Accordion>
  );
}

export function WidgetsPanel({ data }: { data: ManageWidget[] }) {
  const { t } = useTranslation();
  const complete = data.filter((w) => w.complete === 1).length;

  const columns: SimpleDataTableColumn<ManageWidget>[] = [
    { key: 'id', header: t('ID'), cell: (w) => w.itemId },
    {
      key: 'name',
      header: t('Name'),
      cellClassName: 'text-gray-700',
      cell: (w) => w.widgetName || w.widgetType,
    },
    {
      key: 'complete',
      header: t('Complete'),
      align: 'center',
      cell: (w) => <CompletionIcon complete={w.complete} />,
    },
    { key: 'bytes', header: t('Bytes'), align: 'right', cell: (w) => w.bytesRequested },
  ];

  return (
    <Accordion
      title={t('Widgets')}
      icon={Blocks}
      badge={<CompletionBadge complete={complete} total={data.length} />}
      headerClassName="bg-white hover:bg-gray-50"
      contentClassName="overflow-x-auto"
    >
      {data.length === 0 ? (
        <PanelEmptyState message={t('No widgets')} />
      ) : (
        <SimpleDataTable columns={columns} rows={data} rowKey={(w) => w.itemId} />
      )}
    </Accordion>
  );
}

interface BandwidthPanelProps {
  displayId: number;
  defaults: { fromDate: string; toDate: string };
}

export function BandwidthPanel({ displayId, defaults }: BandwidthPanelProps) {
  const { t } = useTranslation();
  const [fromDt, setFromDt] = useState(defaults.fromDate);
  const [toDt, setToDt] = useState(defaults.toDate);

  const { data: bandwidthData, isFetching } = useBandwidthData(displayId, fromDt, toDt, true);

  const chartData =
    bandwidthData?.labels?.map((label, i) => ({
      name: label,
      value: bandwidthData.data[i] ?? 0,
    })) ?? [];

  const suffix = bandwidthData?.postUnits ?? '';

  return (
    <Accordion
      title={t('Bandwidth')}
      icon={ArrowUpDown}
      headerClassName="bg-white hover:bg-gray-50"
    >
      <div className="p-4">
        <div className="flex items-center gap-4 mb-4">
          <label className="text-sm text-gray-600">
            {t('From')}
            <input
              type="date"
              className="ml-2 rounded border border-gray-300 px-2 py-1 text-sm"
              value={fromDt.split(' ')[0] || fromDt}
              onChange={(e) => setFromDt(e.target.value || defaults.fromDate)}
            />
          </label>
          <label className="text-sm text-gray-600">
            {t('To')}
            <input
              type="date"
              className="ml-2 rounded border border-gray-300 px-2 py-1 text-sm"
              value={toDt.split(' ')[0] || toDt}
              onChange={(e) => setToDt(e.target.value || defaults.toDate)}
            />
          </label>
        </div>
        {isFetching && (
          <div className="flex items-center justify-center py-8">
            <Loader2 className="w-6 h-6 animate-spin text-gray-400" />
          </div>
        )}
        {!isFetching && chartData.length > 0 && (
          <ResponsiveContainer width="100%" height={250}>
            <BarChart data={chartData} accessibilityLayer={false}>
              <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#E5E7EB" />
              <XAxis
                dataKey="name"
                axisLine={false}
                tickLine={false}
                tick={{ fontSize: 12, fill: '#9CA3AF' }}
              />
              <YAxis
                axisLine={false}
                tickLine={false}
                tick={{ fontSize: 12, fill: '#9CA3AF' }}
                label={{
                  value: suffix,
                  position: 'insideLeft',
                  angle: -90,
                  style: { fontSize: 12, fill: '#9CA3AF' },
                }}
              />
              <Legend
                iconType="circle"
                iconSize={8}
                formatter={(value: string) => (
                  <span className="text-xs text-gray-600">{value}</span>
                )}
                wrapperStyle={{ paddingTop: 8 }}
              />
              <Bar
                dataKey="value"
                name={t('Bandwidth')}
                fill={BRAND_PRIMARY}
                radius={[4, 4, 0, 0]}
              />
            </BarChart>
          </ResponsiveContainer>
        )}
        {!isFetching && chartData.length === 0 && (
          <PanelEmptyState message={t('No bandwidth data for the selected period')} />
        )}
      </div>
    </Accordion>
  );
}
