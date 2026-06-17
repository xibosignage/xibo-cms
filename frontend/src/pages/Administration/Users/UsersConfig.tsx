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
import { Edit, Folder, Settings, Trash2, Users } from 'lucide-react';
import { type ComponentProps } from 'react';

import type { FilterConfigItem } from '@/components/ui/FilterInputs';
import type { DataTableBulkAction } from '@/components/ui/table/DataTableBulkActions';
import { TextCell, CheckMarkCell, ActionsCell } from '@/components/ui/table/cells';
import type { ActionItem, BaseModalType } from '@/types/table';
import type { User } from '@/types/user';
import { UserType } from '@/types/user';

export interface UserFilterInput {
  userName: string | null;
  logicalOperatorName: 'OR' | 'AND';
  useRegexForName: boolean;
  userTypeId: string | null;
  retired: string | null;
  firstName: string | null;
  lastName: string | null;
}

export type ModalType = BaseModalType | 'setHomeFolder' | 'userGroups' | 'features' | null;

export const INITIAL_FILTER_STATE: UserFilterInput = {
  userName: null,
  logicalOperatorName: 'OR',
  useRegexForName: false,
  userTypeId: null,
  retired: null,
  firstName: null,
  lastName: null,
};

export const getBaseFilterKeys = (t: TFunction): FilterConfigItem<UserFilterInput>[] => [
  {
    label: t('Username'),
    name: 'userName',
    type: 'text',
    placeholder: ' ',
    showAndOr: true,
    andOrKey: 'logicalOperatorName',
    showRegex: true,
    regexKey: 'useRegexForName',
  },
  {
    label: t('User Type'),
    name: 'userTypeId',
    options: [
      { label: t('Super Admin'), value: String(UserType.SuperAdmin) },
      { label: t('Group Admin'), value: String(UserType.GroupAdmin) },
      { label: t('User'), value: String(UserType.User) },
    ],
  },
  {
    label: t('Retired'),
    name: 'retired',
    options: [
      { label: t('Yes'), value: '1' },
      { label: t('No'), value: '0' },
    ],
  },
  {
    label: t('First Name'),
    name: 'firstName',
    type: 'text',
    placeholder: t('First Name'),
  },
  {
    label: t('Last Name'),
    name: 'lastName',
    type: 'text',
    placeholder: t('Last Name'),
  },
];

const getUserTypeLabel = (t: TFunction, userTypeId: number): string => {
  switch (userTypeId) {
    case UserType.SuperAdmin:
      return t('Super Admin');
    case UserType.GroupAdmin:
      return t('Group Admin');
    case UserType.User:
      return t('User');
    default:
      return t('Unknown');
  }
};

export interface UserActionsProps {
  t: TFunction;
  onEdit: (user: User) => void;
  onSetHomeFolder: (user: User) => void;
  onUserGroups: (user: User) => void;
  onFeatures: (user: User) => void;
  onDelete: (user: User) => void;
}

export const getUserItemActions = ({
  t,
  onEdit,
  onSetHomeFolder,
  onUserGroups,
  onFeatures,
  onDelete,
}: UserActionsProps): ((user: User) => ActionItem[]) => {
  return (user: User) => [
    // Quick action
    {
      label: t('Edit'),
      icon: Edit,
      onClick: () => onEdit(user),
      isQuickAction: true,
      variant: 'primary' as const,
    },

    // Dropdown menu actions
    {
      label: t('Edit'),
      icon: Edit,
      onClick: () => onEdit(user),
    },
    {
      label: t('Set Home Folder'),
      icon: Folder,
      onClick: () => onSetHomeFolder(user),
    },
    {
      label: t('User Groups'),
      icon: Users,
      onClick: () => onUserGroups(user),
    },
    {
      label: t('Features'),
      icon: Settings,
      onClick: () => onFeatures(user),
    },
    { isSeparator: true },
    {
      label: t('Delete'),
      icon: Trash2,
      onClick: () => onDelete(user),
      variant: 'danger' as const,
    },
  ];
};

export const getUserColumns = (props: UserActionsProps): ColumnDef<User>[] => {
  const { t } = props;
  const getActions = getUserItemActions(props);
  return [
    {
      accessorKey: 'userId',
      header: t('ID'),
      size: 80,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },
    {
      accessorKey: 'userName',
      header: t('Username'),
      size: 160,
      enableHiding: false,
      cell: (info) => <TextCell weight="bold">{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'userTypeId',
      header: t('User Type'),
      size: 140,
      cell: (info) => <TextCell>{getUserTypeLabel(t, info.getValue<number>())}</TextCell>,
    },
    {
      accessorKey: 'email',
      header: t('Email'),
      size: 200,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'homePage',
      header: t('Home Page'),
      size: 160,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'libraryQuotaFormatted',
      header: t('Library Quota'),
      size: 140,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'loggedIn',
      header: t('Active Sessions'),
      size: 120,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },
    {
      accessorKey: 'retired',
      header: t('Retired'),
      size: 100,
      cell: (info) => <CheckMarkCell active={info.getValue<number>() === 1} />,
    },
    {
      accessorKey: 'twoFactorDescription',
      header: t('Two Factor'),
      size: 140,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'firstName',
      header: t('First Name'),
      size: 140,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'lastName',
      header: t('Last Name'),
      size: 140,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'phone',
      header: t('Phone'),
      size: 140,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'homeFolder',
      header: t('Home Folder'),
      size: 160,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'lastAccessed',
      header: t('Last Accessed'),
      size: 180,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'ref1',
      header: t('Ref 1'),
      size: 120,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'ref2',
      header: t('Ref 2'),
      size: 120,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'ref3',
      header: t('Ref 3'),
      size: 120,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'ref4',
      header: t('Ref 4'),
      size: 120,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'ref5',
      header: t('Ref 5'),
      size: 120,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
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
  onSetHomeFolder: () => void;
}

export const getBulkActions = ({
  t,
  onSetHomeFolder,
}: GetBulkActionsProps): DataTableBulkAction<User>[] => [
  {
    label: t('Set Home Folder'),
    icon: Folder,
    onClick: onSetHomeFolder,
  },
];
