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
import { Download, Edit, Trash2 } from 'lucide-react';
import { type ComponentProps } from 'react';

import type { FilterConfigItem } from '@/components/ui/FilterInputs';
import type { DataTableBulkAction } from '@/components/ui/table/DataTableBulkActions';
import { TextCell, ActionsCell } from '@/components/ui/table/cells';
import type { PlayerVersion } from '@/types/playerVersion';
import type { ActionItem, BaseModalType } from '@/types/table';

export interface PlayerVersionFilterInput {
  type: string | null;
  version: string | null;
  code: string | null;
}

export type ModalType = BaseModalType | null;

export const INITIAL_FILTER_STATE: PlayerVersionFilterInput = {
  type: null,
  version: null,
  code: null,
};

export const PLAYER_VERSION_ACCEPTED_EXTENSIONS: Record<string, string[]> = {
  'application/vnd.android.package-archive': ['.apk'],
  'application/octet-stream': ['.ipk', '.wgt'],
};

export const getTypeOptions = (t: TFunction): { label: string; value: string }[] => [
  { label: t('Android'), value: 'android' },
  { label: t('webOS'), value: 'lg' },
  { label: t('Tizen'), value: 'sssp' },
];

export const getBaseFilterKeys = (
  t: TFunction,
  versionOptions: { label: string; value: string }[] = [],
): FilterConfigItem<PlayerVersionFilterInput>[] => [
  {
    label: t('Type'),
    name: 'type',
    className: '',
    options: getTypeOptions(t),
  },
  {
    label: t('Version'),
    name: 'version',
    className: '',
    options: versionOptions,
  },
  {
    label: t('Code'),
    name: 'code',
    type: 'text',
    className: '',
    placeholder: ' ',
  },
];

interface PlayerVersionActionsProps {
  t: TFunction;
  onDelete: (id: number) => void;
  onDownload: (playerVersion: PlayerVersion) => void;
  openEditModal: (row: PlayerVersion) => void;
}

const getPlayerVersionItemActions = ({
  t,
  onDelete,
  onDownload,
  openEditModal,
}: PlayerVersionActionsProps): ((playerVersion: PlayerVersion) => ActionItem[]) => {
  return (playerVersion: PlayerVersion) => [
    // Quick actions
    {
      label: t('Edit'),
      icon: Edit,
      onClick: () => openEditModal(playerVersion),
      isQuickAction: true,
      variant: 'primary' as const,
    },
    {
      label: t('Download'),
      icon: Download,
      onClick: () => onDownload(playerVersion),
      isQuickAction: true,
    },

    // Dropdown menu actions
    {
      label: t('Edit'),
      icon: Edit,
      onClick: () => openEditModal(playerVersion),
    },
    {
      label: t('Download'),
      icon: Download,
      onClick: () => onDownload(playerVersion),
    },
    { isSeparator: true },
    {
      label: t('Delete'),
      icon: Trash2,
      onClick: () => onDelete(playerVersion.versionId),
      variant: 'danger' as const,
    },
  ];
};

export const getPlayerVersionColumns = (
  props: PlayerVersionActionsProps,
): ColumnDef<PlayerVersion>[] => {
  const { t } = props;
  const getActions = getPlayerVersionItemActions(props);
  return [
    {
      accessorKey: 'versionId',
      header: t('Version ID'),
      size: 120,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'playerShowVersion',
      header: t('Player Version Name'),
      size: 200,
      enableHiding: false,
      cell: (info) => <TextCell weight="bold">{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'type',
      header: t('Type'),
      size: 120,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'version',
      header: t('Version'),
      size: 140,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'code',
      header: t('Code'),
      size: 100,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },
    {
      accessorKey: 'fileName',
      header: t('File Name'),
      size: 200,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'fileSizeFormatted',
      header: t('Size'),
      size: 120,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'createdAt',
      header: t('Created At'),
      size: 160,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'modifiedAt',
      header: t('Modified At'),
      size: 160,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'modifiedBy',
      header: t('Modified By'),
      size: 140,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      id: 'tableActions',
      header: '',
      size: 110,
      minSize: 110,
      maxSize: 110,
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
}: GetBulkActionsProps): DataTableBulkAction<PlayerVersion>[] => {
  return [
    {
      label: t('Delete Selected'),
      icon: Trash2,
      onClick: onDelete,
    },
  ];
};
