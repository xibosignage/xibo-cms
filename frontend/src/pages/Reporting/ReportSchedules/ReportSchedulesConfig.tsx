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

import type { ColumnDef } from '@tanstack/react-table';
import type { TFunction } from 'i18next';
import {
  ArrowLeft,
  Edit,
  ExternalLink,
  PauseCircle,
  PlayCircle,
  RefreshCw,
  Trash2,
} from 'lucide-react';
import type { ComponentProps } from 'react';

import type { FilterConfigItem } from '@/components/ui/FilterInputs';
import type { DataTableBulkAction } from '@/components/ui/table/DataTableBulkActions';
import { ActionsCell, StatusCell, TextCell } from '@/components/ui/table/cells';
import type { ReportSchedule } from '@/types/reportSchedule';
import type { ActionItem } from '@/types/table';
import { formatDateTime } from '@/utils/date';

export interface ReportScheduleFilterInput {
  reportScheduleId: number | null;
  name: string;
  useRegexForName: boolean;
  logicalOperatorName: 'OR' | 'AND';
  userId: string;
  reportName: string;
  onlyMySchedules: string;
}

export type ModalType = 'edit' | 'delete' | 'reset' | 'toggleActive' | 'deleteAllSaved';

export const INITIAL_FILTER_STATE: ReportScheduleFilterInput = {
  reportScheduleId: null,
  name: '',
  useRegexForName: false,
  logicalOperatorName: 'OR',
  userId: '',
  reportName: '',
  onlyMySchedules: '',
};

function formatUnixTimestamp(ts: number): string {
  if (!ts) {
    return '';
  }
  return formatDateTime(new Date(ts * 1000));
}

export const getBaseFilterKeys = (t: TFunction): FilterConfigItem<ReportScheduleFilterInput>[] => [
  {
    label: t('ID'),
    name: 'reportScheduleId',
    type: 'number',
    placeholder: ' ',
  },
  {
    label: t('Name'),
    name: 'name',
    type: 'text',
    placeholder: ' ',
    showAndOr: true,
    andOrKey: 'logicalOperatorName',
    showRegex: true,
    regexKey: 'useRegexForName',
  },
  {
    label: t('Owner'),
    name: 'userId',
    options: [{ label: t('Select Owner'), value: null }],
  },
  {
    label: t('Type'),
    name: 'reportName',
    options: [],
  },
  {
    label: t('Only My Schedules'),
    name: 'onlyMySchedules',
    options: [
      { label: t('All'), value: '' },
      { label: t('Yes'), value: '1' },
    ],
  },
];

export interface ReportScheduleActionsProps {
  t: TFunction;
  reportDescriptionMap: Record<string, string>;
  onDelete: (id: number) => void;
  openEditModal: (row: ReportSchedule) => void;
  openResetModal: (row: ReportSchedule) => void;
  openToggleActiveModal: (row: ReportSchedule) => void;
  onOpenLastSaved: (schedule: ReportSchedule) => void;
  onDeleteAllSaved: (schedule: ReportSchedule) => void;
  onBackToReports: (schedule: ReportSchedule) => void;
}

export const getReportScheduleItemActions = (
  props: ReportScheduleActionsProps,
): ((schedule: ReportSchedule) => ActionItem[]) => {
  const {
    t,
    onDelete,
    openEditModal,
    openResetModal,
    openToggleActiveModal,
    onOpenLastSaved,
    onDeleteAllSaved,
    onBackToReports,
  } = props;
  return (schedule: ReportSchedule) => {
    const isActive = schedule.isActive === 1;
    return [
      {
        label: t('Edit'),
        icon: Edit,
        onClick: () => openEditModal(schedule),
        isQuickAction: true,
        variant: 'primary' as const,
      },
      {
        label: isActive ? t('Pause') : t('Resume'),
        icon: isActive ? PauseCircle : PlayCircle,
        isQuickAction: true,
        onClick: () => openToggleActiveModal(schedule),
      },
      ...(schedule.lastSavedReportId !== 0
        ? [
            {
              label: t('Open Last Report'),
              icon: ExternalLink,
              onClick: () => onOpenLastSaved(schedule),
            },
          ]
        : []),
      {
        label: t('Edit'),
        icon: Edit,
        onClick: () => openEditModal(schedule),
      },
      {
        label: t('Back to Reports'),
        icon: ArrowLeft,
        onClick: () => onBackToReports(schedule),
      },
      {
        label: t('Reset to previous run'),
        icon: RefreshCw,
        onClick: () => openResetModal(schedule),
      },
      { isSeparator: true },
      {
        label: isActive ? t('Pause') : t('Resume'),
        icon: isActive ? PauseCircle : PlayCircle,
        onClick: () => openToggleActiveModal(schedule),
      },
      ...(schedule.lastSavedReportId !== 0
        ? [
            { isSeparator: true as const },
            {
              label: t('Delete All Saved Reports'),
              icon: Trash2,
              onClick: () => onDeleteAllSaved(schedule),
              variant: 'danger' as const,
            },
          ]
        : []),
      { isSeparator: true },
      {
        label: t('Delete'),
        icon: Trash2,
        onClick: () => onDelete(schedule.reportScheduleId),
        variant: 'danger' as const,
      },
    ];
  };
};

