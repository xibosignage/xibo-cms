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

import { useQuery } from '@tanstack/react-query';
import {
  AlertCircle,
  ArrowLeft,
  BarChart3,
  FileDown,
  LineChart,
  List,
  Loader2,
} from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate, useParams } from 'react-router-dom';

import SavedReportChart, { getChartIconType, isRenderableChart } from './SavedReportChart';
import TimeConnectedSavedReport from './TimeConnectedSavedReport';

import Button from '@/components/ui/Button';
import TabNav from '@/components/ui/TabNav';
import { useFilteredTabs } from '@/hooks/useFilteredTabs';
import { exportSavedReport, fetchSavedReportData } from '@/services/savedReportApi';
import type { TimeConnectedTable } from '@/services/timeConnectedApi';

type ColumnDef = {
  key: string;
  label: string;
  id?: string;
  format?: (val: unknown) => string;
};
type ColumnConfig =
  | ColumnDef[]
  | ((meta: Record<string, string>, firstRow?: Record<string, unknown>) => ColumnDef[]);

function decodeHtml(val: unknown): string {
  if (val == null || val === '') return '';
  const el = document.createElement('textarea');
  el.innerHTML = String(val);
  return el.value;
}

function formatDuration(val: unknown): string {
  if (val == null || val === '') return '';
  const totalSeconds = Math.floor(Number(val));
  if (isNaN(totalSeconds)) return String(val);
  const days = Math.floor(totalSeconds / 86400);
  const hours = Math.floor((totalSeconds % 86400) / 3600);
  const minutes = Math.floor((totalSeconds % 3600) / 60);
  const seconds = totalSeconds % 60;
  return `${days}day ${hours}hr ${minutes}min ${seconds}sec`;
}

function formatObjectAfter(val: unknown): string {
  if (val == null || val === '') return '';
  if (typeof val === 'object' && !Array.isArray(val)) {
    return Object.entries(val as Record<string, unknown>)
      .map(([k, v]) => `${k}: ${v}`)
      .join(', ');
  }
  if (typeof val !== 'string') return String(val);
  try {
    const parsed = JSON.parse(val);
    if (typeof parsed === 'object' && parsed !== null) {
      return Object.entries(parsed)
        .map(([k, v]) => `${k}: ${v}`)
        .join(', ');
    }
    return String(parsed);
  } catch {
    return val;
  }
}

