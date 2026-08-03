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
  Trash2,
  Tags,
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
  getSharingColumn,
} from '@/components/ui/table/cells';
import type { ActionItem, BaseModalType } from '@/types/table';
import type { Tag } from '@/types/tag';
import type { Template } from '@/types/templates';
import type { DateLike } from '@/utils/date';
import { formatTagsForExport } from '@/utils/tags';

export interface TemplatesFilterInput {
  template?: string;
  tags?: Tag[];
  logicalOperatorName?: 'OR' | 'AND';
  useRegexForName?: boolean;
  logicalOperator?: 'OR' | 'AND';
  exactTags?: boolean;
}

export const TEMPLATE_INITIAL_FILTER_STATE: TemplatesFilterInput = {
  template: '',
  tags: [],
  logicalOperatorName: 'OR',
  useRegexForName: false,
  logicalOperator: 'OR',
  exactTags: false,
};

export type ModalType =
  | BaseModalType
  | 'publish'
  | 'discard'
  | 'export'
  | 'editTagsMultiple'
  | null;

export const getBaseFilterKeys = (
  t: TFunction,
  canTag = false,
): FilterConfigItem<TemplatesFilterInput>[] => [
  {
    label: t('Name'),
    name: 'template',
    type: 'text',
    className: '',
    placeholder: ' ',
    showAndOr: true,
    andOrKey: 'logicalOperatorName',
    showRegex: true,
    regexKey: 'useRegexForName',
  },
  ...(canTag
    ? ([
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
      ] as FilterConfigItem<TemplatesFilterInput>[])
    : []),
];

export interface TemplatesActionsProps {
  t: TFunction;
  canModify?: boolean;
  canMoveToCampaignFolder?: boolean;
  canUserShare?: boolean;
  canExport?: boolean;
  canViewFolders?: boolean;
  canTag?: boolean;
  formatDateTime: (value: DateLike) => string;
  onPreview?: (row: Template) => void;
  onDelete: (id: number) => void;
  openAddEditModal: (row: Template) => void;
  openShareModal?: (id: number) => void;
  openMoveModal?: (row: Template | Template[]) => void;
  openCopyModal?: (id: number) => void;
  openPublishModal?: (id: number) => void;
  alterTemplate?: (id: number) => void;
  discardTemplate?: (id: number) => void;
  exportTemplate?: (id: number) => void;
  openDetails?: (id: number) => void;
  openTemplate?: (id: number) => void;
}

export const getTemplateColumn = (props: TemplatesActionsProps): ColumnDef<Template>[] => {
  const { t, formatDateTime, canTag = false } = props;
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
      meta: {
        excludeFromExport: true,
      },
      cell: (info) => {
        const row = info.row.original;

        return (
          <MediaCell
            thumb={row?.thumbnail || undefined}
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

    ...(canTag
      ? ([
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
            meta: {
              getExportValue: (row) => formatTagsForExport(row.tags),
            },
          },
        ] as ColumnDef<Template>[])
      : []),

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

    getSharingColumn<Template>(t),

    {
      accessorKey: 'modifiedDt',
      header: t('Modified'),
      size: 160,
      cell: (info) => <TextCell>{formatDateTime(info.getValue<string>())}</TextCell>,
      enableSorting: true,
      meta: {
        getExportValue: (row) => formatDateTime(row.modifiedDt),
      },
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
      size: 80,
      minSize: 80,
      maxSize: 80,
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
  onMove?: () => void;
  onShare: () => void;
  onEditTags?: () => void;
}

export const getBulkActions = ({
  t,
  onDelete,
  onMove,
  onShare,
  onEditTags,
}: GetBulkActionsProps): DataTableBulkAction<Template>[] => {
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
    ...(onEditTags
      ? [
          {
            label: t('Edit Tags'),
            icon: Tags,
            onClick: onEditTags,
          },
        ]
      : []),
    {
      label: t('Delete Selected'),
      icon: Trash2,
      onClick: onDelete,
    },
  ];
};

export const getTemplateItemActions = ({
  t,
  canModify = false,
  canMoveToCampaignFolder = false,
  canUserShare = false,
  canExport = false,
  canViewFolders = false,
  onDelete,
  openAddEditModal,
  openShareModal,
  openMoveModal,
  openCopyModal,
  openPublishModal,
  alterTemplate,
  discardTemplate,
  exportTemplate,
}: TemplatesActionsProps): ((template: Template) => ActionItem[]) => {
  return (template: Template) => {
    const canEdit = !!template.userPermissions?.edit;
    const canDelete = !!template.userPermissions?.delete;
    const canShare = !!template.userPermissions?.modifyPermissions;

    const actions: ActionItem[] = [];

    const addSeparator = () => {
      if (actions.length > 0 && !actions[actions.length - 1]?.isSeparator) {
        actions.push({ isSeparator: true });
      }
    };

    if (canModify && canEdit) {
      actions.push({
        label: t('Edit'),
        icon: Edit,
        onClick: () => openAddEditModal(template),
        isQuickAction: true,
        variant: 'primary' as const,
      });
      actions.push({
        label: t('Alter Template'),
        icon: PenTool,
        onClick: () => alterTemplate && alterTemplate(template.layoutId),
      });
    }

    if (canModify && canEdit && template.publishedStatus !== 'Published') {
      actions.push({
        label: t('Publish'),
        icon: CloudUpload,
        onClick: () => openPublishModal && openPublishModal(template.layoutId),
      });
      actions.push({
        label: t('Discard'),
        onClick: () => discardTemplate && discardTemplate(template.layoutId),
      });
    }

    if (canModify && canEdit) {
      addSeparator();
      actions.push({
        label: t('Edit'),
        icon: Edit,
        onClick: () => openAddEditModal(template),
      });
      actions.push({
        label: t('Make a Copy'),
        icon: CopyCheck,
        onClick: () => openCopyModal && openCopyModal(template.layoutId),
      });
    }

    if (canMoveToCampaignFolder && canEdit && canViewFolders) {
      addSeparator();
      actions.push({
        label: t('Move'),
        icon: FolderInput,
        onClick: () => openMoveModal && openMoveModal(template),
      });
    }

    if (canModify && canShare && canUserShare) {
      actions.push({
        label: t('Share'),
        icon: UserPlus2,
        onClick: () => openShareModal && openShareModal(template.campaignId),
      });
    }

    if (canExport) {
      addSeparator();
      actions.push({
        label: t('Export'),
        onClick: () => exportTemplate && exportTemplate(template.layoutId),
      });
    }

    if (canModify && canDelete) {
      addSeparator();
      actions.push({
        label: t('Delete'),
        icon: Trash2,
        onClick: () => onDelete(template.layoutId),
        variant: 'danger' as const,
      });
    }

    return actions;
  };
};
