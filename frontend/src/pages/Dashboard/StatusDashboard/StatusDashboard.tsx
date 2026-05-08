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
import type { TFunction } from 'i18next';
import { ArrowRight, Check, Loader2, Newspaper, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router-dom';

import cloud from '../../../assets/dashboard/cloud-arrow-up.svg';
import display from '../../../assets/dashboard/display.svg';
import server from '../../../assets/dashboard/server.svg';
import users from '../../../assets/dashboard/user.svg';

import BandwidthChart from './components/BandwidthChart';
import type { DisplayChartItem } from './components/DisplayChart';
import DisplayChart from './components/DisplayChart';
import LibraryUsageChart from './components/LibraryUsageChart';
import { useDashboardStats } from './hooks/useDashboardStats';

import Button from '@/components/ui/Button';
import { DataTable } from '@/components/ui/table/DataTable';
import { INITIAL_FILTER_STATE } from '@/pages/Displays/Displays/DisplaysConfig';
import { useDisplaysData } from '@/pages/Displays/Displays/hooks/useDisplaysData';
import type { Display } from '@/types/display';
import { formatRelativeDate } from '@/utils/formatters';

interface StatCardProps {
  icon: string;
  value: ReactNode;
  label: ReactNode;
}

function StatCard({ icon, value, label }: StatCardProps) {
  return (
    <div className="flex items-center space-x-4 rounded-lg border border-gray-200 bg-slate-50 p-4">
      <img src={icon} alt="" className="h-10 w-auto text-xibo-blue-600" />
      <div>
        <div className="text-[20px] font-semibold text-gray-800">{value}</div>
        <div className="text-[16px] text-gray-500">{label}</div>
      </div>
    </div>
  );
}

function getDisplayCount(displayStatus: string | undefined): number {
  if (!displayStatus) return 0;
  const [online, offline] = JSON.parse(displayStatus) as [number, number];
  return online + offline;
}

function parseJsonPair(json: string | undefined): [number, number] {
  if (!json) return [0, 0];
  return JSON.parse(json) as [number, number];
}

interface NewsItem {
  title: string;
  description: string;
  link: string;
  date: string;
  image: string;
}

function decodeHtmlEntities(text: string): string {
  const textarea = document.createElement('textarea');
  textarea.innerHTML = text;
  return textarea.value;
}

function NewsArticle({ news }: { news: NewsItem }) {
  const { t } = useTranslation();

  return (
    <div className="bg-slate-50 p-4 flex flex-col gap-y-3 border-b border-gray-200 last:border-b-0">
      <div className="flex items-end justify-between gap-4">
        <h4 className="text-[16px] font-semibold leading-snug text-gray-800">{news.title}</h4>
        <span className="shrink-0 text-xs text-gray-500 uppercase font-semibold">
          {formatRelativeDate(news.date, t)}
        </span>
      </div>
      <p className="line-clamp-6 text-sm leading-relaxed text-gray-500">
        {decodeHtmlEntities(news.description)}
      </p>
      {news.link && (
        <a
          href={news.link}
          target="_blank"
          rel="noopener noreferrer"
          className="inline-flex items-center gap-1 text-sm text-xibo-blue-600 hover:underline self-end"
        >
          {t('Full Article')} <ArrowRight className="h-3.5 w-3.5" />
        </a>
      )}
    </div>
  );
}

function getDisplayColumns(t: TFunction): ColumnDef<Display>[] {
  return [
    {
      accessorKey: 'display',
      header: t('Display'),
    },
    {
      accessorKey: 'loggedIn',
      header: t('Logged In'),
      cell: ({ getValue }) =>
        getValue<number>() ? (
          <Check className="h-4 w-4 text-green-500" />
        ) : (
          <X className="h-4 w-4 text-red-400" />
        ),
    },
    {
      accessorKey: 'licensed',
      header: t('Authorised'),
      cell: ({ getValue }) =>
        getValue<number>() ? (
          <Check className="h-4 w-4 text-green-500" />
        ) : (
          <X className="h-4 w-4 text-red-400" />
        ),
    },
  ];
}

export default function Dashboard() {
  const { t } = useTranslation();
  const { data, isLoading } = useDashboardStats();

  // Display table state
  const [displayPagination, setDisplayPagination] = useState<PaginationState>({
    pageIndex: 0,
    pageSize: 5,
  });
  const [displaySorting, setDisplaySorting] = useState<SortingState>([]);

  const { data: displayData, isFetching: displaysFetching } = useDisplaysData({
    pagination: displayPagination,
    sorting: displaySorting,
    filter: '',
    advancedFilters: INITIAL_FILTER_STATE,
  });

  const displays = displayData?.rows ?? [];
  const displayPageCount = Math.ceil((displayData?.totalCount ?? 0) / displayPagination.pageSize);
  const displayColumns = getDisplayColumns(t);

  const Loading = <div className="h-4 w-12 animate-pulse rounded bg-gray-200" />;
  const displayCount = isLoading ? Loading : getDisplayCount(data?.displayStatus);
  const librarySize = isLoading ? Loading : (data?.librarySize ?? '-');
  const userCount = isLoading ? Loading : (data?.countUsers ?? '-');

  const [online, offline] = parseJsonPair(data?.displayStatus);
  const [upToDate, notUpToDate] = parseJsonPair(data?.displayMediaStatus);

  const statusData: DisplayChartItem[] = [
    { name: t('Online'), value: online, color: '#0ea5a0' },
    { name: t('Offline'), value: offline, color: '#E5E7EB' },
  ];

  const contentStatusData: DisplayChartItem[] = [
    { name: t('Up-to-date'), value: upToDate, color: '#3b82f6' },
    { name: t('Not up-to-date'), value: notUpToDate, color: '#E5E7EB' },
  ];

  const latestNews = (data?.latestNews ?? []) as NewsItem[];

  return (
    <section className="flex flex-col space-y-5 p-5">
      {/* Top Stats */}
      <div className="grid grid-cols-4 gap-5">
        <StatCard icon={display} value={displayCount} label={t('Active Displays')} />
        <StatCard icon={server} value={librarySize} label={t('Remaining Storage')} />
        <StatCard icon={users} value={userCount} label={t('Active Users')} />
        <StatCard
          icon={cloud}
          value={t('Cloud')}
          label={
            <Button variant="tertiary" rightIcon={ArrowRight} className="p-0">
              {t('Manage Account')}
            </Button>
          }
        />
      </div>

      {/* Charts & News */}
      <div className="grid grid-cols-2 gap-5">
        {/* Display Activity */}
        <div className="rounded-lg flex flex-col border border-gray-200 bg-slate-50 p-5 space-y-8">
          <div className="flex items-center justify-between">
            <h3 className="text-base font-semibold text-gray-800">{t('Display Activity')}</h3>
            <Link
              to="/displays/displays"
              className="flex items-center gap-1 text-sm text-xibo-blue-600 hover:underline"
            >
              {t('Displays page')} <ArrowRight className="h-3.5 w-3.5" />
            </Link>
          </div>
          {isLoading ? (
            <div className="flex flex-col items-center justify-center h-full">
              <Loader2 className="w-8 h-8 animate-spin text-gray-600" />
              <span className="mt-2 text-gray-500">{t('Loading...')}</span>
            </div>
          ) : (
            <div className="grid grid-cols-2 gap-4">
              <DisplayChart data={statusData} label={t('Status')} />
              <DisplayChart data={contentStatusData} label={t('Content Status')} />
            </div>
          )}
        </div>

        {/* Latest News */}
        <div className="rounded-lg border border-gray-200 bg-white p-5 max-h-100 flex flex-col overflow-hidden">
          <div className="mb-4 flex items-center gap-2">
            <Newspaper className="h-5 w-5 text-gray-600" />
            <h3 className="text-base font-semibold text-gray-800">{t('Latest News')}</h3>
          </div>
          {isLoading ? (
            <div className="flex flex-col items-center justify-center h-full">
              <Loader2 className="w-8 h-8 animate-spin text-gray-600" />
              <span className="mt-2 text-gray-500">{t('Loading...')}</span>
            </div>
          ) : (
            <div className="flex flex-col flex-1 overflow-auto">
              {latestNews.map((news) => (
                <NewsArticle key={news.link} news={news} />
              ))}
              {latestNews.length === 0 && (
                <p className="text-sm text-gray-400">{t('No news available.')}</p>
              )}
            </div>
          )}
        </div>
      </div>

      <div className="grid grid-cols-2 gap-5">
        {/* Bandwidth Usage */}
        <div className="rounded-lg flex flex-col border border-gray-200 bg-slate-50 p-5 space-y-8">
          <div className="flex items-center justify-between">
            <h3 className="text-base font-semibold text-gray-800">{t('Bandwidth Usage')}</h3>
            <a
              href="/report/form/bandwidth"
              className="flex items-center gap-1 text-sm text-xibo-blue-600 hover:underline"
            >
              {t('View Full Report')} <ArrowRight className="h-3.5 w-3.5" />
            </a>
          </div>
          {isLoading ? (
            <div className="flex flex-col items-center justify-center h-full">
              <Loader2 className="w-8 h-8 animate-spin text-gray-600" />
              <span className="mt-2 text-gray-500">{t('Loading...')}</span>
            </div>
          ) : data?.bandwidthWidget ? (
            <BandwidthChart
              bandwidthWidget={data.bandwidthWidget}
              bandwidthSuffix={data.bandwidthSuffix}
            />
          ) : (
            <div className="flex h-55 items-center justify-center text-gray-400">
              {t('No bandwidth data available.')}
            </div>
          )}
        </div>

        {/* Library Usage */}
        <div className="rounded-lg flex flex-col border border-gray-200 bg-white p-5 space-y-8">
          <h3 className="text-base font-semibold text-gray-800">{t('Library Usage')}</h3>
          {isLoading ? (
            <div className="flex flex-col items-center justify-center h-full">
              <Loader2 className="w-8 h-8 animate-spin text-gray-600" />
              <span className="mt-2 text-gray-500">{t('Loading...')}</span>
            </div>
          ) : data?.libraryWidgetLabels ? (
            <LibraryUsageChart
              libraryWidgetLabels={data.libraryWidgetLabels}
              libraryWidgetData={data.libraryWidgetData}
              librarySize={data.librarySize}
              librarySuffix={data.librarySuffix}
              libraryLimit={data.libraryLimit}
              libraryLimitSet={data.libraryLimitSet}
            />
          ) : (
            <div className="flex h-55 items-center justify-center text-gray-400">
              {t('No library data available.')}
            </div>
          )}
        </div>
      </div>

      {/* Display Activity Table */}
      <div className="rounded-lg border border-gray-200 bg-white p-5">
        <div className="mb-4 flex items-center justify-between">
          <h3 className="text-base font-semibold text-gray-800">{t('Display Activity')}</h3>
        </div>
        <DataTable
          columns={displayColumns}
          data={displays}
          pageCount={displayPageCount}
          pagination={displayPagination}
          onPaginationChange={setDisplayPagination}
          sorting={displaySorting}
          onSortingChange={setDisplaySorting}
          globalFilter=""
          onGlobalFilterChange={() => {}}
          rowSelection={{}}
          onRowSelectionChange={() => {}}
          loading={displaysFetching}
          hideToolbar
          enableSelection={false}
          viewMode={'table'}
          getRowId={(row) => String(row.displayId)}
        />
      </div>
    </section>
  );
}
