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

import type { TFunction } from 'i18next';
import {
  CalendarClock,
  Download,
  FileBarChart,
  FileBarChart2,
  FileClock,
  FileCode2,
  FileCog,
  Filter,
  FilterX,
  Gauge,
  HardDrive,
  Loader2,
  MonitorX,
  PlayCircle,
  type LucideIcon,
} from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';

import { INITIAL_FILTER_STATE, getFilterKeys } from './AllReportsConfig';
import type { ReportFilterInput } from './AllReportsConfig';
import ExportStatisticsModal from './ExportStatisticsModal';
import { useAllReportsData } from './hooks/useAllReportsData';

import Button from '@/components/ui/Button';
import FilterInputs from '@/components/ui/FilterInputs';
import TabNav from '@/components/ui/TabNav';
import { useFilteredTabs } from '@/hooks/useFilteredTabs';
import { useTableState } from '@/hooks/useTableState';
import type { Report } from '@/types/report';

const LUCIDE_ICON_MAP: Record<string, LucideIcon> = {
  CalendarClock,
  FileBarChart,
  FileBarChart2,
  FileClock,
  FileCode2,
  FileCog,
  Gauge,
  HardDrive,
  MonitorX,
  PlayCircle,
};

interface StaticCard {
  key: string;
  description: string;
  type: string;
  typeLabel: string;
  icon: LucideIcon;
  onClick: () => void;
}

function buildStaticCards(
  t: TFunction,
  onOpenExportModal: () => void,
): Record<string, StaticCard[]> {
  return {
    'Proof of Play': [
      {
        key: 'export-statistics',
        description: t('Export Statistics'),
        type: 'Export',
        typeLabel: t('Export'),
        icon: Download,
        onClick: onOpenExportModal,
      },
    ],
  };
}

function ReportCardBase({
  icon: Icon,
  description,
  typeLabel,
  onClick,
}: {
  icon: LucideIcon;
  description: string;
  typeLabel: string;
  onClick: () => void;
}) {
  return (
    <div
      className="flex items-center min-h-28 px-8 py-4 gap-4 rounded-lg border bg-slate-50 border-gray-200 cursor-pointer hover:bg-gray-100 hover:border-xibo-blue-600 drop-shadow-xs"
      onClick={onClick}
      role="link"
      tabIndex={0}
      onKeyDown={(e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          onClick();
        }
      }}
    >
      <div
        className={
          'flex h-11.5 w-11.5 items-center justify-center rounded-full bg-blue-100 text-blue-800'
        }
      >
        <Icon size={24} />
      </div>
      <div className="flex flex-col">
        <span className="text-lg font-semibold text-gray-800">{description}</span>
        <Button className="h-auto justify-start p-0" variant="link">
          {typeLabel}
        </Button>
      </div>
    </div>
  );
}

function ReportCard({ report }: { report: Report }) {
  const { t } = useTranslation();
  const navigate = useNavigate();
  return (
    <ReportCardBase
      icon={LUCIDE_ICON_MAP[report.lucide_icon] ?? FileBarChart}
      description={report.description}
      typeLabel={
        report.type === 'Export' ? t('Export') : t('View {{type}}', { type: t(report.type) })
      }
      onClick={() => {
        if (report.prototype_url) {
          // React report inside this SPA (mounted at basename /prototype), navigate
          // client-side so the app/sidebar isn't torn down and rebooted.
          navigate(report.prototype_url.replace(/^\/prototype/, ''));
        } else {
          // Legacy Twig report lives outside the SPA, so a full navigation is required.
          window.location.href = `/report/form/${report.name}`;
        }
      }}
    />
  );
}

function StaticReportCard({ card }: { card: StaticCard }) {
  return (
    <ReportCardBase
      icon={card.icon}
      description={card.description}
      typeLabel={card.typeLabel}
      onClick={card.onClick}
    />
  );
}

