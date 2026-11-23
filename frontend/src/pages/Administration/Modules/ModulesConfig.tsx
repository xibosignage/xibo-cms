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
import { Settings, Trash2 } from 'lucide-react';
import { type ComponentProps } from 'react';

import type { FilterConfigItem } from '@/components/ui/FilterInputs';
import { CheckMarkCell, TextCell, ActionsCell } from '@/components/ui/table/cells';
import type { Module } from '@/types/module';
import type { ActionItem } from '@/types/table';

export interface ModuleFilterInput {
  name?: string | null;
}

export type ModalType = 'configure' | 'clearCache' | null;

export const INITIAL_FILTER_STATE: ModuleFilterInput = {
  name: null,
};

export const getFilterKeys = (t: TFunction): FilterConfigItem<ModuleFilterInput>[] => [
  {
    label: t('Name'),
    name: 'name',
    type: 'text',
    placeholder: ' ',
  },
];

export interface ModuleActionsProps {
  t: TFunction;
  onConfigure: (module: Module) => void;
  onClearCache: (module: Module) => void;
}

export const getModuleItemActions = ({
  t,
  onConfigure,
  onClearCache,
}: ModuleActionsProps): ((module: Module) => ActionItem[]) => {
  return (module: Module) => {
    const actions: ActionItem[] = [
      {
        label: t('Configure'),
        icon: Settings,
        onClick: () => onConfigure(module),
        isQuickAction: true,
        variant: 'primary' as const,
      },
      {
        label: t('Configure'),
        icon: Settings,
        onClick: () => onConfigure(module),
      },
    ];

    if (module.regionSpecific === 1) {
      actions.push(
        { isSeparator: true },
        {
          label: t('Clear Cache'),
          icon: Trash2,
          onClick: () => onClearCache(module),
          variant: 'danger' as const,
        },
      );
    }

    return actions;
  };
};

export const getModuleColumns = (props: ModuleActionsProps): ColumnDef<Module>[] => {
  const { t } = props;
  const getActions = getModuleItemActions(props);
  return [
    {
      accessorKey: 'name',
      header: t('Name'),
      size: 200,
      enableHiding: false,
      cell: (info) => <TextCell weight="bold">{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'description',
      header: t('Description'),
      size: 300,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'regionSpecific',
      header: t('Library Media'),
      size: 120,
      cell: (info) => <CheckMarkCell active={info.getValue<number>() === 0} />,
    },
    {
      accessorKey: 'defaultDuration',
      header: t('Default Duration'),
      size: 140,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },
    {
      accessorKey: 'previewEnabled',
      header: t('Preview Enabled'),
      size: 140,
      cell: (info) => <CheckMarkCell active={info.getValue<number>() === 1} />,
    },
    {
      accessorKey: 'assignable',
      header: t('Assignable'),
      size: 110,
      cell: (info) => <CheckMarkCell active={info.getValue<number>() === 1} />,
    },
    {
      accessorKey: 'enabled',
      header: t('Enabled'),
      size: 90,
      cell: (info) => <CheckMarkCell active={info.getValue<number>() === 1} />,
    },
    {
      accessorKey: 'isError',
      header: t('Errors'),
      size: 90,
      cell: (info) => <CheckMarkCell active={!info.getValue<boolean>()} />,
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
