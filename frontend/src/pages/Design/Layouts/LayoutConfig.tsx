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
  PaletteIcon,
  CloudUpload,
  DownloadCloud,
  Redo2,
  FileCheck,
  Download,
  FileX,
  ArrowRight,
  Info,
} from 'lucide-react';
import type { ComponentProps } from 'react';

import type { FilterConfigItem } from '@/components/ui/FilterInputs';
import type { DataTableBulkAction } from '@/components/ui/table/DataTableBulkActions';
import {
  TextCell,
  StatusCell,
  CheckMarkCell,
  ActionsCell,
  MediaCell,
  TagsCell,
} from '@/components/ui/table/cells';
import { getCommonFormOptions } from '@/config/commonForms';
import type { Layout } from '@/types/layout';
import type { ActionItem } from '@/types/table';
import type { Tag } from '@/types/tag';
import { formatDuration } from '@/utils/formatters';

export interface LayoutFilterInput {
  ownerId?: string;
  ownerUserGroupId?: string;
  retired?: string;
  orientation?: string;
  lastModified?: string;
}

export const LAYOUT_INITIAL_FILTER_STATE: LayoutFilterInput = {
  ownerId: '',
  ownerUserGroupId: '',
  retired: '',
  orientation: '',
  lastModified: '',
};
export const getBaseFilterKeys = (t: TFunction): FilterConfigItem<LayoutFilterInput>[] => [
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
    className: '',

    shouldTranslateOptions: false,
    showAllOption: false,
    options: [{ label: 'Select Group', value: null }],
  },
  {
    label: 'Retired',
    name: 'retired',
    className: '',
    shouldTranslateOptions: false,
    showAllOption: false,
    options: getCommonFormOptions(t).retired,
  },
  {
    label: 'Orientation',
    name: 'orientation',
    className: '',
    shouldTranslateOptions: false,
    showAllOption: false,
    options: getCommonFormOptions(t).orientation,
  },
  {
    label: 'Last Modified',
    name: 'lastModified',
    className: '',
    shouldTranslateOptions: true,
    showAllOption: false,
    options: getCommonFormOptions(t).lastModifiedFilter,
  },
];

export interface LayoutActionsProps {
  t: TFunction;
  onPreview?: (row: Layout) => void;
  onDelete: (id: number) => void;
  openEditModal: (row: Layout) => void;
  openShareModal?: (id: number) => void;
  openMoveModal?: (row: Layout | Layout[]) => void;
  copyLayout?: (row: number) => void;
  openDetails?: (id: number) => void;
  openLayout?: (id: number) => void;
  openPublish?: (id: number) => void;
  checkoutLayout?: (id: number) => void;
  discardLayout?: (id: number) => void;
  assignModal?: (layout: Layout) => void;
  jumpToPlaylists?: (layoutId: number) => void;
  exportLayout?: (row: Layout) => void;
  openTemplateModal?: (layoutId: number) => void;
  jumpToCampaigns?: (layoutId: number) => void;
  jumpToMedia?: (layoutId: number) => void;
  openRetireModal?: (layout: Layout) => void;
  openEnableStatsModal?: (layout: Layout) => void;
}

