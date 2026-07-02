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
import { Edit, Eye, Trash2 } from 'lucide-react';
import type { ComponentProps } from 'react';

import type { FilterConfigItem } from '@/components/ui/FilterInputs';
import type { DataTableBulkAction } from '@/components/ui/table/DataTableBulkActions';
import { TextCell, ActionsCell, CheckMarkCell } from '@/components/ui/table/cells';
import type { Notification } from '@/types/notification';
import type { ActionItem, BaseModalType } from '@/types/table';
import type { DateLike } from '@/utils/date';

export interface NotificationFilterInput {
  read: number | null;
  type: string;
  releaseDt: string;
}

export type ModalType = BaseModalType | 'add' | 'show' | 'edit' | null;

export const NOTIFICATION_INITIAL_FILTER_STATE: NotificationFilterInput = {
  read: null,
  type: '',
  releaseDt: '',
};

export const getBaseFilterKeys = (t: TFunction): FilterConfigItem<NotificationFilterInput>[] => [
  {
    label: t('Status'),
    name: 'read',
    options: [
      { label: t('All'), value: '' },
      { label: t('My Unread'), value: 0 },
      { label: t('My Read'), value: 1 },
    ],
  },
  {
    label: t('Type'),
    name: 'type',
    options: [
      { label: t('All'), value: '' },
      { label: t('Custom'), value: 'custom' },
      { label: t('DataSet'), value: 'dataset' },
      { label: t('Display'), value: 'display' },
      { label: t('Layout'), value: 'layout' },
      { label: t('Library'), value: 'library' },
      { label: t('Report'), value: 'report' },
      { label: t('Schedule'), value: 'schedule' },
    ],
  },
  {
    label: t('Date'),
    name: 'releaseDt',
    type: 'date',
  },
];

export interface NotificationActionsProps {
  t: TFunction;
  onDelete: (id: number) => void;
  onView: (row: Notification) => void;
  onEdit: (row: Notification) => void;
  formatDateTime: (value: DateLike) => string;
}

export const getNotificationItemActions = ({
  t,
  onDelete,
  onView,
  onEdit,
}: NotificationActionsProps): ((notification: Notification) => ActionItem[]) => {
  return (notification: Notification) => {
    const canEdit = notification.canEdit ?? false;
    const canDelete = notification.canDelete ?? false;
    const actions: ActionItem[] = [];

    if (canEdit) {
      actions.push({
        label: t('Edit'),
        icon: Edit,
        onClick: () => onEdit(notification),
        isQuickAction: true,
        variant: 'primary' as const,
      });
    }

    actions.push({
      label: t('View'),
      icon: Eye,
      onClick: () => onView(notification),
    });

    if (canEdit) {
      actions.push({
        label: t('Edit'),
        icon: Edit,
        onClick: () => onEdit(notification),
      });
    }

    if (canDelete) {
      actions.push({
        label: t('Delete'),
        icon: Trash2,
        onClick: () => onDelete(notification.notificationId!),
        variant: 'danger' as const,
      });
    }

    return actions;
  };
};

export const getNotificationColumns = (
  props: NotificationActionsProps,
): ColumnDef<Notification>[] => {
  const { t, formatDateTime, onView } = props;
  const getActions = getNotificationItemActions(props);

  const formatReleaseDt = (value: number | string | null | undefined): string => {
    if (value === null || value === undefined || value === '') {
      return '';
    }
    const ts = Number(value);
    if (isNaN(ts) || ts === 0) {
      return String(value);
    }
    return formatDateTime(new Date(ts * 1000));
  };

  return [
    {
      accessorKey: 'subject',
      header: t('Subject'),
      size: 360,
      enableHiding: false,
      cell: (info) => (
        <div
          data-notification-id={info.row.original.notificationId}
          role="button"
          tabIndex={0}
          className="cursor-pointer focus:outline-none focus:ring-2 focus:ring-xibo-blue-400 rounded"
          onKeyDown={(e) => {
            if (e.key === 'Enter' || e.key === ' ') {
              e.preventDefault();
              onView(info.row.original);
            }
          }}
        >
          <TextCell weight="bold" truncate>
            {info.getValue<string>()}
          </TextCell>
        </div>
      ),
    },
    {
      accessorKey: 'type',
      header: t('Type'),
      size: 100,
      cell: (info) => {
        const type = info.getValue<string>();
        if (!type) return null;
        return (
          <span className="inline-flex items-center px-2 py-1 rounded-xl border border-gray-400 text-xs capitalize font-medium">
            {type}
          </span>
        );
      },
    },
    {
      accessorKey: 'releaseDt',
      header: t('Date'),
      size: 140,
      enableSorting: true,
      cell: (info) => <TextCell>{formatReleaseDt(info.getValue<number | string>())}</TextCell>,
    },
    {
      accessorKey: 'isInterrupt',
      header: t('Interrupt?'),
      size: 100,
      cell: (info) => <CheckMarkCell active={info.getValue<number>() === 1} />,
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
}

export const getBulkActions = ({
  t,
  onDelete,
}: GetBulkActionsProps): DataTableBulkAction<Notification>[] => {
  return [
    {
      label: t('Delete Selected'),
      icon: Trash2,
      onClick: onDelete,
    },
  ];
};
