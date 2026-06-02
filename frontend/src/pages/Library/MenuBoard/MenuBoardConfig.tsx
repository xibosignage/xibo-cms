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
import { CopyCheck, Edit, FolderInput, Trash2, UserPlus2 } from 'lucide-react';
import { type ComponentProps } from 'react';

import type { FilterConfigItem } from '@/components/ui/FilterInputs';
import type { DataTableBulkAction } from '@/components/ui/table/DataTableBulkActions';
import { TextCell, ActionsCell } from '@/components/ui/table/cells';
import { getCommonFormOptions } from '@/config/commonForms';
import type { MenuBoard } from '@/types/menuBoard';
import type { ActionItem, BaseModalType } from '@/types/table';
import { formatDateTime } from '@/utils/date';

export interface MenuBoardFilterInput {
  name: string;
  menuId: string;
  code: string;
  userId: string;
  lastModified: string;
  logicalOperatorName?: 'OR' | 'AND';
  useRegexForName?: boolean;
}

export type ModalType = BaseModalType | null;

export const INITIAL_FILTER_STATE: MenuBoardFilterInput = {
  name: '',
  menuId: '',
  userId: '',
  code: '',
  lastModified: '',
  logicalOperatorName: 'OR',
  useRegexForName: false,
};

export const getBaseFilterKeys = (t: TFunction): FilterConfigItem<MenuBoardFilterInput>[] => [
  {
    label: t('ID'),
    placeholder: ' ',
    name: 'menuId',
    type: 'number',
  },
  {
    label: t('Name'),
    name: 'name',
    type: 'text',
    className: '',
    placeholder: ' ',
    showAndOr: true,
    andOrKey: 'logicalOperatorName',
    showRegex: true,
    regexKey: 'useRegexForName',
  },
  {
    label: t('Code'),
    placeholder: ' ',
    name: 'code',
    type: 'text',
  },
  {
    label: t('Owner'),
    name: 'userId',
    className: '',
    options: [{ label: t('Select Owner'), value: null }],
  },
  {
    label: t('Last Modified'),
    name: 'lastModified',
    className: '',
    type: 'date-range',
    options: getCommonFormOptions(t).lastModifiedFilter,
  },
];

export interface MenuBoardActionsProps {
  t: TFunction;
  onDelete: (id: number) => void;
  openAddEditModal: (row: MenuBoard) => void;
  openShareModal?: (id: number) => void;
  openMoveModal?: (row: MenuBoard) => void;
  copyMenuBoard?: (id: number) => void;
  onNavigate: (path: string) => void;
}

export const getMenuBoardItemActions = ({
  t,
  onDelete,
  openAddEditModal,
  openShareModal,
  openMoveModal,
  copyMenuBoard,
  onNavigate,
}: MenuBoardActionsProps): ((menuBoard: MenuBoard) => ActionItem[]) => {
  return (menuBoard: MenuBoard) => [
    // Quick Actions
    {
      label: t('Edit'),
      icon: Edit,
      onClick: () => openAddEditModal(menuBoard),
      isQuickAction: true,
      variant: 'primary' as const,
    },

    // Dropdown Menu Actions
    {
      label: t('Edit'),
      icon: Edit,
      onClick: () => openAddEditModal(menuBoard),
    },
    {
      label: t('Make a Copy'),
      icon: CopyCheck,
      onClick: () => copyMenuBoard && copyMenuBoard(menuBoard.menuId),
    },
    {
      label: t('Move'),
      icon: FolderInput,
      onClick: () => openMoveModal && openMoveModal(menuBoard),
    },
    {
      label: t('Share'),
      icon: UserPlus2,
      onClick: () => openShareModal && openShareModal(menuBoard.menuId),
    },
    { isSeparator: true },
    {
      label: t('View Categories'),
      isNavigation: true,
      onClick: () => {
        onNavigate(`/library/menu-boards/${menuBoard.menuId}/categories`);
      },
    },
    { isSeparator: true },
    {
      label: t('Delete'),
      icon: Trash2,
      onClick: () => onDelete(menuBoard.menuId),
      variant: 'danger' as const,
    },
  ];
};

export const getMenuBoardColumns = (props: MenuBoardActionsProps): ColumnDef<MenuBoard>[] => {
  const { t } = props;
  const getActions = getMenuBoardItemActions(props);
  return [
    {
      accessorKey: 'menuId',
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
      accessorKey: 'description',
      header: t('Description'),
      size: 150,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'code',
      header: t('Code'),
      size: 100,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'owner',
      header: t('Owner'),
      size: 150,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'groupsWithPermissions',
      enableSorting: false,
      header: t('Sharing'),
      size: 120,
      cell: (info) => {
        const groups = info.getValue() as string;
        return <TextCell className="italic text-gray-500">{groups || t('Private')}</TextCell>;
      },
    },
    {
      accessorKey: 'modifiedDt',
      header: t('Modified'),
      size: 160,
      cell: (info) => {
        const ts = info.getValue<number>();
        return <TextCell>{ts ? formatDateTime(new Date(ts * 1000)) : ''}</TextCell>;
      },
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

interface GetBulkActionsProps {
  t: TFunction;
  onDelete: () => void;
  onMove?: () => void;
  onShare: () => void;
}

export const getBulkActions = ({
  t,
  onDelete,
  onMove,
  onShare,
}: GetBulkActionsProps): DataTableBulkAction<MenuBoard>[] => {
  return [
    ...(onMove
      ? [
          {
            label: t('Move'),
            icon: FolderInput,
            onClick: onMove,
          },
        ]
      : []),
    {
      label: t('Share'),
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
