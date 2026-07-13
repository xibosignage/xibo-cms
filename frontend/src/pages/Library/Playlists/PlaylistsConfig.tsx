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
  Clock,
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
import { getCommonFormOptions } from '@/config/commonForms';
import type { Playlist } from '@/types/playlist';
import type { ActionItem, BaseModalType } from '@/types/table';
import type { Tag } from '@/types/tag';
import type { DateLike } from '@/utils/date';
import { formatDuration } from '@/utils/formatters';

export interface PlaylistFilterInput {
  playlistId?: number | null;
  name?: string;
  tags?: Tag[];
  userId?: string;
  ownerUserGroupId?: string;
  layoutId?: number | null;
  lastModified?: string;
  logicalOperatorName?: 'OR' | 'AND';
  useRegexForName?: boolean;
  logicalOperator?: 'OR' | 'AND';
  exactTags?: boolean;
}

export type ModalType = BaseModalType | 'schedule' | 'enableStats' | 'usageReport' | null;

export const INITIAL_FILTER_STATE: PlaylistFilterInput = {
  playlistId: null,
  name: '',
  tags: [],
  userId: '',
  ownerUserGroupId: '',
  layoutId: null,
  lastModified: '',
  logicalOperatorName: 'OR',
  useRegexForName: false,
  logicalOperator: 'OR',
  exactTags: false,
};

export const getBaseFilterKeys = (t: TFunction): FilterConfigItem<PlaylistFilterInput>[] => [
  {
    label: t('ID'),
    placeholder: ' ',
    name: 'playlistId',
    type: 'number',
  },
  {
    label: t('Name'),
    name: 'name',
    type: 'text',
    className: '',
    placeholder: ' ',
    showAndOr: true,
    andOrKey: 'logicalOperatorName',
    showRegex: true,
    regexKey: 'useRegexForName',
  },
  {
    label: t('Tags'),
    name: 'tags',
    type: 'tags',
    placeholder: ' ',
    className: '',
    showAndOr: true,
    andOrKey: 'logicalOperator',
    showExactTags: true,
    exactTagsKey: 'exactTags',
  },
  {
    label: t('Owner'),
    name: 'userId',
    className: '',
    options: [{ label: t('Select Owner'), value: null }],
  },
  {
    label: t('User Group'),
    name: 'ownerUserGroupId',
    options: [{ label: t('Select Group'), value: null }],
  },
  {
    label: t('Layout ID'),
    name: 'layoutId',
    type: 'number',
    className: '',
    placeholder: ' ',
  },
  {
    label: t('Last Modified'),
    name: 'lastModified',
    className: '',
    type: 'date-range',
    options: getCommonFormOptions(t).lastModifiedFilter,
  },
];

export interface PlaylistActionsProps {
  t: TFunction;
  formatDateTime: (value: DateLike) => string;
  onDelete: (id: number) => void;
  openAddEditModal: (row: Playlist) => void;
  openShareModal?: (id: number) => void;
  openMoveModal?: (row: Playlist | Playlist[]) => void;
  copyPlaylist?: (row: number) => void;
  openScheduleModal?: (row: Playlist) => void;
  openTimeline?: (id: number) => void;
  openEnableStatsModal?: (id: number) => void;
  openUsageReportModal?: (id: number) => void;
}

export const getPlaylistItemActions = ({
  t,
  onDelete,
  openAddEditModal,
  openShareModal,
  openMoveModal,
  copyPlaylist,
  openScheduleModal,
  openTimeline,
  openEnableStatsModal,
  openUsageReportModal,
}: PlaylistActionsProps): ((playlist: Playlist) => ActionItem[]) => {
  return (playlist: Playlist) => {
    const isDynamic = Boolean(playlist.isDynamic);

    return [
      // Quick Actions
      {
        label: t('Edit'),
        icon: Edit,
        onClick: () => openAddEditModal(playlist),
        isQuickAction: true,
        variant: 'primary' as const,
      },
      ...(!isDynamic
        ? [
            {
              label: t('Timeline'),
              icon: BarChartHorizontalBig,
              onClick: () => openTimeline && openTimeline(playlist.playlistId),
              isQuickAction: true,
            },
          ]
        : []),

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
      ...(openScheduleModal
        ? [
            {
              label: t('Schedule'),
              icon: CalendarClock,
              onClick: () => openScheduleModal(playlist),
            },
          ]
        : []),
      ...(!isDynamic
        ? [
            {
              label: t('Timeline'),
              icon: BarChartHorizontalBig,
              onClick: () => openTimeline && openTimeline(playlist.playlistId),
            },
          ]
        : []),
      { isSeparator: true },
      {
        label: t('Enable Stats Collection'),
        onClick: () => openEnableStatsModal && openEnableStatsModal(playlist.playlistId),
      },
      {
        label: t('Usage Report'),
        onClick: () => openUsageReportModal && openUsageReportModal(playlist.playlistId),
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
};

export const getPlaylistColumns = (props: PlaylistActionsProps): ColumnDef<Playlist>[] => {
  const { t, formatDateTime } = props;
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
          label: tag.value ? `${tag.tag}|${tag.value}` : tag.tag,
        }));
        return <TagsCell tags={formattedTags} />;
      },
    },
    {
      accessorKey: 'duration',
      header: t('Duration'),
      size: 140,
      cell: (info) => {
        const duration = info.getValue<number>();
        const requiresDurationUpdate = info.row.original.requiresDurationUpdate;

        if (requiresDurationUpdate === 1) {
          return (
            <TextCell>
              <span
                title={t(
                  'Changes have been made and we are recalculating this Playlist’s duration',
                )}
              >
                <Clock className="size-4 text-gray-500" />
              </span>
            </TextCell>
          );
        }

        if (requiresDurationUpdate) {
          return (
            <TextCell>
              {formatDuration(duration)}
              <span
                title={t('This duration will be updated at {{date}}', {
                  date: formatDateTime(new Date(requiresDurationUpdate * 1000)),
                })}
              >
                <Clock className="size-4 text-gray-500" />
              </span>
            </TextCell>
          );
        }

        return <TextCell>{formatDuration(duration)}</TextCell>;
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
      cell: (info) => <CheckMarkCell active={Boolean(info.getValue())} />,
    },
    {
      accessorKey: 'enableStat',
      header: t('Stats'),
      size: 100,
      cell: (info) => {
        const value = info.getValue();
        if (!value || value === 'Inherit') {
          return <StatusCell label={value ? (value as string) : 'Inherit'} type="neutral" />;
        }

        return <CheckMarkCell active={String(value).toLowerCase() === 'on'} />;
      },
    },
    {
      accessorKey: 'createdDt',
      header: t('Created'),
      size: 160,
      cell: (info) => <TextCell>{formatDateTime(info.getValue<string>())}</TextCell>,
    },
    {
      accessorKey: 'modifiedDt',
      header: t('Modified'),
      size: 160,
      cell: (info) => <TextCell>{formatDateTime(info.getValue<string>())}</TextCell>,
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
  onMove?: () => void;
  onShare: () => void;
}

export const getBulkActions = ({
  t,
  onDelete,
  onMove,
  onShare,
}: GetBulkActionsProps): DataTableBulkAction<Playlist>[] => {
  return [
    ...(onMove
      ? [
          {
            label: t('Move'),
            icon: FolderInput,
            onClick: onMove,
          },
        ]
      : []),
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
