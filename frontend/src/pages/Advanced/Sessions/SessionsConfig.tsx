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
import { LogOut } from 'lucide-react';
import { type ComponentProps } from 'react';

import type { FilterConfigItem } from '@/components/ui/FilterInputs';
import type { DataTableBulkAction } from '@/components/ui/table/DataTableBulkActions';
import { TextCell, ActionsCell, CheckMarkCell } from '@/components/ui/table/cells';
import { getCommonFormOptions } from '@/config/commonForms';
import type { Session } from '@/types/session';
import type { ActionItem, BaseModalType } from '@/types/table';

export interface SessionFilterInput {
  type?: string | null;
  lastModified?: string;
}

export type ModalType = BaseModalType | null;

export const INITIAL_FILTER_STATE: SessionFilterInput = {
  type: '',
  lastModified: '',
};

export const getBaseFilterKeys = (t: TFunction): FilterConfigItem<SessionFilterInput>[] => [
  {
    label: t('From Date'),
    name: 'lastModified',
    className: '',
    shouldTranslateOptions: true,
    showAllOption: false,
    allowCustomRange: true,
    options: getCommonFormOptions(t).lastModifiedFilter,
  },
  {
    label: t('Type'),
    name: 'type',
    className: '',
    shouldTranslateOptions: true,
    showAllOption: true,
    options: [
      { label: 'Active', value: 'active' },
      { label: 'Guest', value: 'guest' },
      { label: 'Expired', value: 'expired' },
    ],
  },
];

export interface SessionActionsProps {
  t: TFunction;
  onLogout: (id: number) => void;
}

export const getSessionItemActions = ({
  t,
  onLogout,
}: SessionActionsProps): ((session: Session) => ActionItem[]) => {
  return (session: Session) => [
    {
      label: t('Logout'),
      icon: LogOut,
      onClick: () => onLogout(session.userId),
      isQuickAction: true,
      variant: 'danger' as const,
    },
  ];
};

export const getSessionColumns = (props: SessionActionsProps): ColumnDef<Session>[] => {
  const { t } = props;
  const getActions = getSessionItemActions(props);
  return [
    {
      accessorKey: 'lastAccessed',
      header: t('Last Accessed'),
      size: 180,
      cell: (info) => <TextCell weight="bold">{info.getValue<string>()}</TextCell>,
    },

    {
      accessorKey: 'isExpired',
      header: t('Active'),
      size: 100,
      cell: (info) => <CheckMarkCell active={info.getValue() !== 1} />,
    },
    {
      accessorKey: 'userName',
      header: t('User Name'),
      size: 140,
      accessorFn: (row) => row.userName ?? '',
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'remoteAddress',
      header: t('IP Address'),
      size: 140,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'userAgent',
      header: t('Browser'),
      size: 280,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'expiresAt',
      header: t('Expires At'),
      size: 140,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      id: 'tableActions',
      header: '',
      size: 40,
      minSize: 40,
      maxSize: 40,
      enableHiding: false,
      enableResizing: false,
      cell: ({ row }) =>
        !row.original.isExpired ? (
          <ActionsCell
            row={row}
            actions={getActions(row.original) as ComponentProps<typeof ActionsCell>['actions']}
          />
        ) : null,
    },
  ];
};

interface GetBulkActionsProps {
  t: TFunction;
  onLogout: () => void;
}

export const getBulkActions = ({
  t,
  onLogout,
}: GetBulkActionsProps): DataTableBulkAction<Session>[] => {
  return [
    {
      label: t('Logout Selected'),
      icon: LogOut,
      onClick: onLogout,
    },
  ];
};