export const getLayoutItemActions = ({
  t,
  onDelete,
  openEditModal,
  openShareModal,
  openMoveModal,
  copyLayout,
  openDetails,
  openLayout,
  openPublish,
  checkoutLayout,
  discardLayout,
  assignModal,
  jumpToPlaylists,
  jumpToCampaigns,
  jumpToMedia,
  exportLayout,
  openTemplateModal,
  openRetireModal,
  openEnableStatsModal,
}: LayoutActionsProps): ((layout: Layout) => ActionItem[]) => {
  return (layout: Layout) => {
    const actions: ActionItem[] = [];

    // TODO: Set all true for now. Add userPermission to layout data
    const canEdit = layout.userPermissions?.edit ?? 1;
    const canDelete = layout.userPermissions?.delete ?? 1;
    const canShare = layout.userPermissions?.modifyPermissions ?? 1;

    actions.push({
      label: t('Design'),
      icon: PaletteIcon,
      isQuickAction: true,
      variant: 'primary',

      onClick: () => openLayout && openLayout(layout.layoutId),
    });

    if (canEdit) {
      actions.push({
        label: t('Edit'),
        icon: Edit,
        onClick: () => openEditModal(layout),
        isQuickAction: true,
      });
    }

    actions.push({
      label: t('Design'),
      icon: PaletteIcon,
      onClick: () => openLayout && openLayout(layout.layoutId),
    });

    if (layout.publishedStatus !== 'Published') {
      actions.push({
        label: t('Publish'),
        icon: CloudUpload,
        onClick: () => openPublish && openPublish(layout.layoutId),
      });
      actions.push({
        label: t('Discard'),
        icon: Redo2,
        onClick: () => discardLayout && discardLayout(layout.layoutId),
      });
    } else {
      actions.push({
        label: t('Checkout'),
        icon: DownloadCloud,
        onClick: () => checkoutLayout && checkoutLayout(layout.layoutId),
      });
      actions.push({
        label: t('Save as Template'),
        icon: FileCheck,
        onClick: () => openTemplateModal && openTemplateModal(layout.layoutId),
      });
    }

    actions.push({ isSeparator: true });

    if (canEdit) {
      actions.push({
        label: t('Edit'),
        icon: Edit,
        onClick: () => openEditModal(layout),
      });
    }

    if (canEdit && openMoveModal) {
      actions.push({
        label: t('Move'),
        icon: FolderInput,
        onClick: () => openMoveModal(layout),
      });
    }

    if (canEdit && copyLayout) {
      actions.push({
        label: t('Make a Copy'),
        icon: CopyCheck,
        onClick: () => copyLayout(layout.layoutId),
      });
    }

    if (canShare && openShareModal && layout.campaignId) {
      actions.push({
        label: t('Share'),
        icon: UserPlus2,
        onClick: () => openShareModal(layout.campaignId),
      });
    }

    actions.push({
      label: t('Export'),
      icon: Download,
      onClick: () => exportLayout && exportLayout(layout),
    });

    actions.push({
      label: t('Schedule'),
      icon: CalendarClock,
      onClick: () => console.log('Schedule', layout.layoutId),
    });

    actions.push({
      label: t('Retire'),
      icon: FileX,
      onClick: () => openRetireModal && openRetireModal(layout),
    });
    actions.push({
      label: t('Details'),
      icon: Info,
      onClick: () => openDetails && openDetails(layout.layoutId),
    });

    actions.push({ isSeparator: true });

    actions.push({
      label: t('Jump to Playlists'),
      onClick: () => jumpToPlaylists && jumpToPlaylists(layout.layoutId),
      rightIcon: ArrowRight,
    });
    actions.push({
      label: t('Jump to Campaigns'),
      onClick: () => jumpToCampaigns && jumpToCampaigns(layout.layoutId),
      rightIcon: ArrowRight,
    });
    actions.push({
      label: t('Jump to Media'),
      onClick: () => jumpToMedia && jumpToMedia(layout.layoutId),
      rightIcon: ArrowRight,
    });

    actions.push({
      label: t('Assign to Campaign'),
      onClick: () => assignModal && assignModal(layout),
    });

    actions.push({
      label: t('Enable Stats Collection'),
      onClick: () => openEnableStatsModal && openEnableStatsModal(layout),
    });

    if (canDelete) {
      actions.push({ isSeparator: true });

      actions.push({
        label: t('Delete'),
        icon: Trash2,
        onClick: () => onDelete(layout.layoutId),
        variant: 'danger',
      });
    }

    return actions;
  };
};

export const getLayoutColumns = (props: LayoutActionsProps): ColumnDef<Layout>[] => {
  const { t } = props;
  const getActions = getLayoutItemActions(props);

  return [
    {
      accessorKey: 'campaignId',
      header: t('ID'),
      size: 80,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
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
            alt={row?.name || row?.layout}
            mediaType="image"
            onPreview={() => props.onPreview && props.onPreview(row)}
          />
        );
      },
    },
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
    },
    {
      accessorKey: 'duration',
      header: t('Duration'),
      size: 140,
      cell: (info) => {
        const value = info.getValue<number>();
        return <TextCell>{formatDuration(value)}</TextCell>;
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
      header: t('Sharing'),
      enableSorting: false,
      size: 80,
      cell: (info) => {
        const groups = info.getValue<string>();
        return <TextCell className="italic text-gray-500">{groups || t('Private')}</TextCell>;
      },
    },

    {
      accessorKey: 'valid',
      header: t('Valid?'),
      size: 100,
      cell: (info) => <CheckMarkCell active={info.getValue<number>() === 1} />,
    },

    {
      accessorKey: 'status',
      header: t('Stats?'),
      size: 100,
      cell: (info) => <CheckMarkCell active={info.getValue<number>() === 1} />,
    },

    {
      accessorKey: 'modifiedDt',
      header: t('Modified'),
      size: 160,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'layoutId',
      header: t('Layout ID'),
      size: 100,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },
    {
      accessorKey: 'code',
      header: t('Code'),
      size: 100,
      cell: (info) => <TextCell>{info.getValue<number>() || '-'}</TextCell>,
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
}: GetBulkActionsProps): DataTableBulkAction<Layout>[] => {
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
