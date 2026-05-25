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
import { TextCell, ActionsCell, MediaCell } from '@/components/ui/table/cells';
import type { MenuBoardCategory } from '@/types/menuBoardCategory';
import type { ActionItem } from '@/types/table';

export interface MenuBoardCategoryFilterInput {
  menuCategoryId: string;
  code: string;
}

export const INITIAL_CATEGORY_FILTER_STATE: MenuBoardCategoryFilterInput = {
  menuCategoryId: '',
  code: '',
};

export const getCategoryFilterKeys = (
  t: TFunction,
): FilterConfigItem<MenuBoardCategoryFilterInput>[] => [
  {
    label: t('Category ID'),
    placeholder: t('Enter ID'),
    name: 'menuCategoryId',
    type: 'number',
  },
  {
    label: t('Code'),
    placeholder: t('Enter Code'),
    name: 'code',
    type: 'text',
  },
];

export interface MenuBoardCategoryActionsProps {
  t: TFunction;
  onEdit: (category: MenuBoardCategory) => void;
  onCopy: (category: MenuBoardCategory) => void;
  onDelete: (id: number) => void;
  onViewProducts: (category: MenuBoardCategory) => void;
  onPreview?: (category: MenuBoardCategory) => void;
}

export const getCategoryItemActions = ({
  t,
  onEdit,
  onCopy,
  onDelete,
  onViewProducts,
}: MenuBoardCategoryActionsProps): ((category: MenuBoardCategory) => ActionItem[]) => {
  return (category: MenuBoardCategory) => [
    {
      label: t('Edit'),
      icon: Edit,
      onClick: () => onEdit(category),
      isQuickAction: true,
      variant: 'primary' as const,
    },
    {
      label: t('Edit'),
      icon: Edit,
      onClick: () => onEdit(category),
    },
    {
      label: t('Make a Copy'),
      icon: CopyCheck,
      onClick: () => onCopy(category),
    },
    { isSeparator: true },
    {
      label: t('View Products'),
      isNavigation: true,
      onClick: () => onViewProducts(category),
    },
    { isSeparator: true },
    {
      label: t('Delete'),
      icon: Trash2,
      onClick: () => onDelete(category.menuCategoryId),
      variant: 'danger' as const,
    },
  ];
};

export const getCategoryColumnDefinitions = (
  props: MenuBoardCategoryActionsProps,
): ColumnDef<MenuBoardCategory>[] => {
  const { t, onPreview } = props;
  const getActions = getCategoryItemActions(props);

  return [
    {
      accessorKey: 'menuCategoryId',
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
      accessorKey: 'thumbnail',
      header: t('Media'),
      size: 100,
      enableSorting: false,
      cell: (info) => {
        const thumb = info.getValue<string | undefined>();
        if (!thumb) {
          return <TextCell>-</TextCell>;
        }
        return (
          <MediaCell
            thumb={thumb}
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
      accessorKey: 'code',
      header: t('Code'),
      size: 100,
      cell: (info) => <TextCell>{info.getValue<string>() || '-'}</TextCell>,
    },
    {
      id: 'tableActions',
      header: '',
      size: 120,
      minSize: 120,
      maxSize: 120,
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

interface GetCategoryBulkActionsProps {
  t: TFunction;
  onDelete: () => void;
}

export const getCategoryBulkActions = ({
  t,
  onDelete,
}: GetCategoryBulkActionsProps): DataTableBulkAction<MenuBoardCategory>[] => {
  return [
    {
      label: t('Delete Selected'),
      icon: Trash2,
      onClick: onDelete,
    },
  ];
};
