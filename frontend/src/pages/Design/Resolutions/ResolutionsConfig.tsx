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
import { Edit, Trash2 } from 'lucide-react';
import { type ComponentProps } from 'react';

import type { FilterConfigItem } from '@/components/ui/FilterInputs';
import type { DataTableBulkAction } from '@/components/ui/table/DataTableBulkActions';
import { TextCell, ActionsCell, CheckMarkCell } from '@/components/ui/table/cells';
import type { Resolution } from '@/types/resolution';
import type { ActionItem, BaseModalType } from '@/types/table';

export interface ResolutionFilterInput {
  enabled: number | null;
}

export type ModalType = BaseModalType | null;

export const INITIAL_FILTER_STATE: ResolutionFilterInput = {
  enabled: 1,
};

export const getBaseFilterKeys = (t: TFunction): FilterConfigItem<ResolutionFilterInput>[] => [
  {
    label: t('Enabled?'),
    name: 'enabled',
    className: '',
    shouldTranslateOptions: true,
    showAllOption: true,
    options: [
      { label: 'Yes', value: 1 },
      { label: 'No', value: 0 },
    ],
  },
];

export interface ResolutionActionsProps {
  t: TFunction;
  onDelete: (id: number) => void;
  openAddEditModal: (row: Resolution) => void;
}

export const getResolutionItemActions = ({
  t,
  onDelete,
  openAddEditModal,
}: ResolutionActionsProps): ((resolution: Resolution) => ActionItem[]) => {
  return (resolution: Resolution) => [
    {
      label: t('Edit'),
      icon: Edit,
      onClick: () => openAddEditModal(resolution),
      isQuickAction: true,
      variant: 'primary' as const,
    },
    {
      label: t('Delete'),
      icon: Trash2,
      onClick: () => onDelete(resolution.resolutionId),
      isQuickAction: true,
      variant: 'danger' as const,
    },
  ];
};

export const getResolutionColumns = (props: ResolutionActionsProps): ColumnDef<Resolution>[] => {
  const { t } = props;
  const getActions = getResolutionItemActions(props);
  return [
    {
      accessorKey: 'resolutionId',
      header: t('ID'),
      size: 80,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },

    {
      accessorKey: 'resolution',
      header: t('Resolution'),
      size: 200,
      enableHiding: false,
      cell: (info) => <TextCell weight="bold">{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'width',
      header: t('Width'),
      size: 140,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'height',
      header: t('Height'),
      size: 140,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'enabled',
      header: t('Enabled'),
      size: 100,
      cell: (info) => <CheckMarkCell active={info.getValue() === 1} />,
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
}

export const getBulkActions = ({
  t,
  onDelete,
}: GetBulkActionsProps): DataTableBulkAction<Resolution>[] => {
  return [
    {
      label: t('Delete Selected'),
      icon: Trash2,
      onClick: onDelete,
    },
  ];
};
