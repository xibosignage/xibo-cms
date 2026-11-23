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
import { CopyCheck, Edit, Trash2, Check, X } from 'lucide-react';
import { type ComponentProps } from 'react';

import type { DataTableBulkAction } from '@/components/ui/table/DataTableBulkActions';
import { TextCell, ActionsCell } from '@/components/ui/table/cells';
import type { DatasetColumn } from '@/types/datasetColumn';
import type { ActionItem } from '@/types/table';

export interface DatasetColumnActionsProps {
  t: TFunction;
  canEdit?: boolean;
  onEdit: (column: DatasetColumn) => void;
  onCopy: (column: DatasetColumn) => void;
  onDelete: (id: number) => void;
}

export const getColumnActions = ({
  t,
  onEdit,
  onCopy,
  onDelete,
}: DatasetColumnActionsProps): ((column: DatasetColumn) => ActionItem[]) => {
  return (column: DatasetColumn) => [
    {
      label: t('Edit'),
      icon: Edit,
      onClick: () => onEdit(column),
      isQuickAction: true,
      variant: 'primary' as const,
    },
    {
      label: t('Edit'),
      icon: Edit,
      onClick: () => onEdit(column),
    },
    {
      label: t('Make a Copy'),
      icon: CopyCheck,
      onClick: () => onCopy(column),
    },
    { isSeparator: true },
    {
      label: t('Delete'),
      icon: Trash2,
      onClick: () => onDelete(column.dataSetColumnId),
      variant: 'danger' as const,
    },
  ];
};

export const getColumnDefinitions = (
  props: DatasetColumnActionsProps,
): ColumnDef<DatasetColumn>[] => {
  const { t, canEdit = false } = props;
  const getActions = getColumnActions(props);

  const dataColumns: ColumnDef<DatasetColumn>[] = [
    {
      accessorKey: 'heading',
      header: t('HEADING'),
      size: 150,
      cell: (info) => <TextCell weight="bold">{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'dataType',
      header: t('DATA TYPE'),
      size: 120,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'dataSetColumnType',
      header: t('COLUMN TYPE'),
      size: 120,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'listContent',
      header: t('LIST CONTENT'),
      size: 140,
      cell: (info) => {
        const val = info.getValue<string>();
        return <TextCell>{val ? val : '-'}</TextCell>;
      },
    },
    {
      accessorKey: 'tooltip',
      header: t('TOOLTIP'),
      size: 140,
      cell: (info) => {
        const val = info.getValue<string>();
        return <TextCell>{val ? val : '-'}</TextCell>;
      },
    },
    {
      accessorKey: 'columnOrder',
      header: t('ORDER'),
      size: 80,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },
    {
      accessorKey: 'isRequired',
      header: t('REQUIRED'),
      size: 100,
      cell: (info) => {
        const isReq = Boolean(info.getValue());
        return (
          <div className="flex items-center pl-2">
            {isReq ? (
              <div className="bg-green-100 text-green-600 rounded p-1">
                <Check size={14} strokeWidth={3} />
              </div>
            ) : (
              <div className="text-gray-400 p-1">
                <X size={14} strokeWidth={3} />
              </div>
            )}
          </div>
        );
      },
    },
  ];

  // Column editing (add/edit/delete) is gated behind the dataset.modify feature
  if (!canEdit) {
    return dataColumns;
  }

  const selectionColumn: ColumnDef<DatasetColumn> = {
    id: 'tableSelection',
    header: '',
    size: 40,
    enableHiding: false,
    enableResizing: false,
  };

  const actionColumn: ColumnDef<DatasetColumn> = {
    id: 'tableActions',
    header: t('ACTION'),
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
  };

  return [selectionColumn, ...dataColumns, actionColumn];
};

interface GetBulkActionsProps {
  t: TFunction;
  onDelete: () => void;
}

export const getBulkActions = ({
  t,
  onDelete,
}: GetBulkActionsProps): DataTableBulkAction<DatasetColumn>[] => {
  return [
    {
      label: t('Delete Selected'),
      icon: Trash2,
      onClick: onDelete,
    },
  ];
};
