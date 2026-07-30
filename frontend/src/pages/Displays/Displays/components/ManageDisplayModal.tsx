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

import { Check, Loader2, X } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Bar, BarChart, CartesianGrid, Legend, ResponsiveContainer, XAxis, YAxis } from 'recharts';

import { useBandwidthData, useDisplayManageData } from '../hooks/useDisplayManageData';

import Modal from '@/components/ui/modals/Modal';
import { useUserContext } from '@/context/UserContext';
import DisplayChart from '@/pages/Dashboard/StatusDashboard/components/DisplayChart';
import type { Display } from '@/types/display';
import type {
  ManageDependency,
  ManageLayout,
  ManageMedia,
  ManageWidget,
  ManageWidgetData,
  PlayerFault,
} from '@/types/displayManage';
import { hasFeature } from '@/utils/permissions';

interface ManageDisplayModalProps {
  display: Display;
  onClose: () => void;
}

function CompletionIcon({ complete }: { complete: number }) {
  return complete === 1 ? (
    <Check className="w-4 h-4 text-green-600" />
  ) : (
    <X className="w-4 h-4 text-red-500" />
  );
}

function SectionCard({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="rounded-lg border border-gray-200 overflow-hidden">
      <div className="bg-gray-50 px-4 py-2.5 border-b border-gray-200">
        <h3 className="text-sm font-semibold text-gray-700">{title}</h3>
      </div>
      <div className="overflow-x-auto">{children}</div>
    </div>
  );
}

function EmptyState({ message }: { message: string }) {
  return <p className="px-4 py-3 text-sm text-gray-400 italic text-center">{message}</p>;
}

