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

import type { FilterConfigItem } from '@/components/ui/FilterInputs';
import type { ModalAction } from '@/components/ui/modals/Modal';
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
import type { MediaRow } from '@/types/media';

export const LIBRARY_TABS = APP_ROUTES.find((r) => r.path === 'library')?.subLinks || [];

export interface MediaFilterInput {
  type: string;
  owner: string;
  userGroup: string;
  orientation: string;
  retired: string;
  lastModified: string;
}

interface ApiTag {
  tagId: number;
  tag: string;
}

type MediaType = 'image' | 'video' | 'audio' | 'pdf' | 'archive' | 'other';

export const INITIAL_FILTER_STATE: MediaFilterInput = {
  type: '',
  owner: '',
  userGroup: '',
  orientation: '',
  retired: '',
  lastModified: '',
};

export const FILTER_OPTIONS: FilterConfigItem<MediaFilterInput>[] = [
  {
    label: 'Type',
    name: 'type',
    className: '',
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
    name: 'owner',
    className: '',
    options: [
      { label: 'Owner 1', value: 'owner-1' },
      { label: 'Owner 2', value: 'owner-2' },
      { label: 'Owner 3', value: 'owner-3' },
      { label: 'Owner 4', value: 'owner-4' },
    ],
  },
  {
    label: 'User Group',
    name: 'userGroup',
    className: 'md:w-auto w-[100%]',
    options: [
      { label: 'Group 1', value: 'group-1' },
      { label: 'Group 2', value: 'group-2' },
      { label: 'Group 3', value: 'group-3' },
      { label: 'Group 4', value: 'group-4' },
    ],
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
    className: 'max-w-[100px]',
    options: [
      { label: 'Any', value: '' },
      { label: 'Yes', value: 'yes' },
      { label: 'No', value: 'no' },
    ],
  },
  {
    label: 'Last Modified',
    name: 'lastModified',
    className: '',
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

interface GetMediaColumnsProps {
  t: TFunction;
  onPreview: (row: MediaRow) => void;
  onDelete: (id: number) => void;
  onDownload: (row: MediaRow) => void;
  openEditModal: (row: MediaRow) => void;
}

export const getMediaColumns = ({
  t,
  onPreview,
  onDelete,
  onDownload,
  openEditModal,
}: GetMediaColumnsProps): ColumnDef<MediaRow>[] => {
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
          onPreview={() => onPreview(info.row.original)}
        />
      ),
    },
    {
      accessorKey: 'name',
      header: t('Name'),
      size: 250,
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
      size: 200,
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
          actions={[
            // Quick Actions
            {
              label: t('Edit'),
              icon: Edit,
              onClick: () => openEditModal(row.original),
              isQuickAction: true,
              variant: 'primary',
            },
            {
              label: t('Download'),
              icon: Download,
              onClick: () => onDownload(row.original),
              isQuickAction: true,
            },

            // Dropdown Menu Actions
            {
              label: t('Edit'),
              icon: Edit,
              onClick: () => openEditModal(row.original),
            },
            {
              label: t('Make a Copy'),
              icon: CopyCheck,
              onClick: () => console.log('Make a Copy', row.original.mediaId),
            },
            {
              label: t('Move'),
              icon: FolderInput,
              onClick: () => console.log('Move', row.original.mediaId),
            },
            {
              label: t('Share'),
              icon: UserPlus2,
              onClick: () => console.log('Share', row.original.mediaId),
            },
            {
              label: t('Download'),
              icon: Download,
              onClick: () => onDownload(row.original),
            },
            {
              label: t('Schedule'),
              icon: CalendarClock,
              onClick: () => console.log('Schedule', row.original.mediaId),
            },
            {
              label: t('Details'),
              icon: Info,
              onClick: () => console.log('Details', row.original.mediaId),
            },
            { isSeparator: true },
            {
              label: t('Enable Stats Collection'),
              onClick: () => console.log('Enable Stats', row.original.mediaId),
            },
            {
              label: t('Usage Report'),
              onClick: () => console.log('Usage Report', row.original.mediaId),
            },
            { isSeparator: true },
            {
              label: t('Delete'),
              icon: Trash2,
              onClick: () => onDelete(row.original.mediaId),
              variant: 'danger',
            },
          ]}
        />
      ),
    },
  ];
};

interface GetBulkActionsProps {
  t: TFunction;
  onDelete: (selectedItems: MediaRow[]) => void;
  onMove: (selectedItems: MediaRow[]) => void;
  onShare: (selectedItems: MediaRow[]) => void;
}

export const getBulkActions = ({
  t,
  onDelete,
  onMove,
  onShare,
}: GetBulkActionsProps): DataTableBulkAction<MediaRow>[] => {
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

interface GetModalActionsProps {
  t: TFunction;
  onCancel: () => void;
  onUpload: () => void;
  isUploading: boolean;
  hasQueueItems: boolean;
}

export const getAddModalActions = ({
  t,
  onCancel,
  onUpload,
  isUploading,
  hasQueueItems,
}: GetModalActionsProps): ModalAction[] => [
  {
    label: t('Cancel'),
    onClick: onCancel,
    variant: 'secondary',
    disabled: isUploading,
  },
  {
    label: isUploading ? t('Uploading...') : t('Start Upload'),
    onClick: onUpload,
    variant: 'primary',
    disabled: isUploading || !hasQueueItems,
  },
];
