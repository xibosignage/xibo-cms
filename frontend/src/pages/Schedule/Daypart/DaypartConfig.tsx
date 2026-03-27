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
import { Edit, Trash2, UserPlus2 } from 'lucide-react';
import { type ComponentProps } from 'react';

import type { FilterConfigItem } from '@/components/ui/FilterInputs';
import type { DataTableBulkAction } from '@/components/ui/table/DataTableBulkActions';
import { TextCell, ActionsCell } from '@/components/ui/table/cells';
import { getCommonFormOptions } from '@/config/commonForms';
import type { Daypart } from '@/types/daypart';
import type { ActionItem, BaseModalType } from '@/types/table';

export interface DaypartFilterInput {
  keyword?: string;
  retired?: number | null;
}

export type ModalType = BaseModalType | null;

export const INITIAL_FILTER_STATE: DaypartFilterInput = {};

export const getBaseFilterKeys = (t: TFunction): FilterConfigItem<DaypartFilterInput>[] => [
  {
    label: t('Retired'),
    name: 'retired',
    showAllOption: false,
    options: getCommonFormOptions(t).retired,
  },
];

export interface DaypartActionsProps {
  t: TFunction;
  onDelete: (id: number) => void;
  openAddEditModal: (row: Daypart) => void;
  openShareModal: (id: number) => void;
}

export const getDaypartItemActions = ({
  t,
  onDelete,
  openAddEditModal,
  openShareModal,
}: DaypartActionsProps): ((daypart: Daypart) => ActionItem[]) => {
  return (daypart: Daypart) => {
    const isSpecial = daypart.isAlways === 1 || daypart.isCustom === 1;
    const actions: ActionItem[] = [];

    if (!isSpecial) {
      actions.push({
        label: t('Edit'),
        icon: Edit,
        onClick: () => openAddEditModal(daypart),
        isQuickAction: true,
        variant: 'primary' as const,
      });
    }

    actions.push({
      label: t('Share'),
      icon: UserPlus2,
      onClick: () => openShareModal(daypart.dayPartId),
      isQuickAction: false,
    });

    if (!isSpecial) {
      actions.push({
        label: t('Delete'),
        icon: Trash2,
        onClick: () => onDelete(daypart.dayPartId),
        isQuickAction: true,
        variant: 'danger' as const,
      });
    }

    return actions;
  };
};

export const getDaypartColumns = (props: DaypartActionsProps): ColumnDef<Daypart>[] => {
  const { t } = props;
  const getActions = getDaypartItemActions(props);
  return [
    {
      accessorKey: 'dayPartId',
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
      accessorKey: 'startTime',
      header: t('Start Time'),
      size: 140,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'endTime',
      header: t('End Time'),
      size: 140,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'description',
      header: t('Description'),
      size: 200,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      id: 'tableActions',
      header: '',
      size: 80,
      minSize: 80,
      maxSize: 80,
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
  onShare: () => void;
}

export const getBulkActions = ({
  t,
  onDelete,
  onShare,
}: GetBulkActionsProps): DataTableBulkAction<Daypart>[] => {
  return [
    {
      label: t('Share Selected'),
      icon: UserPlus2,
      onClick: onShare,
    },
    {
      label: t('Delete Selected'),
      icon: Trash2,
      onClick: onDelete,
    },
  ];
};
