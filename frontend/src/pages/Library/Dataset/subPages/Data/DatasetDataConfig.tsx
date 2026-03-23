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
import { CopyCheck, Edit, Trash2 } from 'lucide-react';
import { type ComponentProps } from 'react';

import type { DataTableBulkAction } from '@/components/ui/table/DataTableBulkActions';
import { TextCell, ActionsCell } from '@/components/ui/table/cells';
import type { DynamicRowData } from '@/services/datasetApi';
import type { DatasetColumn } from '@/types/datasetColumn';
import type { ActionItem } from '@/types/table';

export interface DatasetDataActionsProps {
  t: TFunction;
  onEdit: (row: DynamicRowData) => void;
  onDelete: (rowId: string | number) => void;
  onCopy?: (row: DynamicRowData) => void;
  rowIdKey?: string;
}

export const getDynamicDataColumns = (
  columnsSchema: DatasetColumn[],
  props: DatasetDataActionsProps,
): ColumnDef<DynamicRowData>[] => {
  const { t, onEdit, onDelete, rowIdKey = 'id' } = props;

  const selectionColumn: ColumnDef<DynamicRowData> = {
    id: 'tableSelection',
    header: '',
    size: 40,
    enableHiding: false,
    enableResizing: false,
  };

  const dynamicColumns: ColumnDef<DynamicRowData>[] = columnsSchema
    .sort((a, b) => (a.columnOrder || 0) - (b.columnOrder || 0))
    .map((col) => ({
      id: `col_${col.dataSetColumnId}`,
      accessorFn: (row) => row[col.heading] ?? row[col.dataSetColumnId],
      header: col.heading.toUpperCase(),
      size: 150,
      cell: (info) => {
        const value = info.getValue();

        if (value === undefined || value === null || value === '') {
          return <TextCell className="text-gray-400">-</TextCell>;
        }

        if (col.dataTypeId === 6) {
          return <TextCell className="italic text-gray-500">{t('<HTML Content>')}</TextCell>;
        }

        return <TextCell>{String(value)}</TextCell>;
      },
    }));

  const actionColumn: ColumnDef<DynamicRowData> = {
    id: 'tableActions',
    header: t('ACTION'),
    size: 80,
    enableHiding: false,
    enableResizing: false,
    cell: ({ row }) => {
      const actions: ActionItem[] = [
        {
          label: t('Edit'),
          icon: Edit,
          onClick: () => {
            onEdit(row.original);
          },
          isQuickAction: true,
          variant: 'primary',
        },
        {
          label: t('Duplicate'),
          icon: CopyCheck,
          onClick: () => {
            if (props.onCopy) props.onCopy(row.original);
          },
        },
        { isSeparator: true },
        {
          label: t('Delete'),
          icon: Trash2,
          onClick: () => {
            const idToDelete = row.original[rowIdKey] as string | number;
            onDelete(idToDelete);
          },
          variant: 'danger',
        },
      ];

      return (
        <ActionsCell row={row} actions={actions as ComponentProps<typeof ActionsCell>['actions']} />
      );
    },
  };

  return [selectionColumn, ...dynamicColumns, actionColumn];
};

interface GetBulkActionsProps {
  t: TFunction;
  onDelete: () => void;
}

export const getBulkActions = ({
  t,
  onDelete,
}: GetBulkActionsProps): DataTableBulkAction<DynamicRowData>[] => {
  return [
    {
      label: t('Delete Selected'),
      icon: Trash2,
      onClick: onDelete,
    },
  ];
};
