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
import { Edit, Trash2, UserPlus2 } from 'lucide-react';
import { type ComponentProps } from 'react';

import type { FilterConfigItem } from '@/components/ui/FilterInputs';
import type { DataTableBulkAction } from '@/components/ui/table/DataTableBulkActions';
import { TextCell, ActionsCell } from '@/components/ui/table/cells';
import type { Command } from '@/types/command';
import type { ActionItem, BaseModalType } from '@/types/table';

export interface CommandsFilterInput {
  name: string;
  code: string;
  logicalOperatorName?: 'OR' | 'AND';
  useRegexForName?: boolean;
  logicalOperatorCode?: 'OR' | 'AND';
  useRegexForCode?: boolean;
}

export type ModalType = BaseModalType | null;

export const INITIAL_FILTER_STATE: CommandsFilterInput = {
  name: '',
  code: '',
  logicalOperatorName: 'OR',
  useRegexForName: false,
  logicalOperatorCode: 'OR',
  useRegexForCode: false,
};

export const getFilterKeys = (t: TFunction): FilterConfigItem<CommandsFilterInput>[] => [
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
  {
    label: t('Code'),
    name: 'code',
    type: 'text',
    placeholder: ' ',
    showAndOr: true,
    andOrKey: 'logicalOperatorCode',
    showRegex: true,
    regexKey: 'useRegexForCode',
  },
];

export interface CommandActionsProps {
  t: TFunction;
  onDelete: (id: number) => void;
  openEditModal: (row: Command) => void;
  openShareModal: (row: Command) => void;
}

export const getCommandItemActions = ({
  t,
  onDelete,
  openEditModal,
  openShareModal,
}: CommandActionsProps): ((command: Command) => ActionItem[]) => {
  return (command: Command) => [
    // Quick actions
    {
      label: t('Edit'),
      icon: Edit,
      onClick: () => openEditModal(command),
      isQuickAction: true,
      variant: 'primary' as const,
    },

    // Dropdown menu actions
    {
      label: t('Edit'),
      icon: Edit,
      onClick: () => openEditModal(command),
    },
    {
      label: t('Share'),
      icon: UserPlus2,
      onClick: () => openShareModal(command),
    },
    { isSeparator: true },
    {
      label: t('Delete'),
      icon: Trash2,
      onClick: () => onDelete(command.commandId),
      variant: 'danger' as const,
    },
  ];
};

export const getCommandColumns = (props: CommandActionsProps): ColumnDef<Command>[] => {
  const { t } = props;
  const getActions = getCommandItemActions(props);
  return [
    {
      accessorKey: 'commandId',
      header: t('ID'),
      size: 80,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },
    {
      accessorKey: 'command',
      header: t('Name'),
      size: 200,
      enableHiding: false,
      cell: (info) => <TextCell weight="bold">{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'code',
      header: t('Code'),
      size: 150,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'description',
      header: t('Description'),
      size: 250,
      cell: (info) => <TextCell className="truncate">{info.getValue<string>() ?? ''}</TextCell>,
    },
    {
      accessorKey: 'availableOn',
      header: t('Available On'),
      size: 180,
      cell: (info) => <TextCell>{info.getValue<string>() ?? t('All')}</TextCell>,
    },
    {
      accessorKey: 'createAlertOn',
      header: t('Create Alert On'),
      size: 140,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'groupsWithPermissions',
      header: t('Sharing'),
      size: 160,
      cell: (info) => <TextCell>{info.getValue<string>() ?? ''}</TextCell>,
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
  onShare: () => void;
}

export const getBulkActions = ({
  t,
  onDelete,
  onShare,
}: GetBulkActionsProps): DataTableBulkAction<Command>[] => {
  return [
    {
      label: t('Share Selected'),
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