export const getReportScheduleColumns = (
  props: ReportScheduleActionsProps,
): ColumnDef<ReportSchedule>[] => {
  const { t, reportDescriptionMap } = props;
  const getActions = getReportScheduleItemActions(props);

  return [
    {
      accessorKey: 'reportScheduleId',
      header: t('ID'),
      size: 80,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },
    {
      accessorKey: 'name',
      header: t('Name'),
      size: 200,
      enableHiding: false,
      cell: (info) => <TextCell weight="bold">{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'reportName',
      header: t('Report Name'),
      size: 160,
      cell: (info) => {
        const internalName = info.getValue<string>();
        return <TextCell>{reportDescriptionMap[internalName] ?? internalName}</TextCell>;
      },
    },
    {
      accessorKey: 'schedule',
      header: t('Schedule'),
      size: 160,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'owner',
      header: t('Owner'),
      size: 140,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'lastRunDt',
      header: t('Last Run'),
      size: 160,
      cell: (info) => <TextCell>{formatUnixTimestamp(info.getValue<number>())}</TextCell>,
    },
    {
      id: 'nextRunDt',
      accessorKey: 'nextRunDt',
      header: t('Next Run'),
      size: 160,
      enableSorting: false,
      cell: (info) => <TextCell>{formatUnixTimestamp(info.getValue<number>())}</TextCell>,
    },
    {
      accessorKey: 'previousRunDt',
      header: t('Previous Run'),
      size: 160,
      cell: (info) => <TextCell>{formatUnixTimestamp(info.getValue<number>())}</TextCell>,
    },
    {
      accessorKey: 'fromDt',
      header: t('Start Time'),
      size: 160,
      cell: (info) => <TextCell>{formatUnixTimestamp(info.getValue<number>())}</TextCell>,
    },
    {
      accessorKey: 'toDt',
      header: t('End Time'),
      size: 160,
      cell: (info) => <TextCell>{formatUnixTimestamp(info.getValue<number>())}</TextCell>,
    },
    {
      accessorKey: 'message',
      header: t('Failed Message'),
      size: 200,
      cell: (info) => <TextCell>{info.getValue<string | null>() ?? ''}</TextCell>,
    },
    {
      accessorKey: 'createdDt',
      header: t('Created'),
      size: 160,
      cell: (info) => <TextCell>{formatUnixTimestamp(info.getValue<number>())}</TextCell>,
    },
    {
      accessorKey: 'isActive',
      header: t('Active'),
      size: 100,
      cell: (info) => {
        const active = info.getValue<number>() === 1;
        return (
          <StatusCell
            label={active ? t('Active') : t('Paused')}
            type={active ? 'success' : 'warning'}
          />
        );
      },
    },
    {
      id: 'tableActions',
      header: '',
      size: 120,
      minSize: 120,
      maxSize: 120,
      enableHiding: false,
      enableSorting: false,
      enableResizing: false,
      cell: ({ row }) => (
        <ActionsCell
          row={row}
          actions={getActions(row.original) as ComponentProps<typeof ActionsCell>['actions']}
        />
      ),
    },
  ];
};

interface GetBulkActionsProps {
  t: TFunction;
  onDelete: () => void;
}

export const getBulkActions = ({
  t,
  onDelete,
}: GetBulkActionsProps): DataTableBulkAction<ReportSchedule>[] => [
  {
    label: t('Delete Selected'),
    icon: Trash2,
    onClick: onDelete,
  },
];
