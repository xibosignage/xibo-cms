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
import { Edit, Trash2 } from 'lucide-react';
import { type ComponentProps } from 'react';

import type { FilterConfigItem } from '@/components/ui/FilterInputs';
import type { DataTableBulkAction } from '@/components/ui/table/DataTableBulkActions';
import { TextCell, ActionsCell } from '@/components/ui/table/cells';
import type { Application } from '@/types/application';
import type { ActionItem, BaseModalType } from '@/types/table';

export interface ApplicationFilterInput {
  name: string | null;
  logicalOperatorName: 'AND' | 'OR' | null;
  useRegexForName: boolean | null;
}

export type ModalType = BaseModalType;

export const INITIAL_FILTER_STATE: ApplicationFilterInput = {
  name: null,
  logicalOperatorName: null,
  useRegexForName: null,
};

export const getBaseFilterKeys = (t: TFunction): FilterConfigItem<ApplicationFilterInput>[] => [
  {
    label: t('Name'),
    placeholder: ' ',
    name: 'name',
    type: 'text',
    showAndOr: true,
    andOrKey: 'logicalOperatorName',
    showRegex: true,
    regexKey: 'useRegexForName',
  },
];

export interface ApplicationActionsProps {
  t: TFunction;
  onDelete: (key: string) => void;
  onEdit: (application: Application) => void;
}

export const getApplicationItemActions = ({
  t,
  onDelete,
  onEdit,
}: ApplicationActionsProps): ((application: Application) => ActionItem[]) => {
  return (application: Application) => [
    {
      label: t('Edit'),
      icon: Edit,
      onClick: () => onEdit(application),
      isQuickAction: true,
      variant: 'primary' as const,
    },
    {
      label: t('Edit'),
      icon: Edit,
      onClick: () => onEdit(application),
    },
    {
      label: t('Delete'),
      icon: Trash2,
      onClick: () => onDelete(application.key),
      variant: 'danger' as const,
    },
  ];
};

export const getApplicationColumns = (props: ApplicationActionsProps): ColumnDef<Application>[] => {
  const { t } = props;
  const getActions = getApplicationItemActions(props);
  return [
    {
      accessorKey: 'name',
      header: t('Name'),
      enableHiding: false,
      cell: (info) => <TextCell weight="bold">{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'owner',
      header: t('Owner'),
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
}: GetBulkActionsProps): DataTableBulkAction<Application>[] => {
  return [
    {
      label: t('Delete Selected'),
      icon: Trash2,
      onClick: onDelete,
    },
  ];
};
