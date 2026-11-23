/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
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
    options: [
      { label: t('All'), value: '' },
      { label: t('Yes'), value: 1 },
      { label: t('No'), value: 0 },
    ],
  },
];

export interface ResolutionActionsProps {
  t: TFunction;
  canModify?: boolean;
  onDelete: (id: number) => void;
  openAddEditModal: (row: Resolution) => void;
}

export const getResolutionItemActions = ({
  t,
  canModify = false,
  onDelete,
  openAddEditModal,
}: ResolutionActionsProps): ((resolution: Resolution) => ActionItem[]) => {
  return (resolution: Resolution) => {
    const canEdit = !!resolution.userPermissions?.edit;
    const canDelete = !!resolution.userPermissions?.delete;

    const actions: ActionItem[] = [];

    if (canModify && canEdit) {
      actions.push({
        label: t('Edit'),
        icon: Edit,
        onClick: () => openAddEditModal(resolution),
        isQuickAction: true,
        variant: 'primary' as const,
      });
    }

    if (canModify && canDelete) {
      actions.push({
        label: t('Delete'),
        icon: Trash2,
        onClick: () => onDelete(resolution.resolutionId),
        isQuickAction: true,
        variant: 'danger' as const,
      });
    }

    return actions;
  };
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
  canModify?: boolean;
  onDelete: () => void;
}

export const getBulkActions = ({
  t,
  canModify = false,
  onDelete,
}: GetBulkActionsProps): DataTableBulkAction<Resolution>[] => {
  const actions: DataTableBulkAction<Resolution>[] = [];

  if (canModify) {
    actions.push({
      label: t('Delete Selected'),
      icon: Trash2,
      onClick: onDelete,
    });
  }

  return actions;
};
