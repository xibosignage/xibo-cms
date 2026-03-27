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
import type { DatasetRss } from '@/types/datasetRss';
import type { ActionItem } from '@/types/table';

export interface DatasetRssActionsProps {
  t: TFunction;
  onEdit: (column: DatasetRss) => void;
  onCopy: (column: DatasetRss) => void;
  onDelete: (id: number) => void;
}

export const getRssActions = ({
  t,
  onEdit,
  onCopy,
  onDelete,
}: DatasetRssActionsProps): ((rss: DatasetRss) => ActionItem[]) => {
  return (rss: DatasetRss) => [
    {
      label: t('Edit'),
      icon: Edit,
      onClick: () => onEdit(rss),
      isQuickAction: true,
      variant: 'primary' as const,
    },
    {
      label: t('Edit'),
      icon: Edit,
      onClick: () => onEdit(rss),
    },
    {
      label: t('Make a Copy'),
      icon: CopyCheck,
      onClick: () => onCopy(rss),
    },
    { isSeparator: true },
    {
      label: t('Delete'),
      icon: Trash2,
      onClick: () => onDelete(rss.id),
      variant: 'danger' as const,
    },
  ];
};

export const getRssDefinitions = (props: DatasetRssActionsProps): ColumnDef<DatasetRss>[] => {
  const { t } = props;
  const getActions = getRssActions(props);

  return [
    {
      id: 'tableSelection',
      header: '',
      size: 40,
      enableHiding: false,
      enableResizing: false,
    },
    {
      accessorKey: 'id',
      header: t('ID'),
      size: 100,
      cell: (info) => <TextCell weight="bold">{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'title',
      header: t('TITLE'),
      size: 150,
      cell: (info) => <TextCell weight="bold">{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'author',
      header: t('AUTHOR'),
      size: 120,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'psk',
      header: t('URL'),
      size: 120,
      cell: (info) => {
        const psk = info.getValue<string>();

        const fullUrl = `${window.location.origin}/rss/${psk}`;

        return (
          <TextCell>
            <a
              href={fullUrl}
              target="_blank"
              rel="noopener noreferrer"
              className="text-xibo-blue-600 hover:text-blue-800 hover:underline transition-colors"
            >
              {fullUrl}
            </a>
          </TextCell>
        );
      },
    },
    {
      id: 'tableActions',
      header: t('ACTION'),
      size: 80,
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
}: GetBulkActionsProps): DataTableBulkAction<DatasetRss>[] => {
  return [
    {
      label: t('Delete Selected'),
      icon: Trash2,
      onClick: onDelete,
    },
  ];
};