const REPORT_COLUMN_CONFIG: Record<string, ColumnConfig> = {
  apirequests: (meta) => {
    if (meta.logType === 'audit') {
      return [
        { key: 'logDate', label: 'Date' },
        { key: 'userName', label: 'User Name' },
        { key: 'userId', label: 'User ID' },
        { key: 'applicationName', label: 'Application' },
        { key: 'requestId', label: 'Request ID' },
        { key: 'method', label: 'Method' },
        { key: 'url', label: 'Url' },
        { key: 'entity', label: 'Entity' },
        { key: 'entityId', label: 'Entity ID' },
        { key: 'message', label: 'Message' },
        { key: 'objectAfter', label: 'Details', format: formatObjectAfter },
      ];
    }
    if (meta.logType === 'debug') {
      return [
        { key: 'logDate', label: 'Date' },
        { key: 'userName', label: 'UserName' },
        { key: 'userId', label: 'User ID' },
        { key: 'applicationName', label: 'Application' },
        { key: 'requestId', label: 'Request ID' },
        { key: 'method', label: 'Method' },
        { key: 'url', label: 'Url' },
        { key: 'type', label: 'Level' },
        { key: 'message', label: 'Details' },
      ];
    }
    return [
      { key: 'startTime', label: 'Date' },
      { key: 'userName', label: 'UserName' },
      { key: 'userId', label: 'User ID' },
      { key: 'applicationName', label: 'Application' },
      { key: 'requestId', label: 'Request ID' },
      { key: 'method', label: 'Method' },
      { key: 'url', label: 'Url' },
    ];
  },
  bandwidth: [
    { key: 'label', label: '' },
    { key: 'bandwidth', label: 'Bandwidth' },
    { key: 'unit', label: 'Unit' },
  ],
  displayalerts: [
    { key: 'displayId', label: 'Display ID' },
    { key: 'display', label: 'Display' },
    { key: 'eventType', label: 'Event Type' },
    { key: 'start', label: 'Start' },
    { key: 'end', label: 'End' },
    { key: 'refId', label: 'Reference' },
    { key: 'detail', label: 'Detail' },
  ],
  distributionReport: [
    { key: 'label', label: 'Period' },
    { key: 'duration', label: 'Duration' },
    { key: 'count', label: 'Count' },
  ],
  libraryusage: [
    { key: 'userId', label: 'ID' },
    { key: 'userName', label: 'User' },
    { key: 'bytesUsedFormatted', label: 'Usage' },
    { key: 'numFiles', label: 'Count Files' },
  ],
  proofofplayReport: [
    { key: 'type', label: 'Type' },
    { key: 'displayId', label: 'Display ID' },
    { key: 'display', label: 'Display' },
    { key: 'parentCampaign', label: 'Campaign' },
    { key: 'layoutId', label: 'Layout ID' },
    { key: 'layout', label: 'Layout' },
    { key: 'widgetId', label: 'Widget ID' },
    { key: 'media', label: 'Media' },
    { key: 'tag', label: 'Tag' },
    { key: 'numberPlays', label: 'Number of Plays' },
    { key: 'duration', label: 'Total Duration', format: formatDuration, id: 'duration_fmt' },
    { key: 'duration', label: 'Total Duration (s)', id: 'duration_raw' },
    { key: 'minStart', label: 'First Shown' },
    { key: 'maxEnd', label: 'Last Shown' },
  ],
  sessionhistory: (meta) => {
    if (meta.type === 'audit') {
      return [
        { key: 'logDate', label: 'Date' },
        { key: 'userName', label: 'User Name' },
        { key: 'userId', label: 'User ID' },
        { key: 'ipAddress', label: 'IP Address' },
        { key: 'sessionHistoryId', label: 'Session ID' },
        { key: 'entity', label: 'Entity' },
        { key: 'entityId', label: 'Entity ID' },
        { key: 'message', label: 'Message', format: decodeHtml },
        { key: 'objectAfter', label: 'Object', format: formatObjectAfter },
      ];
    }
    if (meta.type === 'debug') {
      return [
        { key: 'logDate', label: 'Date' },
        { key: 'userName', label: 'UserName' },
        { key: 'userId', label: 'User ID' },
        { key: 'ipAddress', label: 'IP Address' },
        { key: 'sessionHistoryId', label: 'Session ID' },
        { key: 'channel', label: 'Channel' },
        { key: 'function', label: 'Function' },
        { key: 'type', label: 'Level' },
        { key: 'page', label: 'Page' },
        { key: 'message', label: 'Details' },
      ];
    }
    return [
      { key: 'logDate', label: 'Date' },
      { key: 'userName', label: 'User Name' },
      { key: 'userId', label: 'User ID' },
      { key: 'ipAddress', label: 'IP Address' },
      { key: 'sessionHistoryId', label: 'Session ID' },
      { key: 'message', label: 'Message', format: decodeHtml },
      { key: 'objectAfter', label: 'Object', format: formatObjectAfter },
    ];
  },
  summaryReport: [
    { key: 'label', label: 'Period' },
    { key: 'duration', label: 'Duration' },
    { key: 'count', label: 'Count' },
  ],
  timedisconnectedsummary: [
    { key: 'displayId', label: 'Display ID' },
    { key: 'display', label: 'Display' },
    { key: 'timeDisconnected', label: 'Time Disconnected' },
    { key: 'timeConnected', label: 'Time Connected' },
    { key: 'postUnits', label: 'Units' },
  ],
};