function DependenciesTable({ data }: { data: ManageDependency[] }) {
  const { t } = useTranslation();
  if (data.length === 0) return <EmptyState message={t('No dependencies')} />;

  return (
    <table className="w-full text-sm">
      <thead className="bg-gray-50 border-b border-gray-200">
        <tr>
          <th className="text-left px-3 py-2 font-semibold text-gray-600">{t('Path')}</th>
          <th className="text-left px-3 py-2 font-semibold text-gray-600">{t('File Type')}</th>
          <th className="text-right px-3 py-2 font-semibold text-gray-600">{t('Bytes')}</th>
          <th className="text-center px-3 py-2 font-semibold text-gray-600">{t('Complete')}</th>
        </tr>
      </thead>
      <tbody>
        {data.map((dep) => (
          <tr
            key={`${dep.path}-${dep.fileType}`}
            className="border-b border-gray-100 last:border-0 hover:bg-gray-50"
          >
            <td className="px-3 py-2 text-gray-700 break-all">{dep.path}</td>
            <td className="px-3 py-2 text-gray-500">{dep.fileType}</td>
            <td className="px-3 py-2 text-gray-500 text-right">{dep.bytesRequested}</td>
            <td className="px-3 py-2 text-center">
              <CompletionIcon complete={dep.complete} />
            </td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}

function LayoutsTable({ data }: { data: ManageLayout[] }) {
  const { t } = useTranslation();
  if (data.length === 0) return <EmptyState message={t('No layouts')} />;

  return (
    <table className="w-full text-sm">
      <thead className="bg-gray-50 border-b border-gray-200">
        <tr>
          <th className="text-left px-3 py-2 font-semibold text-gray-600">{t('ID')}</th>
          <th className="text-left px-3 py-2 font-semibold text-gray-600">{t('Layout')}</th>
          <th className="text-right px-3 py-2 font-semibold text-gray-600">{t('Size')}</th>
          <th className="text-center px-3 py-2 font-semibold text-gray-600">{t('Complete')}</th>
          <th className="text-right px-3 py-2 font-semibold text-gray-600">{t('Bytes')}</th>
        </tr>
      </thead>
      <tbody>
        {data.map((layout) => (
          <tr
            key={layout.itemId}
            className="border-b border-gray-100 last:border-0 hover:bg-gray-50"
          >
            <td className="px-3 py-2 text-gray-500">{layout.itemId}</td>
            <td className="px-3 py-2 text-gray-700">{layout.layout}</td>
            <td className="px-3 py-2 text-gray-500 text-right">{layout.size}</td>
            <td className="px-3 py-2 text-center">
              <CompletionIcon complete={layout.complete} />
            </td>
            <td className="px-3 py-2 text-gray-500 text-right">{layout.bytesRequested}</td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}

function MediaTable({ data }: { data: ManageMedia[] }) {
  const { t } = useTranslation();
  if (data.length === 0) return <EmptyState message={t('No media')} />;

  return (
    <table className="w-full text-sm">
      <thead className="bg-gray-50 border-b border-gray-200">
        <tr>
          <th className="text-left px-3 py-2 font-semibold text-gray-600">{t('ID')}</th>
          <th className="text-left px-3 py-2 font-semibold text-gray-600">{t('Name')}</th>
          <th className="text-left px-3 py-2 font-semibold text-gray-600">{t('Type')}</th>
          <th className="text-right px-3 py-2 font-semibold text-gray-600">{t('Size')}</th>
          <th className="text-center px-3 py-2 font-semibold text-gray-600">{t('Complete')}</th>
          <th className="text-right px-3 py-2 font-semibold text-gray-600">{t('Bytes')}</th>
          <th className="text-center px-3 py-2 font-semibold text-gray-600">{t('Released')}</th>
        </tr>
      </thead>
      <tbody>
        {data.map((m) => (
          <tr key={m.itemId} className="border-b border-gray-100 last:border-0 hover:bg-gray-50">
            <td className="px-3 py-2 text-gray-500">{m.itemId}</td>
            <td className="px-3 py-2 text-gray-700">{m.name}</td>
            <td className="px-3 py-2 text-gray-500">{m.type}</td>
            <td className="px-3 py-2 text-gray-500 text-right">{m.size}</td>
            <td className="px-3 py-2 text-center">
              <CompletionIcon complete={m.complete} />
            </td>
            <td className="px-3 py-2 text-gray-500 text-right">{m.bytesRequested}</td>
            <td className="px-3 py-2 text-center">
              <CompletionIcon complete={m.released} />
            </td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}

function WidgetsTable({ data }: { data: ManageWidget[] }) {
  const { t } = useTranslation();
  if (data.length === 0) return <EmptyState message={t('No widgets')} />;

  return (
    <table className="w-full text-sm">
      <thead className="bg-gray-50 border-b border-gray-200">
        <tr>
          <th className="text-left px-3 py-2 font-semibold text-gray-600">{t('ID')}</th>
          <th className="text-left px-3 py-2 font-semibold text-gray-600">{t('Name')}</th>
          <th className="text-center px-3 py-2 font-semibold text-gray-600">{t('Complete')}</th>
          <th className="text-right px-3 py-2 font-semibold text-gray-600">{t('Bytes')}</th>
        </tr>
      </thead>
      <tbody>
        {data.map((w) => (
          <tr key={w.itemId} className="border-b border-gray-100 last:border-0 hover:bg-gray-50">
            <td className="px-3 py-2 text-gray-500">{w.itemId}</td>
            <td className="px-3 py-2 text-gray-700">{w.widgetName || w.widgetType}</td>
            <td className="px-3 py-2 text-center">
              <CompletionIcon complete={w.complete} />
            </td>
            <td className="px-3 py-2 text-gray-500 text-right">{w.bytesRequested}</td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}

function WidgetDataTable({ data }: { data: ManageWidgetData[] }) {
  const { t } = useTranslation();
  if (data.length === 0) return <EmptyState message={t('No widget data')} />;

  return (
    <table className="w-full text-sm">
      <thead className="bg-gray-50 border-b border-gray-200">
        <tr>
          <th className="text-left px-3 py-2 font-semibold text-gray-600">{t('Widget ID')}</th>
          <th className="text-left px-3 py-2 font-semibold text-gray-600">{t('Name')}</th>
          <th className="text-right px-3 py-2 font-semibold text-gray-600">{t('Bytes')}</th>
        </tr>
      </thead>
      <tbody>
        {data.map((wd) => (
          <tr
            key={`${wd.widgetId}-${wd.widgetType}`}
            className="border-b border-gray-100 last:border-0 hover:bg-gray-50"
          >
            <td className="px-3 py-2 text-gray-500">{wd.widgetId}</td>
            <td className="px-3 py-2 text-gray-700">{wd.widgetName || wd.widgetType}</td>
            <td className="px-3 py-2 text-gray-500 text-right">{wd.bytesRequested}</td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}

function FaultsTable({ data }: { data: PlayerFault[] }) {
  const { t } = useTranslation();
  if (data.length === 0) return <EmptyState message={t('No reported faults')} />;

  return (
    <table className="w-full text-sm">
      <thead className="bg-gray-50 border-b border-gray-200">
        <tr>
          <th className="text-left px-3 py-2 font-semibold text-gray-600">{t('Code')}</th>
          <th className="text-left px-3 py-2 font-semibold text-gray-600">{t('Reason')}</th>
          <th className="text-left px-3 py-2 font-semibold text-gray-600">{t('Date')}</th>
          <th className="text-left px-3 py-2 font-semibold text-gray-600">{t('Expires')}</th>
          <th className="text-left px-3 py-2 font-semibold text-gray-600">{t('Schedule ID')}</th>
          <th className="text-left px-3 py-2 font-semibold text-gray-600">{t('Layout ID')}</th>
          <th className="text-left px-3 py-2 font-semibold text-gray-600">{t('Media ID')}</th>
        </tr>
      </thead>
      <tbody>
        {data.map((fault) => (
          <tr
            key={fault.playerFaultId}
            className="border-b border-gray-100 last:border-0 hover:bg-gray-50"
          >
            <td className="px-3 py-2 text-gray-700">{fault.code}</td>
            <td className="px-3 py-2 text-gray-500">{fault.reason}</td>
            <td className="px-3 py-2 text-gray-500">{fault.incidentDt}</td>
            <td className="px-3 py-2 text-gray-500">{fault.expires}</td>
            <td className="px-3 py-2 text-gray-500">{fault.scheduleId || '-'}</td>
            <td className="px-3 py-2 text-gray-500">{fault.layoutId || '-'}</td>
            <td className="px-3 py-2 text-gray-500">{fault.mediaId || '-'}</td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}

function BandwidthSection({
  displayId,
  defaults,
}: {
  displayId: number;
  defaults: { fromDate: string; toDate: string };
}) {
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
    <SectionCard title={t('Bandwidth')}>
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
              <Bar dataKey="value" name={t('Bandwidth')} fill="#0E70F6" radius={[4, 4, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        )}
        {!isFetching && chartData.length === 0 && (
          <EmptyState message={t('No bandwidth data for the selected period')} />
        )}
      </div>
    </SectionCard>
  );
}

export default function ManageDisplayModal({ display, onClose }: ManageDisplayModalProps) {
  const { t } = useTranslation();
  const { user } = useUserContext();
  const showBandwidth = hasFeature(user, 'displays.reporting');

  const { manageQuery, faultsQuery } = useDisplayManageData(display.displayId);

  const isLoading = manageQuery.isLoading;
  const error = manageQuery.error instanceof Error ? manageQuery.error.message : null;
  const faultsError = faultsQuery.error instanceof Error ? faultsQuery.error.message : null;
  const data = manageQuery.data;
  const faultsRaw = faultsQuery.data;
  const faults = Array.isArray(faultsRaw) ? faultsRaw : [];

  const status = data?.status;
  const countChartData = status
    ? [
        {
          name: t('Downloaded'),
          value: status.countComplete,
          color: '#4ADE80',
        },
        {
          name: t('Pending'),
          value: status.countRemaining,
          color: '#F87171',
        },
      ]
    : [];

  const sizeChartData = status
    ? [
        {
          name: `${t('Downloaded')} (${status.units})`,
          value: status.sizeComplete,
          color: '#4ADE80',
        },
        {
          name: `${t('Pending')} (${status.units})`,
          value: status.sizeRemaining,
          color: '#F87171',
        },
      ]
    : [];

  return (
    <Modal
      isOpen
      onClose={onClose}
      title={`${t('Manage')}: ${display.display}`}
      showCloseButton
      size="xl"
      actions={[{ label: t('Close'), onClick: onClose, variant: 'secondary' }]}
      error={error ?? undefined}
    >
      <div className="p-6 space-y-6">
        {isLoading && (
          <div className="flex items-center justify-center py-16">
            <Loader2 className="w-8 h-8 animate-spin text-gray-400" />
          </div>
        )}

        {data && (
          <>
            {/* File Status Charts */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <SectionCard title={t('File Count')}>
                {(status?.countComplete ?? 0) + (status?.countRemaining ?? 0) > 0 ? (
                  <div className="p-4">
                    <DisplayChart data={countChartData} label={t('Files')} />
                  </div>
                ) : (
                  <EmptyState message={t('No file data available')} />
                )}
              </SectionCard>
              <SectionCard title={t('File Size')}>
                {(status?.sizeComplete ?? 0) + (status?.sizeRemaining ?? 0) > 0 ? (
                  <div className="p-4">
                    <DisplayChart data={sizeChartData} label={status?.units} />
                  </div>
                ) : (
                  <EmptyState message={t('No file data available')} />
                )}
              </SectionCard>
            </div>

            {/* Player Faults */}
            <SectionCard title={t('Reported Player Faults')}>
              {faultsQuery.isLoading ? (
                <div className="flex items-center justify-center py-6">
                  <Loader2 className="w-5 h-5 animate-spin text-gray-400" />
                </div>
              ) : faultsError ? (
                <p className="px-4 py-3 text-sm text-red-600">{faultsError}</p>
              ) : (
                <FaultsTable data={faults} />
              )}
            </SectionCard>

            {/* Dependencies & Layouts */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <SectionCard title={t('Dependencies')}>
                <DependenciesTable data={data.inventory?.dependencies ?? []} />
              </SectionCard>
              <SectionCard title={t('Layouts')}>
                <LayoutsTable data={data.inventory?.layouts ?? []} />
              </SectionCard>
            </div>

            {/* Media & Widgets */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <SectionCard title={t('Media')}>
                <MediaTable data={data.inventory?.media ?? []} />
              </SectionCard>
              <SectionCard title={t('Widgets')}>
                <WidgetsTable data={data.inventory?.widgets ?? []} />
              </SectionCard>
            </div>

            {/* Widget Data */}
            {(data.inventory?.widgetData?.length ?? 0) > 0 && (
              <SectionCard title={t('Widget Data')}>
                <WidgetDataTable data={data.inventory?.widgetData ?? []} />
              </SectionCard>
            )}

            {/* Bandwidth */}
            {showBandwidth && (
              <BandwidthSection displayId={display.displayId} defaults={data.defaults} />
            )}
          </>
        )}
      </div>
    </Modal>
  );
}
