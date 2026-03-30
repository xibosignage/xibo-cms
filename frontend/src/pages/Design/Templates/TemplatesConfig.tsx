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
  CopyCheck,
  FolderInput,
  UserPlus2,
  CalendarClock,
  Trash2,
  CloudUpload,
  PenTool,
} from 'lucide-react';
import type { ComponentProps } from 'react';

import type { FilterConfigItem } from '@/components/ui/FilterInputs';
import type { DataTableBulkAction } from '@/components/ui/table/DataTableBulkActions';
import {
  TextCell,
  StatusCell,
  ActionsCell,
  MediaCell,
  TagsCell,
} from '@/components/ui/table/cells';
import type { ActionItem, BaseModalType } from '@/types/table';
import type { Tag } from '@/types/tag';
import type { Template } from '@/types/templates';

export interface TemplatesFilterInput {
  name?: string;
  tags?: Tag[];
}

export const TEMPLATE_INITIAL_FILTER_STATE: TemplatesFilterInput = {
  name: '',
  tags: [],
};

export type ModalType = BaseModalType | null;

export const getBaseFilterKeys = (t: TFunction): FilterConfigItem<Record<string, unknown>>[] => [
  {
    label: t('Tag'),
    name: 'tags',
    type: 'tags',
    className: 'max-w-auto md:max-w-80',
    shouldTranslateOptions: false,
    showAllOption: false,
  },
];

export interface TemplatesActionsProps {
  t: TFunction;
  onPreview?: (row: Template) => void;
  onDelete: (id: number) => void;
  openAddEditModal: (row: Template) => void;
  openShareModal?: (id: number) => void;
  openMoveModal?: (row: Template | Template[]) => void;
  openCopyModal?: (id: number) => void;
  alterTemplate?: (id: number) => void;
  openDetails?: (id: number) => void;
  openTemplate?: (id: number) => void;
}

export const getTemplateColumn = (props: TemplatesActionsProps): ColumnDef<Template>[] => {
  const { t } = props;
  const getActions = getTemplateItemActions(props);

  return [
    {
      accessorKey: 'layout',
      header: t('Name'),
      size: 200,
      enableHiding: false,
      cell: (info) => (
        <TextCell weight="bold" truncate>
          {info.getValue<string>()}
        </TextCell>
      ),
      enableSorting: true,
    },
    {
      accessorKey: 'thumbnail',
      header: t('Thumbnail'),
      size: 140,
      enableSorting: false,
      cell: (info) => {
        const row = info.row.original;

        return (
          <MediaCell
            thumb={row?.layoutId ? `/layout/thumbnail/${row.layoutId}` : undefined}
            alt={row?.layout}
            mediaType="image"
            onPreview={() => props.onPreview && props.onPreview(row)}
          />
        );
      },
    },

    {
      accessorKey: 'owner',
      header: t('Owner'),
      size: 150,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
      enableSorting: true,
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
      accessorKey: 'description',
      header: t('Description'),
      size: 200,
      cell: (info) => (
        <TextCell weight="normal" className="text-xs" truncate>
          {info.getValue<string>()}
        </TextCell>
      ),
      enableSorting: false,
    },

    {
      accessorKey: 'publishedStatus',
      header: t('Status'),
      size: 120,
      cell: (info) => {
        const status = info.getValue<string>();
        return <StatusCell label={status} type={status === 'Published' ? 'success' : 'neutral'} />;
      },
      enableSorting: true,
    },

    {
      accessorKey: 'groupsWithPermissions',
      header: t('Sharing'),
      enableSorting: false,
      size: 80,
      cell: (info) => {
        const groups = info.getValue<string>();
        return <TextCell className="italic text-gray-500">{groups || t('Private')}</TextCell>;
      },
    },

    {
      accessorKey: 'modifiedDt',
      header: t('Modified'),
      size: 160,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
      enableSorting: true,
    },

    {
      accessorKey: 'orientation',
      header: t('Orientation'),
      size: 160,
      cell: (info) => <TextCell className="capitalize">{info.getValue<string>()}</TextCell>,
    },

    {
      id: 'tableActions',
      header: '',
      size: 120,
      minSize: 120,
      maxSize: 120,
      enableHiding: false,
      enableResizing: false,
      cell: (info) => {
        const row = info.row.original;
        if (!row) return null;

        return (
          <ActionsCell
            row={info.row}
            actions={getActions(row) as ComponentProps<typeof ActionsCell>['actions']}
          />
        );
      },
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
}: GetBulkActionsProps): DataTableBulkAction<Template>[] => {
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

export const getTemplateItemActions = ({
  t,
  onDelete,
  openAddEditModal,
  openShareModal,
  openMoveModal,
  openCopyModal,
  alterTemplate,
}: TemplatesActionsProps): ((template: Template) => ActionItem[]) => {
  return (template: Template) => [
    {
      label: t('Edit'),
      icon: Edit,
      onClick: () => openAddEditModal(template),
      isQuickAction: true,
      variant: 'primary' as const,
    },

    {
      label: t('Alter Template'),
      icon: PenTool,
      onClick: () => alterTemplate && alterTemplate(template.layoutId),
    },
    {
      label: t('Publish'),
      icon: CloudUpload,
      onClick: () => console.log('Publish', template.layoutId),
    },
    { isSeparator: true },
    {
      label: t('Edit'),
      icon: Edit,
      onClick: () => openAddEditModal(template),
    },
    {
      label: t('Make a Copy'),
      icon: CopyCheck,
      onClick: () => openCopyModal && openCopyModal(template.layoutId),
    },
    {
      label: t('Move'),
      icon: FolderInput,
      onClick: () => openMoveModal && openMoveModal(template),
    },
    {
      label: t('Share'),
      icon: UserPlus2,
      onClick: () => openShareModal && openShareModal(template.campaignId),
    },
    {
      label: t('Schedule'),
      icon: CalendarClock,
      onClick: () => console.log('Schedule', template.layoutId),
    },
    { isSeparator: true },
    {
      label: t('Discard'),
      onClick: () => console.log('Discard', template.layoutId),
    },
    {
      label: t('Export'),
      onClick: () => console.log('Export', template.layoutId),
    },
    { isSeparator: true },
    {
      label: t('Delete'),
      icon: Trash2,
      onClick: () => onDelete(template.layoutId),
      variant: 'danger' as const,
    },
  ];
};
