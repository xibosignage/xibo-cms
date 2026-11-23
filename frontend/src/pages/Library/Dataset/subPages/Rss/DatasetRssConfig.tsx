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
              className="text-xibo-blue-600 hover:text-xibo-blue-800 hover:underline transition-colors"
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
}: GetBulkActionsProps): DataTableBulkAction<DatasetRss>[] => {
  return [
    {
      label: t('Delete Selected'),
      icon: Trash2,
      onClick: onDelete,
    },
  ];
};
