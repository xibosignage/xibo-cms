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
import { BarChart2, Edit, Trash2 } from 'lucide-react';
import { type ComponentProps } from 'react';

import type { FilterConfigItem } from '@/components/ui/FilterInputs';
import type { DataTableBulkAction } from '@/components/ui/table/DataTableBulkActions';
import { ActionsCell, CheckMarkCell, TextCell } from '@/components/ui/table/cells';
import type { ActionItem, BaseModalType } from '@/types/table';
import type { Tag } from '@/types/tag';

export interface TagFilterInput {
  tagId: number | null;
  tag: string | null;
  logicalOperatorName: 'AND' | 'OR' | null;
  useRegexForName: boolean | null;
  isSystem: number | null;
  haveOptions: number | null;
}

export type ModalType = BaseModalType | 'usage';

export const INITIAL_FILTER_STATE: TagFilterInput = {
  tagId: null,
  tag: null,
  logicalOperatorName: null,
  useRegexForName: null,
  isSystem: null,
  haveOptions: null,
};

export const getBaseFilterKeys = (t: TFunction): FilterConfigItem<TagFilterInput>[] => [
  {
    label: t('ID'),
    name: 'tagId',
    type: 'number',
  },
  {
    label: t('Name'),
    placeholder: ' ',
    name: 'tag',
    type: 'text',
    showAndOr: true,
    andOrKey: 'logicalOperatorName',
    showRegex: true,
    regexKey: 'useRegexForName',
  },
  {
    label: t('Show System Tags?'),
    name: 'isSystem',
    type: 'select',
    options: [
      { label: t('All'), value: null },
      { label: t('Yes'), value: 1 },
      { label: t('No'), value: 0 },
    ],
  },
  {
    label: t('Show Only Tags with Values?'),
    name: 'haveOptions',
    type: 'select',
    options: [
      { label: t('All'), value: null },
      { label: t('Yes'), value: 1 },
      { label: t('No'), value: 0 },
    ],
  },
];

export interface TagActionsProps {
  t: TFunction;
  onDelete: (tagId: number) => void;
  onEdit: (tag: Tag) => void;
  onUsage: (tag: Tag) => void;
  isSuperAdmin: boolean;
}

export const getTagItemActions = ({
  t,
  onDelete,
  onEdit,
  onUsage,
  isSuperAdmin,
}: TagActionsProps): ((tag: Tag) => ActionItem[]) => {
  return (tag: Tag) => {
    const actions: ActionItem[] = [];

    if (isSuperAdmin && tag.isSystem === 0) {
      actions.push(
        {
          label: t('Edit'),
          icon: Edit,
          onClick: () => onEdit(tag),
          isQuickAction: true,
          variant: 'primary' as const,
        },
        {
          label: t('Edit'),
          icon: Edit,
          onClick: () => onEdit(tag),
        },
        { isSeparator: true },
        {
          label: t('Delete'),
          icon: Trash2,
          onClick: () => onDelete(tag.tagId),
          variant: 'danger' as const,
        },
        { isSeparator: true },
      );
    }

    actions.push({
      label: t('Usage'),
      icon: BarChart2,
      onClick: () => onUsage(tag),
    });

    return actions;
  };
};

export const getTagColumns = (props: TagActionsProps): ColumnDef<Tag>[] => {
  const { t } = props;
  const getActions = getTagItemActions(props);
  return [
    {
      accessorKey: 'tagId',
      header: t('ID'),
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },
    {
      accessorKey: 'tag',
      header: t('Name'),
      enableHiding: false,
      cell: (info) => <TextCell weight="bold">{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'isRequired',
      header: t('Required Value?'),
      cell: (info) => <CheckMarkCell active={!!info.getValue<number>()} />,
    },
    {
      accessorKey: 'options',
      header: t('Values'),
      cell: (info) => {
        const raw = info.getValue<string | null>();
        if (!raw) return <TextCell>{''}</TextCell>;
        try {
          const parsed = JSON.parse(raw) as string[];
          return <TextCell>{Array.isArray(parsed) ? parsed.join(', ') : raw}</TextCell>;
        } catch {
          return <TextCell>{raw}</TextCell>;
        }
      },
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
  isSuperAdmin: boolean;
}

export const getBulkActions = ({
  t,
  onDelete,
  isSuperAdmin,
}: GetBulkActionsProps): DataTableBulkAction<Tag>[] => {
  if (!isSuperAdmin) return [];
  return [
    {
      label: t('Delete Selected'),
      icon: Trash2,
      onClick: onDelete,
    },
  ];
};
