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
import { Edit, CopyCheck, FolderInput, UserPlus2, CalendarClock, Trash2, Eye } from 'lucide-react';
import type { ComponentProps } from 'react';

import type { FilterConfigItem } from '@/components/ui/FilterInputs';
import type { DataTableBulkAction } from '@/components/ui/table/DataTableBulkActions';
import { TextCell, TagsCell, StatusCell, ActionsCell } from '@/components/ui/table/cells';
import type { Campaign } from '@/types/campaign';
import type { ActionItem, BaseModalType } from '@/types/table';
import type { Tag } from '@/types/tag';

export interface CampaignFilterInput {
  tags?: Tag[];
  hasLayouts?: string;
  layoutId?: string;
  type?: string;
  cyclePlaybackEnabled?: string;
}

export const CAMPAIGN_INITIAL_FILTER_STATE: CampaignFilterInput = {
  tags: [],
  hasLayouts: '',
  layoutId: '',
  type: '',
  cyclePlaybackEnabled: '',
};

export const getCampaignFilterKeys = (t: TFunction): FilterConfigItem<CampaignFilterInput>[] => [
  {
    label: t('Tags'),
    name: 'tags',
    type: 'tags',
    placeholder: t('Add tags'),
    className: 'md:w-auto md:flex-1 min-w-0',
  },

  {
    label: t('Layout'),
    name: 'hasLayouts',
    className: '',
    shouldTranslateOptions: false,
    showAllOption: false,
    options: [
      { label: t('All'), value: '' },
      { label: t('Yes'), value: '1' },
      { label: t('No'), value: '0' },
    ],
  },

  {
    label: t('Layout ID'),
    name: 'layoutId',
    type: 'number',
    placeholder: t('Enter layout ID'),
    className: '',
  },

  {
    label: t('Type'),
    name: 'type',
    className: '',
    shouldTranslateOptions: false,
    showAllOption: false,
    options: [
      { label: t('All'), value: '' },
      { label: t('Layout List'), value: 'list' },
      { label: t('Ad Campaign'), value: 'ad' },
    ],
  },

  {
    label: t('Cycle Based'),
    name: 'cyclePlaybackEnabled',
    className: '',
    shouldTranslateOptions: false,
    showAllOption: false,
    options: [
      { label: t('All'), value: '' },
      { label: t('Enabled'), value: '1' },
      { label: t('Disabled'), value: '0' },
    ],
  },
];

export type ModalType = BaseModalType | null;

interface CampaignActionsProps {
  t: TFunction;
  onDelete?: (id: number) => void;
  openEditModal?: (campaign: Campaign) => void;
  openShareModal?: (id: number) => void;
  openMoveModal?: (row: Campaign | Campaign[]) => void;
  openCopyModal?: (campaign: Campaign) => void;
}

