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
  Image as ImageIcon,
  Film,
  Music,
  FileText,
  Archive,
  File as FileIcon,
  Edit,
  Download,
  CopyCheck,
  FolderInput,
  UserPlus2,
  CalendarClock,
  Info,
  Trash2,
  MoreVertical,
} from 'lucide-react';
import { type ComponentProps, type ElementType } from 'react';

import type { FilterConfigItem } from '@/components/ui/FilterInputs';
import type { DataTableBulkAction } from '@/components/ui/table/DataTableBulkActions';
import {
  MediaCell,
  CheckMarkCell,
  TextCell,
  StatusCell,
  ActionsCell,
  TagsCell,
} from '@/components/ui/table/cells';
import { APP_ROUTES } from '@/config/appRoutes';
import type { Media } from '@/types/media';

export const LIBRARY_TABS = APP_ROUTES.find((r) => r.path === 'library')?.subLinks || [];

export interface MediaFilterInput {
  type: string;
  ownerId: string;
  ownerUserGroupId: string;
  orientation: string;
  retired?: number;
  lastModified: string;
}

interface ApiTag {
  tagId: number;
  tag: string;
}

export const getMediaIcon = (mediaType: string) => {
  const type = mediaType?.toLowerCase();

  switch (type) {
    case 'image':
      return ImageIcon;
    case 'video':
      return Film;
    case 'audio':
      return Music;
    case 'pdf':
      return FileText;
    case 'archive':
      return Archive;
    default:
      return FileIcon;
  }
};

export type ActionItem =
  | {
      isSeparator: true;
      label?: never;
      icon?: never;
      onClick?: never;
      variant?: never;
      isQuickAction?: never;
    }
  | {
      isSeparator?: false | undefined;
      label: string;
      icon?: ElementType;
      onClick?: () => void;
      variant?: 'default' | 'primary' | 'danger';
      isQuickAction?: boolean;
    };

type MediaType = 'image' | 'video' | 'audio' | 'pdf' | 'archive' | 'other';

export const INITIAL_FILTER_STATE: MediaFilterInput = {
  type: '',
  ownerId: '',
  ownerUserGroupId: '',
  orientation: '',
  lastModified: '',
};

export const BASE_FILTER_KEYS: FilterConfigItem<MediaFilterInput>[] = [
  {
    label: 'Type',
    name: 'type',
    className: '',
    shouldTranslateOptions: true,
    options: [
      { label: 'Image', value: 'image' },
      { label: 'Video', value: 'video' },
      { label: 'Audio', value: 'audio' },
      { label: 'PDF', value: 'pdf' },
      { label: 'Archive', value: 'archive' },
      { label: 'Other', value: 'other' },
    ],
  },
  {
    label: 'Owner',
    name: 'ownerId',
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
    label: 'Orientation',
    name: 'orientation',
    className: '',
    options: [
      { label: 'Portrait', value: 'portrait' },
      { label: 'Landscape', value: 'landscape' },
      { label: 'Square', value: 'square' },
    ],
  },
  {
    label: 'Retired',
    name: 'retired',
    className: 'max-w-auto md:max-w-[100px]',
    shouldTranslateOptions: true,
    showAllOption: false,
    options: [
      { label: 'Any', value: null },
      { label: 'No', value: 0 },
      { label: 'Yes', value: 1 },
    ],
  },
  {
    label: 'Last Modified',
    name: 'lastModified',
    className: '',
    shouldTranslateOptions: true,
    showAllOption: false,
    options: [
      { label: 'Any time', value: '' },
      { label: 'Today', value: 'today' },
      { label: 'Last 7 days', value: '7d' },
      { label: 'Last 30 days', value: '30d' },
      { label: 'This year', value: '1y' },
    ],
  },
];

export const MEDIA_FORM_OPTIONS = {
  folders: {
    myFiles: ['Folder 1', 'Folder 2', 'Folder 3'],
    home: ['USER 1', 'USER 2', 'USER 3', 'USER 4'],
  },

  expiryDates: ['Never Expire', 'End of Today', 'In 7 Days', 'In 14 Days', 'In 30 Days'],

  orientation: [
    { label: 'Portrait', value: 'portrait' },
    { label: 'Landscape', value: 'landscape' },
  ],

  inherit: [
    { label: 'Off', value: 'off' },
    { label: 'On', value: 'on' },
    { label: 'Inherit', value: 'inherit' },
  ],
};

