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

import type { FilterConfigItem } from '@/components/ui/FilterInputs';
import type { DataTableBulkAction } from '@/components/ui/table/DataTableBulkActions';
import { ActionsCell, CheckMarkCell, MediaCell, TextCell } from '@/components/ui/table/cells';
import type { MenuBoardProduct } from '@/types/menuBoardProduct';
import type { ActionItem } from '@/types/table';

export interface MenuBoardProductFilterInput {
  menuProductId: string;
  name: string;
  code: string;
  availability: string;
}

export const INITIAL_PRODUCT_FILTER_STATE: MenuBoardProductFilterInput = {
  menuProductId: '',
  name: '',
  code: '',
  availability: '',
};

export const getProductFilterKeys = (
  t: TFunction,
): FilterConfigItem<MenuBoardProductFilterInput>[] => [
  {
    label: t('Product ID'),
    placeholder: t('Enter ID'),
    name: 'menuProductId',
    type: 'number',
  },
  {
    label: t('Name'),
    placeholder: t('Enter Name'),
    name: 'name',
    type: 'text',
  },
  {
    label: t('Code'),
    placeholder: t('Enter Code'),
    name: 'code',
    type: 'text',
  },
  {
    label: t('Availability'),
    name: 'availability',
    type: 'select',
    options: [
      { label: t('Available'), value: '1' },
      { label: t('Unavailable'), value: '0' },
    ],
  },
];

export interface MenuBoardProductActionsProps {
  t: TFunction;
  onEdit: (product: MenuBoardProduct) => void;
  onCopy: (product: MenuBoardProduct) => void;
  onDelete: (id: number) => void;
  onPreview?: (product: MenuBoardProduct) => void;
}

export const getProductItemActions = ({
  t,
  onEdit,
  onCopy,
  onDelete,
}: MenuBoardProductActionsProps): ((product: MenuBoardProduct) => ActionItem[]) => {
  return (product: MenuBoardProduct) => [
    {
      label: t('Edit'),
      icon: Edit,
      onClick: () => onEdit(product),
      isQuickAction: true,
      variant: 'primary' as const,
    },
    {
      label: t('Edit'),
      icon: Edit,
      onClick: () => onEdit(product),
    },
    {
      label: t('Make a Copy'),
      icon: CopyCheck,
      onClick: () => onCopy(product),
    },
    { isSeparator: true },
    {
      label: t('Delete'),
      icon: Trash2,
      onClick: () => onDelete(product.menuProductId),
      variant: 'danger' as const,
    },
  ];
};

export const getProductColumnDefinitions = (
  props: MenuBoardProductActionsProps,
): ColumnDef<MenuBoardProduct>[] => {
  const { t, onPreview } = props;
  const getActions = getProductItemActions(props);

  return [
    {
      accessorKey: 'menuProductId',
      header: t('ID'),
      size: 60,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },
    {
      accessorKey: 'name',
      header: t('Name'),
      size: 150,
      enableHiding: false,
      cell: (info) => <TextCell weight="bold">{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'mediaId',
      header: t('Media'),
      size: 100,
      enableSorting: false,
      cell: (info) => {
        const mediaId = info.getValue<number | undefined>();
        if (!mediaId) {
          return <TextCell>-</TextCell>;
        }
        return (
          <MediaCell
            thumb={`/library/thumbnail/${mediaId}`}
            alt={info.row.original.name}
            mediaType="image"
            onPreview={onPreview ? () => onPreview(info.row.original) : undefined}
          />
        );
      },
    },
    {
      accessorKey: 'description',
      header: t('Description'),
      size: 150,
      cell: (info) => <TextCell>{info.getValue<string>() || '-'}</TextCell>,
    },
    {
      accessorKey: 'allergyInfo',
      header: t('Allergy Info'),
      size: 150,
      cell: (info) => <TextCell>{info.getValue<string>() || '-'}</TextCell>,
    },
    {
      accessorKey: 'code',
      header: t('Code'),
      size: 100,
      cell: (info) => <TextCell>{info.getValue<string>() || '-'}</TextCell>,
    },
    {
      accessorKey: 'price',
      header: t('Price'),
      size: 90,
      cell: (info) => {
        const val = info.getValue<number | undefined>();
        return <TextCell>{val !== undefined && val !== null ? String(val) : '-'}</TextCell>;
      },
    },
    {
      accessorKey: 'availability',
      header: t('Availability'),
      size: 130,
      cell: (info) => <CheckMarkCell active={info.getValue() === 1} />,
    },
    {
      accessorKey: 'displayOrder',
      header: t('Display Order'),
      size: 100,
      cell: (info) => {
        const val = info.getValue<number | undefined>();
        return <TextCell>{val !== undefined && val !== null ? String(val) : '-'}</TextCell>;
      },
    },
    {
      accessorKey: 'calories',
      header: t('Calories'),
      size: 90,
      cell: (info) => {
        const val = info.getValue<number | undefined>();
        return <TextCell>{val !== undefined && val !== null ? String(val) : '-'}</TextCell>;
      },
    },
    {
      id: 'tableActions',
      header: '',
      size: 100,
      minSize: 100,
      maxSize: 100,
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

interface GetProductBulkActionsProps {
  t: TFunction;
  onDelete: () => void;
}

export const getProductBulkActions = ({
  t,
  onDelete,
}: GetProductBulkActionsProps): DataTableBulkAction<MenuBoardProduct>[] => {
  return [
    {
      label: t('Delete Selected'),
      icon: Trash2,
      onClick: onDelete,
    },
  ];
};