export const getCampaignColumn = (props: CampaignActionsProps): ColumnDef<Campaign>[] => {
  const { t } = props;
  const getActions = getCampaignItemActions(props);

  return [
    {
      accessorKey: 'campaign',
      header: t('Name'),
      size: 220,
      enableHiding: false,
      cell: (info) => (
        <TextCell weight="bold" truncate>
          {info.getValue<string>()}
        </TextCell>
      ),
      enableSorting: true,
    },

    {
      accessorKey: 'type',
      header: t('Type'),
      size: 120,
      enableSorting: true,
      cell: (info) => {
        const value = info.getValue<string>();
        return <TextCell>{value === 'ad' ? t('Ad Campaign') : t('Layout List')}</TextCell>;
      },
    },

    {
      accessorKey: 'startDt',
      header: t('Start Date'),
      size: 160,
      enableSorting: true,
      cell: (info) => {
        const value = info.getValue<number>();
        return <TextCell>{value ? new Date(value * 1000).toLocaleString() : '-'}</TextCell>;
      },
    },

    {
      accessorKey: 'endDt',
      header: t('End Date'),
      size: 160,
      enableSorting: true,
      cell: (info) => {
        const value = info.getValue<number>();
        return <TextCell>{value ? new Date(value * 1000).toLocaleString() : '-'}</TextCell>;
      },
    },

    {
      accessorKey: 'numberLayouts',
      header: t('# Layouts'),
      size: 100,
      enableSorting: true,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },

    {
      accessorKey: 'tags',
      header: t('Tags'),
      size: 150,
      enableSorting: false,
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
      accessorKey: 'totalDuration',
      header: t('Duration'),
      size: 120,
      enableSorting: true,
      cell: (info) => <TextCell>{info.getValue<number>()}s</TextCell>,
    },

    {
      accessorKey: 'cyclePlaybackEnabled',
      header: t('Cycle based Playback'),
      size: 180,
      enableSorting: true,
      cell: (info) => {
        const value = info.getValue<number>();
        return (
          <StatusCell
            label={value ? t('Enabled') : t('Disabled')}
            type={value ? 'success' : 'neutral'}
          />
        );
      },
    },

    {
      accessorKey: 'playCount',
      header: t('Play Count'),
      size: 120,
      enableSorting: true,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },

    {
      accessorKey: 'targetType',
      header: t('Target Type'),
      size: 140,
      enableSorting: true,
      cell: (info) => <TextCell>{info.getValue<string>() || '-'}</TextCell>,
    },

    {
      accessorKey: 'target',
      header: t('Target'),
      size: 120,
      enableSorting: true,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },

    {
      accessorKey: 'plays',
      header: t('Plays'),
      size: 100,
      enableSorting: true,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },

    {
      accessorKey: 'spend',
      header: t('Spend'),
      size: 120,
      enableSorting: true,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },

    {
      accessorKey: 'impressions',
      header: t('Impressions'),
      size: 140,
      enableSorting: true,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },

    {
      accessorKey: 'ref1',
      header: t('Reference 1'),
      size: 140,
      enableSorting: false,
      cell: (info) => <TextCell>{info.getValue<string | null>() || '-'}</TextCell>,
    },

    {
      accessorKey: 'ref2',
      header: t('Reference 2'),
      size: 140,
      enableSorting: false,
      cell: (info) => <TextCell>{info.getValue<string | null>() || '-'}</TextCell>,
    },

    {
      accessorKey: 'ref3',
      header: t('Reference 3'),
      size: 140,
      enableSorting: false,
      cell: (info) => <TextCell>{info.getValue<string | null>() || '-'}</TextCell>,
    },

    {
      accessorKey: 'ref4',
      header: t('Reference 4'),
      size: 140,
      enableSorting: false,
      cell: (info) => <TextCell>{info.getValue<string | null>() || '-'}</TextCell>,
    },

    {
      accessorKey: 'ref5',
      header: t('Reference 5'),
      size: 140,
      enableSorting: false,
      cell: (info) => <TextCell>{info.getValue<string | null>() || '-'}</TextCell>,
    },

    {
      accessorKey: 'createdAt',
      header: t('Created At'),
      size: 160,
      enableSorting: true,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },

    {
      accessorKey: 'modifiedAt',
      header: t('Modified At'),
      size: 160,
      enableSorting: true,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },

    {
      accessorKey: 'modifiedByName',
      header: t('Modified By'),
      size: 160,
      enableSorting: true,
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

export const getCampaignItemActions = ({
  t,
  onDelete,
  openEditModal,
  openShareModal,
  openMoveModal,
  openCopyModal,
}: CampaignActionsProps): ((campaign: Campaign) => ActionItem[]) => {
  return (campaign: Campaign) => [
    ...(campaign.type !== 'ad'
      ? [
          {
            label: t('Edit'),
            icon: Edit,
            onClick: () => openEditModal && openEditModal(campaign),
            isQuickAction: true,
            variant: 'primary' as const,
          },
        ]
      : []),
    {
      label: t('Schedule'),
      icon: CalendarClock,
      onClick: () => {},
    },
    {
      label: t('Preview Campaign'),
      icon: Eye,
      onClick: () => {},
    },
    { isSeparator: true },
    ...(campaign.type !== 'ad'
      ? [
          {
            label: t('Edit'),
            icon: Edit,
            onClick: () => openEditModal && openEditModal(campaign),
          },
        ]
      : []),
    {
      label: t('Make a Copy'),
      icon: CopyCheck,
      onClick: () => openCopyModal && openCopyModal(campaign),
    },
    {
      label: t('Move'),
      icon: FolderInput,
      onClick: () => openMoveModal && openMoveModal(campaign),
    },
    {
      label: t('Share'),
      icon: UserPlus2,
      onClick: () => openShareModal && openShareModal(campaign.campaignId),
    },

    { isSeparator: true },
    {
      label: t('Delete'),
      icon: Trash2,
      onClick: () => onDelete && onDelete(campaign.campaignId),
      variant: 'danger' as const,
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
}: GetBulkActionsProps): DataTableBulkAction<Campaign>[] => {
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