export const ACCEPTED_MIME_TYPES = {
  // Audio
  'audio/mpeg': ['.mp3'],
  'audio/wav': ['.wav'],
  // Flash
  'application/x-shockwave-flash': ['.swf'],
  // Generic
  'application/vnd.android.package-archive': ['.apk'],
  'application/x-webos-ipk': ['.ipk'],
  'text/html': ['.html', '.htm'],
  'text/javascript': ['.js'],
  // HTML package
  'application/octet-stream': ['.htz'],
  // Images
  'image/jpeg': ['.jpg', '.jpeg'],
  'image/png': ['.png'],
  'image/gif': ['.gif'],
  'image/bmp': ['.bmp'],
  // PDF
  'application/pdf': ['.pdf'],
  // Powerpoint
  'application/vnd.ms-powerpoint': ['.ppt', '.pps'],
  'application/vnd.openxmlformats-officedocument.presentationml.presentation': ['.pptx'],
  // Video
  'video/mp4': ['.mp4'],
  'video/webm': ['.webm'],
  'video/mpeg': ['.mpg', '.mpeg'],
  'video/x-msvideo': ['.avi'],
  'video/x-ms-wmv': ['.wmv'],
};

export const FILTER_DROPWN_OPTIONS = [
  { label: 'All', value: 'all' },
  { label: 'User', value: 'user' },
  { label: 'Group', value: 'group' },
];

const formatDuration = (seconds: number) => {
  if (!seconds) return '-';
  return new Date(seconds * 1000).toISOString().slice(11, 19);
};

const getStatusTypeFromMediaType = (mediaType: string) => {
  const type = mediaType?.toLowerCase();
  switch (type) {
    case 'image':
    case 'video':
    case 'audio':
      return 'info';
    case 'pdf':
    case 'powerpoint':
      return 'danger';
    case 'flash':
    case 'htmlpackage':
      return 'success';
    default:
      return 'neutral';
  }
};

export interface MediaActionsProps {
  t: TFunction;
  onPreview?: (row: Media) => void;
  onDelete: (id: number) => void;
  onDownload: (row: Media) => void;
  openEditModal: (row: Media) => void;
  openShareModal?: () => void;
}

export const getMediaItemActions = ({
  t,
  onDelete,
  onDownload,
  openEditModal,
  openShareModal,
}: MediaActionsProps): ((media: Media) => ActionItem[]) => {
  return (media: Media) => [
    // Quick Actions
    {
      label: t('Edit'),
      icon: Edit,
      onClick: () => openEditModal(media),
      isQuickAction: true,
      variant: 'primary' as const,
    },
    {
      label: t('Download'),
      icon: Download,
      onClick: () => onDownload(media),
      isQuickAction: true,
    },

    // Dropdown Menu Actions
    {
      label: t('Edit'),
      icon: Edit,
      onClick: () => openEditModal(media),
    },
    {
      label: t('Make a Copy'),
      icon: CopyCheck,
      onClick: () => console.log('Make a Copy', media.mediaId),
    },
    {
      label: t('Move'),
      icon: FolderInput,
      onClick: () => console.log('Move', media.mediaId),
    },
    {
      label: t('Share'),
      icon: UserPlus2,
      onClick: () => openShareModal && openShareModal(),
    },
    {
      label: t('Download'),
      icon: Download,
      onClick: () => onDownload(media),
    },
    {
      label: t('Schedule'),
      icon: CalendarClock,
      onClick: () => console.log('Schedule', media.mediaId),
    },
    {
      label: t('Details'),
      icon: Info,
      onClick: () => console.log('Details', media.mediaId),
    },
    { isSeparator: true },
    {
      label: t('Enable Stats Collection'),
      onClick: () => console.log('Enable Stats', media.mediaId),
    },
    {
      label: t('Usage Report'),
      onClick: () => console.log('Usage Report', media.mediaId),
    },
    { isSeparator: true },
    {
      label: t('Delete'),
      icon: Trash2,
      onClick: () => onDelete(media.mediaId),
      variant: 'danger' as const,
    },
  ];
};

