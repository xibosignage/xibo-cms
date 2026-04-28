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
import { Copy, Edit, Trash2 } from 'lucide-react';
import { type ComponentProps } from 'react';

import type { FilterConfigItem } from '@/components/ui/FilterInputs';
import type { DataTableBulkAction } from '@/components/ui/table/DataTableBulkActions';
import { TextCell, ActionsCell, CheckMarkCell } from '@/components/ui/table/cells';
import type { DisplayProfile } from '@/types/displayProfile';
import type { ActionItem, BaseModalType } from '@/types/table';

export interface DisplayProfileFilterInput {
  type: string | null;
}

export type ModalType = BaseModalType | 'add' | 'copy' | null;

export const INITIAL_FILTER_STATE: DisplayProfileFilterInput = {
  type: null,
};

export const getTypeOptions = (t: TFunction): { label: string; value: string }[] => [
  { label: t('Android'), value: 'android' },
  { label: t('Windows'), value: 'windows' },
  { label: t('Linux'), value: 'linux' },
  { label: t('webOS'), value: 'lg' },
  { label: t('Tizen'), value: 'sssp' },
  { label: t('ChromeOS'), value: 'chromeOS' },
];

export const getBaseFilterKeys = (t: TFunction): FilterConfigItem<DisplayProfileFilterInput>[] => [
  {
    label: t('Type'),
    name: 'type',
    className: '',
    shouldTranslateOptions: false,
    showAllOption: true,
    options: getTypeOptions(t),
  },
];

export interface DisplayProfileActionsProps {
  t: TFunction;
  onDelete: (id: number) => void;
  openEditModal: (row: DisplayProfile) => void;
  openCopyModal: (row: DisplayProfile) => void;
}

export const getDisplayProfileItemActions = ({
  t,
  onDelete,
  openEditModal,
  openCopyModal,
}: DisplayProfileActionsProps): ((displayProfile: DisplayProfile) => ActionItem[]) => {
  return (displayProfile: DisplayProfile) => [
    // Quick actions
    {
      label: t('Edit'),
      icon: Edit,
      onClick: () => openEditModal(displayProfile),
      isQuickAction: true,
      variant: 'primary' as const,
    },

    // Dropdown menu actions
    {
      label: t('Edit'),
      icon: Edit,
      onClick: () => openEditModal(displayProfile),
    },
    {
      label: t('Copy'),
      icon: Copy,
      onClick: () => openCopyModal(displayProfile),
    },
    { isSeparator: true },
    {
      label: t('Delete'),
      icon: Trash2,
      onClick: () => onDelete(displayProfile.displayProfileId),
      variant: 'danger' as const,
    },
  ];
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
  onDelete: () => void;
}

export const getBulkActions = ({
  t,
  onDelete,
}: GetBulkActionsProps): DataTableBulkAction<DisplayProfile>[] => {
  return [
    {
      label: t('Delete Selected'),
      icon: Trash2,
      onClick: onDelete,
    },
  ];
};
