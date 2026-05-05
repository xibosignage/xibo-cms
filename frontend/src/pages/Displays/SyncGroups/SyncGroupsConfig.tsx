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
import { Edit, Trash2, Users } from 'lucide-react';
import { type ComponentProps } from 'react';

import type { FilterConfigItem } from '@/components/ui/FilterInputs';
import type { DataTableBulkAction } from '@/components/ui/table/DataTableBulkActions';
import { TextCell, ActionsCell } from '@/components/ui/table/cells';
import type { SyncGroup } from '@/types/syncGroup';
import type { ActionItem, BaseModalType } from '@/types/table';

export interface SyncGroupsFilterInput {
  leadDisplayId: number | null;
}

export type ModalType = BaseModalType | 'members' | null;

export const INITIAL_FILTER_STATE: SyncGroupsFilterInput = {
  leadDisplayId: null,
};

export const getFilterKeys = (t: TFunction): FilterConfigItem<SyncGroupsFilterInput>[] => [
  {
    label: t('Lead Display ID'),
    name: 'leadDisplayId',
    type: 'number',
    placeholder: t('Enter lead display ID...'),
    className: 'w-48',
  },
];

export interface SyncGroupActionsProps {
  t: TFunction;
  onDelete: (id: number) => void;
  openEditModal: (row: SyncGroup) => void;
  openMembersModal: (row: SyncGroup) => void;
}

export const getSyncGroupItemActions = ({
  t,
  onDelete,
  openEditModal,
  openMembersModal,
}: SyncGroupActionsProps): ((syncGroup: SyncGroup) => ActionItem[]) => {
  return (syncGroup: SyncGroup) => [
    // Quick actions
    {
      label: t('Edit'),
      icon: Edit,
      onClick: () => openEditModal(syncGroup),
      isQuickAction: true,
      variant: 'primary' as const,
    },

    // Dropdown menu actions
    {
      label: t('Edit'),
      icon: Edit,
      onClick: () => openEditModal(syncGroup),
    },
    {
      label: t('Members'),
      icon: Users,
      onClick: () => openMembersModal(syncGroup),
    },
    { isSeparator: true },
    {
      label: t('Delete'),
      icon: Trash2,
      onClick: () => onDelete(syncGroup.syncGroupId),
      variant: 'danger' as const,
    },
  ];
};

export const getSyncGroupColumns = (props: SyncGroupActionsProps): ColumnDef<SyncGroup>[] => {
  const { t } = props;
  const getActions = getSyncGroupItemActions(props);
  return [
    {
      accessorKey: 'syncGroupId',
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
      accessorKey: 'createdDt',
      header: t('Created Date'),
      size: 180,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'modifiedDt',
      header: t('Modified Date'),
      size: 180,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'owner',
      header: t('Owner'),
      size: 150,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'modifiedByName',
      header: t('Modified By'),
      size: 150,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'syncPublisherPort',
      header: t('Publisher Port'),
      size: 130,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },
    {
      accessorKey: 'syncSwitchDelay',
      header: t('Switch Delay'),
      size: 130,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },
    {
      accessorKey: 'syncVideoPauseDelay',
      header: t('Video Pause Delay'),
      size: 150,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },
    {
      accessorKey: 'leadDisplay',
      header: t('Lead Display'),
      size: 180,
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
  onDelete: () => void;
}

export const getBulkActions = ({
  t,
  onDelete,
}: GetBulkActionsProps): DataTableBulkAction<SyncGroup>[] => {
  return [
    {
      label: t('Delete Selected'),
      icon: Trash2,
      onClick: onDelete,
    },
  ];
};