export default function AllReports() {
  const { t } = useTranslation();
  const tabs = useFilteredTabs('reporting');

  const { filterInputs, setFilterInputs, isHydrated } = useTableState<ReportFilterInput>(
    'all_reports_page',
    {
      pagination: { pageIndex: 0, pageSize: 10 },
      sorting: [],
      columnVisibility: {},
      viewMode: 'table',
      globalFilter: '',
      filterInputs: INITIAL_FILTER_STATE,
    },
  );

  const { data, isLoading, isError } = useAllReportsData(isHydrated);

  const [openFilter, setOpenFilter] = useState(false);
  const [showExportModal, setShowExportModal] = useState(false);

  const filterOptions = getFilterKeys(t);
  const allStaticCards = buildStaticCards(t, () => setShowExportModal(true));

  function handleResetFilters() {
    setFilterInputs(INITIAL_FILTER_STATE);
  }

  return (
    <section className="flex h-full w-full min-h-0 relative outline-none overflow-hidden">
      <div className="flex-1 flex flex-col min-h-0 min-w-0 px-5 pb-5 overflow-y-auto">
        <div className="flex flex-row justify-between py-4 items-center gap-4">
          <TabNav activeTab={t('All Reports')} navigation={tabs} />
        </div>

        <div className="flex flex-col items-end">
          <Button
            leftIcon={!openFilter ? Filter : FilterX}
            variant="secondary"
            onClick={() => setOpenFilter((prev) => !prev)}
            removeTextOnMobile
          >
            {t('Filters')}
          </Button>
        </div>

        <FilterInputs
          onChange={(name, value) => {
            setFilterInputs((prev) => ({ ...prev, [name]: value ?? '' }));
          }}
          isOpen={openFilter}
          values={filterInputs}
          options={filterOptions}
          onReset={handleResetFilters}
        />

        {isLoading && (
          <div className="flex items-center justify-center h-64">
            <Loader2 className="animate-spin text-gray-400" size={32} />
          </div>
        )}

        {isError && (
          <div className="p-6 text-red-600">{t('Failed to load reports. Please try again.')}</div>
        )}

        {data && (
          <div className="flex flex-col gap-5 mt-5">
            {Object.entries(data).map(([category, reports]) => {
              if (filterInputs.reportType && category !== filterInputs.reportType) {
                return null;
              }

              const nameQuery = filterInputs.name.toLowerCase();

              let visibleReports = reports.filter((r) => r.hidden === 0);
              if (nameQuery) {
                visibleReports = visibleReports.filter((r) =>
                  r.description.toLowerCase().includes(nameQuery),
                );
              }
              if (filterInputs.actionType) {
                visibleReports = visibleReports.filter((r) => r.type === filterInputs.actionType);
              }

              let categoryStaticCards = allStaticCards[category] ?? [];
              if (nameQuery) {
                categoryStaticCards = categoryStaticCards.filter((c) =>
                  c.description.toLowerCase().includes(nameQuery),
                );
              }
              if (filterInputs.actionType) {
                categoryStaticCards = categoryStaticCards.filter(
                  (c) => c.type === filterInputs.actionType,
                );
              }

              if (visibleReports.length === 0 && categoryStaticCards.length === 0) {
                return null;
              }

              return (
                <div key={category} className="flex flex-col gap-3">
                  <h3 className="text-sm font-semibold text-gray-500 uppercase">{t(category)}</h3>
                  <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-2 xl:grid-cols-3">
                    {visibleReports.map((report) => (
                      <ReportCard key={report.name} report={report} />
                    ))}
                    {categoryStaticCards.map((card) => (
                      <StaticReportCard key={card.key} card={card} />
                    ))}
                  </div>
                  <div className="bg-gray-200 h-px mt-2"></div>
                </div>
              );
            })}
          </div>
        )}
      </div>

      <ExportStatisticsModal isOpen={showExportModal} onClose={() => setShowExportModal(false)} />
    </section>
  );
}
