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
import { Download, Info, Trash2 } from 'lucide-react';
import { type ComponentProps } from 'react';

import type { FilterConfigItem } from '@/components/ui/FilterInputs';
import type { DataTableBulkAction } from '@/components/ui/table/DataTableBulkActions';
import { TextCell, ActionsCell } from '@/components/ui/table/cells';
import type { Font } from '@/types/font';
import type { ActionItem } from '@/types/table';
import { formatFileSize } from '@/utils/formatters';

export interface FontFilterInput {
  name?: string;
  id?: string;
  logicalOperatorName?: 'OR' | 'AND';
  useRegexForName?: boolean;
}

export type ModalType = 'delete' | 'upload' | 'details' | null;

export const INITIAL_FILTER_STATE: FontFilterInput = {
  name: '',
  id: '',
  logicalOperatorName: 'OR',
  useRegexForName: false,
};

export const getFilterKeys = (t: TFunction): FilterConfigItem<FontFilterInput>[] => [
  {
    label: t('ID'),
    name: 'id',
    type: 'number',
    placeholder: ' ',
  },
  {
    label: t('Name'),
    name: 'name',
    type: 'text',
    placeholder: ' ',
    showAndOr: true,
    andOrKey: 'logicalOperatorName',
    showRegex: true,
    regexKey: 'useRegexForName',
  },
];

export const FONT_ACCEPTED_EXTENSIONS: Record<string, string[]> = {
  'font/otf': ['.otf'],
  'font/ttf': ['.ttf'],
  'application/vnd.ms-fontobject': ['.eot'],
  'image/svg+xml': ['.svg'],
  'font/woff': ['.woff'],
};

export interface FontActionsProps {
  t: TFunction;
  onDelete: (id: number) => void;
  onDetails: (id: number) => void;
  onDownload: (font: Font) => void;
}

export const getFontItemActions = ({
  t,
  onDelete,
  onDetails,
  onDownload,
}: FontActionsProps): ((font: Font) => ActionItem[]) => {
  return (font: Font) => [
    // Quick actions (icon buttons in row)
    {
      label: t('Details'),
      icon: Info,
      onClick: () => onDetails(font.id),
      isQuickAction: true,
      variant: 'primary' as const,
    },
    {
      label: t('Download'),
      icon: Download,
      onClick: () => onDownload(font),
      isQuickAction: true,
    },
    // Dropdown menu actions
    {
      label: t('Details'),
      icon: Info,
      onClick: () => onDetails(font.id),
    },
    {
      label: t('Download'),
      icon: Download,
      onClick: () => onDownload(font),
    },
    {
      isSeparator: true,
    },
    {
      label: t('Delete'),
      icon: Trash2,
      onClick: () => onDelete(font.id),
      variant: 'danger' as const,
    },
  ];
};

export const getFontColumns = (props: FontActionsProps): ColumnDef<Font>[] => {
  const { t } = props;
  const getActions = getFontItemActions(props);
  return [
    {
      accessorKey: 'id',
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
      accessorKey: 'fileName',
      header: t('File Name'),
      size: 200,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'familyName',
      header: t('Family Name'),
      size: 180,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'createdAt',
      header: t('Created'),
      size: 180,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'modifiedAt',
      header: t('Modified'),
      size: 180,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'modifiedBy',
      header: t('Modified By'),
      size: 150,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'size',
      header: t('Size'),
      size: 100,
      cell: (info) => <TextCell>{formatFileSize(info.getValue<number>())}</TextCell>,
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
}: GetBulkActionsProps): DataTableBulkAction<Font>[] => {
  return [
    {
      label: t('Delete Selected'),
      icon: Trash2,
      onClick: onDelete,
    },
  ];
};
