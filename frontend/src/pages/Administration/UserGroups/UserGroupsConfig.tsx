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
import { Copy, Edit, Settings, Trash2, Users } from 'lucide-react';
import { type ComponentProps } from 'react';

import type { FilterConfigItem } from '@/components/ui/FilterInputs';
import type { DataTableBulkAction } from '@/components/ui/table/DataTableBulkActions';
import { ActionsCell, CheckMarkCell, TextCell } from '@/components/ui/table/cells';
import type { ActionItem, BaseModalType } from '@/types/table';
import type { UserGroup } from '@/types/userGroup';
import { formatFileSizeIEC } from '@/utils/formatters';

export interface UserGroupFilterInput {
  userGroup: string | null;
  logicalOperatorName: 'OR' | 'AND';
  useRegexForName: boolean;
}

export type ModalType = BaseModalType | 'copy' | 'members' | 'features' | null;

export const INITIAL_FILTER_STATE: UserGroupFilterInput = {
  userGroup: null,
  logicalOperatorName: 'OR',
  useRegexForName: false,
};

export const getBaseFilterKeys = (t: TFunction): FilterConfigItem<UserGroupFilterInput>[] => [
  {
    label: t('Name'),
    name: 'userGroup',
    type: 'text',
    placeholder: ' ',
    showAndOr: true,
    andOrKey: 'logicalOperatorName',
    showRegex: true,
    regexKey: 'useRegexForName',
  },
];

export interface UserGroupActionsProps {
  t: TFunction;
  isSuperAdmin: boolean;
  hasUsergroupModify: boolean;
  onEdit: (userGroup: UserGroup) => void;
  onCopy: (userGroup: UserGroup) => void;
  onMembers: (userGroup: UserGroup) => void;
  onFeatures: (userGroup: UserGroup) => void;
  onDelete: (userGroup: UserGroup) => void;
}

export const getUserGroupItemActions = ({
  t,
  isSuperAdmin,
  hasUsergroupModify,
  onEdit,
  onCopy,
  onMembers,
  onFeatures,
  onDelete,
}: UserGroupActionsProps): ((userGroup: UserGroup) => ActionItem[]) => {
  return (userGroup: UserGroup) => {
    // Matches the backend: UserGroup::assignUser()/unassignUser() require the
    // 'usergroup.modify' feature (route middleware) AND checkEditable($group) on this
    // specific group, surfaced here as userGroup.userPermissions.edit.
    const canManageMembers =
      isSuperAdmin || (hasUsergroupModify && !!userGroup.userPermissions?.edit);

    const actions: ActionItem[] = [
      // Quick action
      {
        label: t('Edit'),
        icon: Edit,
        onClick: () => onEdit(userGroup),
        isQuickAction: true,
        variant: 'primary' as const,
      },

      // Dropdown menu actions
      {
        label: t('Edit'),
        icon: Edit,
        onClick: () => onEdit(userGroup),
      },
      {
        label: t('Copy'),
        icon: Copy,
        onClick: () => onCopy(userGroup),
      },
    ];

    if (canManageMembers) {
      actions.push({
        label: t('Members'),
        icon: Users,
        onClick: () => onMembers(userGroup),
      });
    }

    actions.push(
      {
        label: t('Features'),
        icon: Settings,
        onClick: () => onFeatures(userGroup),
      },
      { isSeparator: true },
      {
        label: t('Delete'),
        icon: Trash2,
        onClick: () => onDelete(userGroup),
        variant: 'danger' as const,
      },
    );

    return actions;
  };
};

export const getUserGroupColumns = (props: UserGroupActionsProps): ColumnDef<UserGroup>[] => {
  const { t } = props;
  const getActions = getUserGroupItemActions(props);
  return [
    {
      accessorKey: 'groupId',
      header: t('ID'),
      size: 80,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },
    {
      accessorKey: 'group',
      header: t('Name'),
      size: 200,
      enableHiding: false,
      cell: (info) => <TextCell weight="bold">{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'description',
      header: t('Description'),
      size: 300,
      cell: (info) => <TextCell className="truncate">{info.getValue<string>() ?? ''}</TextCell>,
    },
    {
      accessorKey: 'libraryQuota',
      header: t('Library Quota'),
      size: 140,
      cell: (info) => {
        const kb = info.getValue<number>();
        return <TextCell>{kb ? formatFileSizeIEC(kb * 1024) : t('Unlimited')}</TextCell>;
      },
    },
    {
      accessorKey: 'isShownForAddUser',
      header: t('Shown for Add User'),
      size: 160,
      cell: (info) => <CheckMarkCell active={info.getValue<number>() === 1} />,
    },
    {
      accessorKey: 'isSystemNotification',
      header: t('System Notifications'),
      size: 160,
      cell: (info) => <CheckMarkCell active={info.getValue<number>() === 1} />,
    },
    {
      accessorKey: 'isDisplayNotification',
      header: t('Display Notifications'),
      size: 160,
      cell: (info) => <CheckMarkCell active={info.getValue<number>() === 1} />,
    },
    {
      accessorKey: 'isDataSetNotification',
      header: t('DataSet Notifications'),
      size: 160,
      cell: (info) => <CheckMarkCell active={info.getValue<number>() === 1} />,
    },
    {
      accessorKey: 'isLayoutNotification',
      header: t('Layout Notifications'),
      size: 160,
      cell: (info) => <CheckMarkCell active={info.getValue<number>() === 1} />,
    },
    {
      accessorKey: 'isLibraryNotification',
      header: t('Library Notifications'),
      size: 160,
      cell: (info) => <CheckMarkCell active={info.getValue<number>() === 1} />,
    },
    {
      accessorKey: 'isReportNotification',
      header: t('Report Notifications'),
      size: 160,
      cell: (info) => <CheckMarkCell active={info.getValue<number>() === 1} />,
    },
    {
      accessorKey: 'isScheduleNotification',
      header: t('Schedule Notifications'),
      size: 160,
      cell: (info) => <CheckMarkCell active={info.getValue<number>() === 1} />,
    },
    {
      accessorKey: 'isCustomNotification',
      header: t('Custom Notifications'),
      size: 160,
      cell: (info) => <CheckMarkCell active={info.getValue<number>() === 1} />,
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
}: GetBulkActionsProps): DataTableBulkAction<UserGroup>[] => [
  {
    label: t('Delete'),
    icon: Trash2,
    onClick: onDelete,
  },
];