export const getMediaColumns = (props: MediaActionsProps): ColumnDef<Media>[] => {
  const { t, onPreview } = props;
  const getActions = getMediaItemActions(props);
  return [
    {
      accessorKey: 'mediaId',
      header: t('ID'),
      size: 80,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },
    {
      accessorKey: 'thumbnail',
      header: t('Thumbnail'),
      size: 150,
      enableSorting: false,
      cell: (info) => (
        <MediaCell
          thumb={info.row.original.thumbnail}
          alt={info.row.original.name}
          mediaType={(info.row.original.mediaType as MediaType) || 'other'}
          onPreview={() => onPreview?.(info.row.original)}
        />
      ),
    },
    {
      accessorKey: 'name',
      header: t('Name'),
      size: 240,
      enableHiding: false,
      cell: (info) => <TextCell weight="bold">{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'mediaType',
      header: t('Type'),
      size: 100,
      cell: (info) => {
        const value = info.getValue() as string;
        return <StatusCell label={value} type={getStatusTypeFromMediaType(value)} />;
      },
    },
    {
      accessorKey: 'tags',
      header: t('Tags'),
      enableSorting: false,
      size: 150,
      cell: (info) => {
        const tags = info.getValue<ApiTag[]>() || [];
        const formattedTags = tags.map((tag) => ({
          id: tag.tagId,
          label: tag.tag,
        }));
        return <TagsCell tags={formattedTags} />;
      },
    },
    {
      id: 'formattedDuration',
      accessorKey: 'duration',
      header: t('Duration'),
      size: 140,
      cell: (info) => <TextCell>{formatDuration(info.getValue<number>())}</TextCell>,
    },
    {
      id: 'durationSeconds',
      accessorKey: 'duration',
      header: t('Duration (s)'),
      size: 140,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },
    {
      accessorKey: 'fileSizeFormatted',
      header: t('Size'),
      size: 100,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'fileSize',
      header: t('Size (bytes)'),
      size: 150,
      cell: (info) => (
        <TextCell className="font-mono text-sm">
          {info.getValue<number>().toLocaleString()}
        </TextCell>
      ),
    },
    {
      id: 'resolution',
      header: t('Resolution'),
      size: 150,
      accessorFn: (row) => {
        if (row.width && row.height) return `${row.width}x${row.height}`;
        return '';
      },
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'ownerId',
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
      accessorKey: 'revised',
      header: t('Revised'),
      size: 120,
      cell: (info) => <CheckMarkCell active={(info.getValue<number>() === 1) as boolean} />,
    },
    {
      accessorKey: 'released',
      header: t('Released'),
      size: 120,
      cell: (info) => <CheckMarkCell active={(info.getValue<number>() === 1) as boolean} />,
    },
    {
      accessorKey: 'fileName',
      header: t('File Name'),
      size: 200,
      cell: (info) => (
        <TextCell className="truncate" title={info.getValue() as string}>
          {info.getValue<string>()}
        </TextCell>
      ),
    },
    {
      accessorKey: 'enableStat',
      header: t('Stats?'),
      size: 100,
      cell: (info) => <StatusCell label={info.getValue() as string} type="neutral" />,
    },
    {
      accessorKey: 'createdDt',
      header: t('Created'),
      size: 180,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'modifiedDt',
      header: t('Modified'),
      size: 180,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'expires',
      header: t('Expires'),
      size: 180,
      cell: (info) => {
        const val = info.getValue() as number;
        if (val === 0) return <span className="text-gray-400">-</span>;
        return <TextCell>{val}</TextCell>;
      },
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
  onDelete: (selectedItems: Media[]) => void;
  onMove: (selectedItems: Media[]) => void;
  onShare: (selectedItems: Media[]) => void;
}

export const getBulkActions = ({
  t,
  onDelete,
  onMove,
  onShare,
}: GetBulkActionsProps): DataTableBulkAction<Media>[] => {
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
      label: t('Download'),
      icon: Download,
      onClick: async (selectedItems) => {
        console.log('Download', selectedItems);
      },
    },
    {
      label: t('Delete Selected'),
      icon: Trash2,
      onClick: onDelete,
    },
    {
      label: t('More'),
      icon: MoreVertical,
      onClick: async (selectedItems) => {
        console.log('More?', selectedItems);
      },
    },
  ];
};
