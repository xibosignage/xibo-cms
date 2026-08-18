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
import { Edit, Play, Trash2 } from 'lucide-react';
import { type ComponentProps } from 'react';

import type { FilterConfigItem } from '@/components/ui/FilterInputs';
import type { DataTableBulkAction } from '@/components/ui/table/DataTableBulkActions';
import { ActionsCell, CheckMarkCell, StatusCell, TextCell } from '@/components/ui/table/cells';
import type { ActionItem, BaseModalType } from '@/types/table';
import type { Task } from '@/types/task';
import type { UIStatus } from '@/types/uiStatus';
import { type DateLike, formatCmsDateTime } from '@/utils/date';
import { formatDuration } from '@/utils/formatters';

export interface TaskFilterInput {
  name: string | null;
  logicalOperatorName: 'AND' | 'OR' | null;
  useRegexForName: boolean | null;
}

export type ModalType = BaseModalType | 'runNow';

export const INITIAL_FILTER_STATE: TaskFilterInput = {
  name: null,
  logicalOperatorName: null,
  useRegexForName: null,
};

// Status constants matching PHP Task entity
const STATUS_RUNNING = 1;
const STATUS_IDLE = 2;
const STATUS_ERROR = 3;
const STATUS_SUCCESS = 4;
const STATUS_TIMEOUT = 5;

function getTaskStatusLabel(status: number, t: TFunction): string {
  switch (status) {
    case STATUS_RUNNING:
      return t('Running');
    case STATUS_IDLE:
      return t('Idle');
    case STATUS_ERROR:
      return t('Error');
    case STATUS_SUCCESS:
      return t('Success');
    case STATUS_TIMEOUT:
      return t('Timed Out');
    default:
      return t('Unknown');
  }
}

function getTaskStatusType(status: number): UIStatus {
  switch (status) {
    case STATUS_RUNNING:
      return 'info';
    case STATUS_IDLE:
      return 'neutral';
    case STATUS_ERROR:
      return 'danger';
    case STATUS_SUCCESS:
      return 'success';
    case STATUS_TIMEOUT:
      return 'warning';
    default:
      return 'neutral';
  }
}

function formatUnixTimestamp(ts: number, formatDateTime: (value: DateLike) => string): string {
  if (!ts || ts === 0) return '';
  return formatDateTime(new Date(ts * 1000));
}

export const getBaseFilterKeys = (t: TFunction): FilterConfigItem<TaskFilterInput>[] => [
  {
    label: t('Name'),
    placeholder: ' ',
    name: 'name',
    type: 'text',
    showAndOr: true,
    andOrKey: 'logicalOperatorName',
    showRegex: true,
    regexKey: 'useRegexForName',
  },
];

export interface TaskActionsProps {
  t: TFunction;
  formatDateTime?: (value: DateLike) => string;
  onDelete: (taskId: number) => void;
  onEdit: (task: Task) => void;
  onRunNow: (task: Task) => void;
}

export const getTaskItemActions = ({
  t,
  onDelete,
  onEdit,
  onRunNow,
}: TaskActionsProps): ((task: Task) => ActionItem[]) => {
  return (task: Task) => {
    const actions: ActionItem[] = [
      {
        label: t('Run Now'),
        icon: Play,
        onClick: () => onRunNow(task),
        isQuickAction: true,
        variant: 'primary' as const,
      },
      {
        label: t('Run Now'),
        icon: Play,
        onClick: () => onRunNow(task),
      },
    ];

    if (!task.isConfigLocked) {
      actions.push(
        {
          label: t('Edit'),
          icon: Edit,
          onClick: () => onEdit(task),
          isQuickAction: true,
          variant: 'primary' as const,
        },
        {
          label: t('Edit'),
          icon: Edit,
          onClick: () => onEdit(task),
        },
        { isSeparator: true },
        {
          label: t('Delete'),
          icon: Trash2,
          onClick: () => onDelete(task.taskId),
          variant: 'danger' as const,
        },
      );
    }

    return actions;
  };
};

export const getTaskColumns = (props: TaskActionsProps): ColumnDef<Task>[] => {
  const { t } = props;
  const formatDateTime = props.formatDateTime ?? ((value: DateLike) => formatCmsDateTime(value));
  const getActions = getTaskItemActions(props);
  return [
    {
      accessorKey: 'taskId',
      header: t('ID'),
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },
    {
      accessorKey: 'name',
      header: t('Name'),
      enableHiding: false,
      cell: (info) => <TextCell weight="bold">{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'isActive',
      header: t('Active'),
      cell: (info) => <CheckMarkCell active={!!info.getValue<number>()} />,
    },
    {
      accessorKey: 'status',
      header: t('Status'),
      cell: (info) => {
        const status = info.getValue<number>();
        return (
          <StatusCell label={getTaskStatusLabel(status, t)} type={getTaskStatusType(status)} />
        );
      },
      meta: {
        getExportValue: (row) => getTaskStatusLabel(row.status, t),
      },
    },
    {
      id: 'nextRunDt',
      accessorKey: 'nextRunDt',
      header: t('Next Run'),
      enableSorting: false,
      cell: (info) => (
        <TextCell>{formatUnixTimestamp(info.getValue<number>(), formatDateTime)}</TextCell>
      ),
      meta: {
        getExportValue: (row) => formatUnixTimestamp(row.nextRunDt, formatDateTime),
      },
    },
    {
      accessorKey: 'runNow',
      header: t('Run Now'),
      cell: (info) => <CheckMarkCell active={!!info.getValue<number>()} />,
    },
    {
      accessorKey: 'lastRunDt',
      header: t('Last Run'),
      cell: (info) => (
        <TextCell>{formatUnixTimestamp(info.getValue<number>(), formatDateTime)}</TextCell>
      ),
      meta: {
        getExportValue: (row) => formatUnixTimestamp(row.lastRunDt, formatDateTime),
      },
    },
    {
      accessorKey: 'lastRunStatus',
      header: t('Last Status'),
      cell: ({ row }) => (
        <CheckMarkCell
          active={row.original.lastRunStatus === STATUS_SUCCESS}
          title={row.original.lastRunMessage}
        />
      ),
    },
    {
      accessorKey: 'lastRunDuration',
      header: t('Last Duration'),
      cell: ({ row }) => <TextCell>{formatDuration(row.original.lastRunDuration)}</TextCell>,
    },
    {
      id: 'tableActions',
      header: '',
      size: 110,
      minSize: 110,
      maxSize: 110,
      enableHiding: false,
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
  isConfigLocked: boolean;
}

export const getBulkActions = ({
  t,
  onDelete,
  isConfigLocked,
}: GetBulkActionsProps): DataTableBulkAction<Task>[] => {
  if (isConfigLocked) return [];
  return [
    {
      label: t('Delete Selected'),
      icon: Trash2,
      onClick: onDelete,
    },
  ];
};
