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
import { Pencil } from 'lucide-react';
import { type ComponentProps } from 'react';

import { ActionsCell, CheckMarkCell, TextCell } from '@/components/ui/table/cells';
import type { ActionItem } from '@/types/table';
import type { Transition } from '@/types/transition';

export type ModalType = 'edit' | null;

export interface TransitionActionsProps {
  t: TFunction;
  onEdit: (transition: Transition) => void;
}

export const getTransitionItemActions = ({
  t,
  onEdit,
}: TransitionActionsProps): ((transition: Transition) => ActionItem[]) => {
  return (transition: Transition) => [
    {
      label: t('Edit'),
      icon: Pencil,
      onClick: () => onEdit(transition),
      isQuickAction: true,
      variant: 'primary' as const,
    },
  ];
};

export const getTransitionColumns = (props: TransitionActionsProps): ColumnDef<Transition>[] => {
  const { t } = props;
  const getActions = getTransitionItemActions(props);
  return [
    {
      accessorKey: 'transitionId',
      header: t('ID'),
      size: 80,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },
    {
      accessorKey: 'transition',
      header: t('Name'),
      size: 200,
      enableHiding: false,
      cell: (info) => <TextCell weight="bold">{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'code',
      header: t('Code'),
      size: 100,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'hasDirection',
      header: t('Has Direction'),
      size: 140,
      cell: (info) => <CheckMarkCell active={info.getValue<number>() === 1} />,
    },
    {
      accessorKey: 'hasDuration',
      header: t('Has Duration'),
      size: 140,
      cell: (info) => <CheckMarkCell active={info.getValue<number>() === 1} />,
    },
    {
      accessorKey: 'availableAsIn',
      header: t('Enabled for In'),
      size: 150,
      cell: (info) => <CheckMarkCell active={info.getValue<number>() === 1} />,
    },
    {
      accessorKey: 'availableAsOut',
      header: t('Enabled for Out'),
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