export default function SavedReportViewer() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const tabs = useFilteredTabs('reporting');
  const { savedReportId, reportName } = useParams<{ savedReportId: string; reportName: string }>();

  const [viewMode, setViewMode] = useState<'table' | 'chart'>('table');
  const [isExporting, setIsExporting] = useState(false);
  const [exportError, setExportError] = useState<string | null>(null);

  const handleExport = async () => {
    if (!savedReportId || !reportName) return;
    setIsExporting(true);
    setExportError(null);
    try {
      const blob = await exportSavedReport(Number(savedReportId), reportName);
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `${reportName}_${savedReportId}.pdf`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    } catch (err) {
      const message = err instanceof Error ? err.message : t('Export failed. Please try again.');
      setExportError(message);
    } finally {
      setIsExporting(false);
    }
  };

  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['savedReport', 'open', savedReportId, reportName],
    queryFn: () => fetchSavedReportData(Number(savedReportId), reportName!),
    enabled: !!savedReportId && !!reportName,
    staleTime: 1000 * 60 * 5,
  });

  const isTimeConnected = reportName === 'timeconnected' && !Array.isArray(data?.table);
  const tableRows = isTimeConnected ? [] : ((data?.table ?? []) as Record<string, unknown>[]);

  const chartData = data?.chart ?? null;
  const isChartCompatible = isRenderableChart(chartData);

  const metadata = (data?.metadata ?? undefined) as Record<string, string> | undefined;
  const periodStart = metadata?.periodStart;
  const periodEnd = metadata?.periodEnd;

  const firstRow = tableRows[0];
  const resolvedColumns: ColumnDef[] = (() => {
    const config = reportName ? REPORT_COLUMN_CONFIG[reportName] : undefined;
    if (config) {
      return typeof config === 'function' ? config(metadata ?? {}, firstRow) : config;
    }
    return (firstRow !== undefined ? Object.keys(firstRow) : []).map((k) => ({ key: k, label: k }));
  })();

  return (
    <section className="flex h-full w-full min-h-0 relative outline-none overflow-hidden">
      <div className="flex-1 flex flex-col min-h-0 min-w-0 px-5 pb-5">
        <div className="flex flex-row justify-between py-4 items-center gap-4">
          <TabNav activeTab="" navigation={tabs} />
          <button
            onClick={() => void handleExport()}
            disabled={isExporting}
            className="inline-flex items-center gap-1.5 text-sm font-medium text-gray-600 hover:text-gray-900 px-3 py-2 rounded-lg hover:bg-gray-100 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {isExporting ? (
              <Loader2 className="w-4 h-4 animate-spin" />
            ) : (
              <FileDown className="w-4 h-4" />
            )}
            {t('Export as PDF')}
          </button>
        </div>

        <div className="mb-4">
          <Button variant="iconLink" leftIcon={ArrowLeft} onClick={() => navigate(-1)}>
            {t('Back')}
          </Button>
        </div>

        {isLoading && (
          <div className="flex flex-1 items-center justify-center">
            <Loader2 className="w-8 h-8 animate-spin text-gray-400" />
          </div>
        )}

        {isError && !isLoading && (
          <div className="flex flex-1 flex-col items-center justify-center gap-3 bg-red-50 rounded-lg border border-dashed border-red-200 min-h-40">
            <AlertCircle className="w-8 h-8 text-red-400" />
            <p className="text-red-600 text-sm">
              {t('This report could not be loaded. It may have been deleted or corrupted.')}
            </p>
            <Button variant="tertiary" onClick={() => void refetch()}>
              {t('Retry')}
            </Button>
          </div>
        )}

        {data && (
          <>
            {(periodStart || periodEnd) && (
              <div className="mb-4 flex flex-wrap gap-4 text-sm text-gray-600">
                {periodStart && (
                  <span>
                    {t('From')}: <strong className="text-gray-800">{periodStart}</strong>
                  </span>
                )}
                {periodEnd && (
                  <span>
                    {t('To')}: <strong className="text-gray-800">{periodEnd}</strong>
                  </span>
                )}
              </div>
            )}

            {data.error && (
              <div className="mb-4 flex items-start gap-2 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                <AlertCircle className="w-4 h-4 mt-0.5 shrink-0" />
                <span>{data.error}</span>
              </div>
            )}

            {exportError && (
              <div className="mb-4 flex items-start gap-2 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                <AlertCircle className="w-4 h-4 mt-0.5 shrink-0" />
                <span>{exportError}</span>
              </div>
            )}

            <div className="flex bg-slate-50 rounded-lg p-5 flex-col flex-1 min-h-0 border border-slate-200">
              {isChartCompatible && (
                <div className="flex items-center justify-end mb-3 flex-none">
                  <div className="flex items-center rounded-lg bg-gray-50">
                    <Button
                      variant="tertiary"
                      onClick={() => setViewMode('table')}
                      className={
                        viewMode === 'table' ? 'bg-white shadow-sm border border-gray-200' : ''
                      }
                      title={t('Table View')}
                    >
                      <List className="size-4" />
                    </Button>
                    <Button
                      variant="tertiary"
                      onClick={() => setViewMode('chart')}
                      className={
                        viewMode === 'chart' ? 'bg-white shadow-sm border border-gray-200' : ''
                      }
                      title={t('Chart View')}
                    >
                      {getChartIconType(chartData) === 'line' ? (
                        <LineChart className="size-4" />
                      ) : (
                        <BarChart3 className="size-4" />
                      )}
                    </Button>
                  </div>
                </div>
              )}

              <div className="flex-1 min-h-0 overflow-auto">
                {isTimeConnected ? (
                  <TimeConnectedSavedReport table={data.table as unknown as TimeConnectedTable} />
                ) : tableRows.length === 0 && !isChartCompatible && !data.error ? (
                  <div className="flex h-full min-h-40 items-center justify-center">
                    <p className="text-gray-400 text-sm">{t('This report contains no data.')}</p>
                  </div>
                ) : viewMode === 'chart' && isChartCompatible ? (
                  <SavedReportChart chart={chartData} />
                ) : (
                  <div className="overflow-x-auto">
                    <table className="w-full text-sm border-collapse">
                      <thead>
                        <tr className="border-b border-slate-200">
                          {resolvedColumns.map((col) => (
                            <th
                              key={col.id ?? col.key}
                              className="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap bg-slate-50"
                            >
                              {col.label ? t(col.label) : ''}
                            </th>
                          ))}
                        </tr>
                      </thead>
                      <tbody>
                        {tableRows.map((row, i) => (
                          <tr
                            key={i}
                            className="border-b border-slate-100 hover:bg-white transition-colors"
                          >
                            {resolvedColumns.map((col) => {
                              const rawVal = row[col.key];
                              const displayVal = col.format
                                ? col.format(rawVal)
                                : String(rawVal ?? '');
                              return (
                                <td
                                  key={col.id ?? col.key}
                                  className="px-4 py-2.5 text-gray-800 tabular-nums"
                                >
                                  {displayVal}
                                </td>
                              );
                            })}
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </div>
            </div>
          </>
        )}
      </div>
    </section>
  );
}
