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
import {
  BarChartHorizontalBig,
  CalendarClock,
  CopyCheck,
  Edit,
  FolderInput,
  Trash2,
  UserPlus2,
} from 'lucide-react';
import { type ComponentProps } from 'react';

import type { FilterConfigItem } from '@/components/ui/FilterInputs';
import type { DataTableBulkAction } from '@/components/ui/table/DataTableBulkActions';
import {
  TextCell,
  StatusCell,
  ActionsCell,
  TagsCell,
  CheckMarkCell,
} from '@/components/ui/table/cells';
import { COMMON_FORM_OPTIONS } from '@/config/commonForms';
import type { Playlist } from '@/types/playlist';
import type { ActionItem, BaseModalType } from '@/types/table';
import type { Tag } from '@/types/tag';
import { formatDuration } from '@/utils/formatters';

export interface PlaylistFilterInput {
  userId: string;
  ownerUserGroupId: string;
  lastModified: string;
}

export type ModalType = BaseModalType | null;

export const INITIAL_FILTER_STATE: PlaylistFilterInput = {
  userId: '',
  ownerUserGroupId: '',
  lastModified: '',
};

// TODO: Needs translation
export const BASE_FILTER_KEYS: FilterConfigItem<PlaylistFilterInput>[] = [
  {
    label: 'Owner',
    name: 'userId',
    className: '',
    shouldTranslateOptions: false,
    showAllOption: false,
    options: [{ label: 'Select Owner', value: null }],
  },
  {
    label: 'User Group',
    name: 'ownerUserGroupId',
    shouldTranslateOptions: false,
    showAllOption: false,
    options: [{ label: 'Select Group', value: null }],
  },
  {
    label: 'Last Modified',
    name: 'lastModified',
    className: '',
    shouldTranslateOptions: true,
    showAllOption: false,
    options: COMMON_FORM_OPTIONS.lastModifiedFilter,
  },
];

export interface PlaylistActionsProps {
  t: TFunction;
  onDelete: (id: number) => void;
  openAddEditModal: (row: Playlist) => void;
  openShareModal?: (id: number) => void;
  openMoveModal?: (row: Playlist | Playlist[]) => void;
  copyPlaylist?: (row: number) => void;
}

export const getPlaylistItemActions = ({
  t,
  onDelete,
  openAddEditModal,
  openShareModal,
  openMoveModal,
  copyPlaylist,
}: PlaylistActionsProps): ((playlist: Playlist) => ActionItem[]) => {
  return (playlist: Playlist) => [
    // Quick Actions
    {
      label: t('Edit'),
      icon: Edit,
      onClick: () => openAddEditModal(playlist),
      isQuickAction: true,
      variant: 'primary' as const,
    },
    {
      label: t('Timeline'),
      icon: BarChartHorizontalBig,
      onClick: () => console.log('Open Playlist Editor', playlist.playlistId),
      isQuickAction: true,
    },

    // Dropdown Menu Actions
    {
      label: t('Edit'),
      icon: Edit,
      onClick: () => openAddEditModal(playlist),
    },
    {
      label: t('Make a Copy'),
      icon: CopyCheck,
      onClick: () => copyPlaylist && copyPlaylist(playlist.playlistId),
    },
    {
      label: t('Move'),
      icon: FolderInput,
      onClick: () => openMoveModal && openMoveModal(playlist),
    },
    {
      label: t('Share'),
      icon: UserPlus2,
      onClick: () => openShareModal && openShareModal(playlist.playlistId),
    },
    {
      label: t('Schedule'),
      icon: CalendarClock,
      onClick: () => console.log('Schedule', playlist.playlistId),
    },
    {
      label: t('Timeline'),
      icon: BarChartHorizontalBig,
      onClick: () => console.log('Open Playlist Editor', playlist.playlistId),
    },
    { isSeparator: true },
    {
      label: t('Enable Stats Collection'),
      onClick: () => console.log('Enable Stats', playlist.playlistId),
    },
    {
      label: t('Usage Report'),
      onClick: () => console.log('Usage Report', playlist.playlistId),
    },
    { isSeparator: true },
    {
      label: t('Delete'),
      icon: Trash2,
      onClick: () => onDelete(playlist.playlistId),
      variant: 'danger' as const,
    },
  ];
};

export const getPlaylistColumns = (props: PlaylistActionsProps): ColumnDef<Playlist>[] => {
  const { t } = props;
  const getActions = getPlaylistItemActions(props);
  return [
    {
      accessorKey: 'playlistId',
      header: t('ID'),
      size: 60,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },

    {
      accessorKey: 'name',
      header: t('Name'),
      size: 150,
      enableHiding: false,
      cell: (info) => <TextCell weight="bold">{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'tags',
      header: t('Tags'),
      enableSorting: false,
      size: 150,
      cell: (info) => {
        const tags = info.getValue<Tag[]>() || [];
        const formattedTags = tags.map((tag) => ({
          id: tag.tagId,
          label: tag.tag,
        }));
        return <TagsCell tags={formattedTags} />;
      },
    },
    {
      accessorKey: 'duration',
      header: t('Duration'),
      size: 140,
      cell: (info) => {
        return <TextCell>{formatDuration(info.getValue<number>())}</TextCell>;
      },
    },
    {
      accessorKey: 'owner',
      header: t('Owner'),
      size: 150,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'groupsWithPermissions',
      enableSorting: false,
      header: t('Sharing'),
      size: 150,
      cell: (info) => {
        const groups = info.getValue() as string;
        return <TextCell className="italic text-gray-500">{groups || t('Private')}</TextCell>;
      },
    },
    {
      accessorKey: 'isDynamic',
      header: t('Dynamic'),
      size: 100,
      cell: (info) => <CheckMarkCell active={info.getValue() === 1} />,
    },
    {
      accessorKey: 'enableStat',
      header: t('Stats'),
      size: 100,
      cell: (info) => {
        const value = info.getValue();
        if (value === 'Inherit') {
          return <StatusCell label={info.getValue() as string} type="neutral" />;
        } else {
          return <CheckMarkCell active={info.getValue() === 'on'} />;
        }
      },
    },
    {
      accessorKey: 'createdDt',
      header: t('Created'),
      size: 160,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'modifiedDt',
      header: t('Modified'),
      size: 160,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      id: 'tableActions',
      header: '',
      size: 120,
      minSize: 120,
      maxSize: 120,
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
  onMove: () => void;
  onShare: () => void;
}

export const getBulkActions = ({
  t,
  onDelete,
  onMove,
  onShare,
}: GetBulkActionsProps): DataTableBulkAction<Playlist>[] => {
  return [
    {
      label: t('Move'),
      icon: FolderInput,
      onClick: onMove,
    },
    {
      label: t('Share'),
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
