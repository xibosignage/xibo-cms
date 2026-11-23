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
import { Copy, Edit, Trash2 } from 'lucide-react';
import { type ComponentProps } from 'react';

import type { FilterConfigItem } from '@/components/ui/FilterInputs';
import type { DataTableBulkAction } from '@/components/ui/table/DataTableBulkActions';
import { TextCell, ActionsCell, CheckMarkCell } from '@/components/ui/table/cells';
import type { DisplayProfile } from '@/types/displayProfile';
import type { ActionItem, BaseModalType } from '@/types/table';

export interface DisplayProfileFilterInput {
  displayProfile: string;
  type: string | null;
  logicalOperatorName?: 'OR' | 'AND';
  useRegexForName?: boolean;
}

export type ModalType = BaseModalType | 'add' | 'copy' | null;

export const INITIAL_FILTER_STATE: DisplayProfileFilterInput = {
  displayProfile: '',
  type: null,
  logicalOperatorName: 'OR',
  useRegexForName: false,
};

export const getTypeOptions = (t: TFunction): { label: string; value: string }[] => [
  { label: t('Android'), value: 'android' },
  { label: t('Windows'), value: 'windows' },
  { label: t('Linux'), value: 'linux' },
  { label: t('webOS'), value: 'lg' },
  { label: t('Tizen'), value: 'sssp' },
  { label: t('ChromeOS'), value: 'chromeOS' },
  { label: t('Hisense'), value: 'hisense' },
];

export const getBaseFilterKeys = (t: TFunction): FilterConfigItem<DisplayProfileFilterInput>[] => [
  {
    label: t('Name'),
    name: 'displayProfile',
    type: 'text',
    className: '',
    placeholder: ' ',
    showAndOr: true,
    andOrKey: 'logicalOperatorName',
    showRegex: true,
    regexKey: 'useRegexForName',
  },
  {
    label: t('Type'),
    name: 'type',
    className: '',
    options: getTypeOptions(t),
  },
];

export interface DisplayProfileActionsProps {
  t: TFunction;
  canModify?: boolean;
  currentUserId?: number;
  isSuperAdmin?: boolean;
  onDelete: (id: number) => void;
  openEditModal: (row: DisplayProfile) => void;
  openCopyModal: (row: DisplayProfile) => void;
}

export const getDisplayProfileItemActions = ({
  t,
  canModify = false,
  currentUserId,
  isSuperAdmin = false,
  onDelete,
  openEditModal,
  openCopyModal,
}: DisplayProfileActionsProps): ((displayProfile: DisplayProfile) => ActionItem[]) => {
  return (displayProfile: DisplayProfile) => {
    const isOwnerOrSuper = isSuperAdmin || displayProfile.userId === currentUserId;

    const actions: ActionItem[] = [];

    if (canModify && isOwnerOrSuper) {
      actions.push({
        label: t('Edit'),
        icon: Edit,
        onClick: () => openEditModal(displayProfile),
        isQuickAction: true,
        variant: 'primary' as const,
      });
    }

    if (canModify && isOwnerOrSuper) {
      actions.push({
        label: t('Edit'),
        icon: Edit,
        onClick: () => openEditModal(displayProfile),
      });
    }

    if (canModify && isOwnerOrSuper) {
      actions.push({
        label: t('Copy'),
        icon: Copy,
        onClick: () => openCopyModal(displayProfile),
      });
    }

    if (canModify && isOwnerOrSuper) {
      actions.push({ isSeparator: true });
      actions.push({
        label: t('Delete'),
        icon: Trash2,
        onClick: () => onDelete(displayProfile.displayProfileId),
        variant: 'danger' as const,
      });
    }

    return actions;
  };
};

export const getDisplayProfileColumns = (
  props: DisplayProfileActionsProps,
): ColumnDef<DisplayProfile>[] => {
  const { t } = props;
  const getActions = getDisplayProfileItemActions(props);
  return [
    {
      accessorKey: 'displayProfileId',
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
      accessorKey: 'type',
      header: t('Type'),
      size: 140,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'isDefault',
      header: t('Default'),
      size: 100,
      cell: (info) => <CheckMarkCell active={info.getValue() === 1} />,
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
  canModify?: boolean;
  onDelete: () => void;
}

export const getBulkActions = ({
  t,
  canModify = false,
  onDelete,
}: GetBulkActionsProps): DataTableBulkAction<DisplayProfile>[] => {
  const actions: DataTableBulkAction<DisplayProfile>[] = [];

  if (canModify) {
    actions.push({
      label: t('Delete Selected'),
      icon: Trash2,
      onClick: onDelete,
    });
  }

  return actions;
};
