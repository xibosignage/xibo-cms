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
  Calendar,
  Copy,
  Edit,
  Folder,
  Terminal,
  Trash2,
  UserPlus2,
  Users,
  Webhook,
} from 'lucide-react';
import { type ComponentProps } from 'react';

import type { FilterConfigItem } from '@/components/ui/FilterInputs';
import type { DataTableBulkAction } from '@/components/ui/table/DataTableBulkActions';
import { ActionsCell, CheckMarkCell, TagsCell, TextCell } from '@/components/ui/table/cells';
import type { DisplayGroup } from '@/types/displayGroup';
import type { ActionItem, BaseModalType } from '@/types/table';
import type { Tag } from '@/types/tag';

export type ModalType =
  | BaseModalType
  | 'add'
  | 'members'
  | 'schedule'
  | 'assignFiles'
  | 'assignLayouts'
  | 'sendCommand'
  | 'collectNow'
  | 'triggerWebhook'
  | 'bulkSendCommand'
  | 'bulkTriggerWebhook'
  | null;

export interface DisplayGroupFilterInput {
  displayId: number | null;
  displayIdDropdown: number | null;
  nestedDisplayId: number | null;
  dynamicCriteria: string;
  tags: Tag[];
}

export const INITIAL_FILTER_STATE: DisplayGroupFilterInput = {
  displayId: null,
  displayIdDropdown: null,
  nestedDisplayId: null,
  dynamicCriteria: '',
  tags: [],
};

export const getBaseFilterKeys = (t: TFunction): FilterConfigItem<DisplayGroupFilterInput>[] => [
  {
    label: t('Display ID'),
    name: 'displayId',
    type: 'number',
    placeholder: t('Enter display ID...'),
  },
  {
    label: t('Display'),
    name: 'displayIdDropdown',
    type: 'paged-select',
    placeholder: t('All'),
    options: [],
    shouldTranslateOptions: false,
  },
  {
    label: t('Nested Display'),
    name: 'nestedDisplayId',
    type: 'paged-select',
    placeholder: t('All'),
    options: [],
    shouldTranslateOptions: false,
  },
  {
    label: t('Dynamic Criteria'),
    name: 'dynamicCriteria',
    type: 'text',
    placeholder: t('Filter by criteria...'),
  },
  {
    label: t('Tags'),
    name: 'tags',
    type: 'tags',
    className: 'max-w-auto md:max-w-80',
    shouldTranslateOptions: false,
  },
];

export interface DisplayGroupActionsProps {
  t: TFunction;
  onDelete: (displayGroup: DisplayGroup) => void;
  openEditModal: (displayGroup: DisplayGroup) => void;
  openCopyModal: (displayGroup: DisplayGroup) => void;
  openMembersModal: (displayGroup: DisplayGroup) => void;
  openMoveModal?: (displayGroup: DisplayGroup) => void;
  openScheduleModal: (displayGroup: DisplayGroup) => void;
  openAssignFilesModal: (displayGroup: DisplayGroup) => void;
  openAssignLayoutsModal: (displayGroup: DisplayGroup) => void;
  openShareModal: (displayGroup: DisplayGroup) => void;
  openSendCommandModal: (displayGroup: DisplayGroup) => void;
  collectNow: (displayGroup: DisplayGroup) => void;
  triggerWebhook: (displayGroup: DisplayGroup) => void;
}

export const getDisplayGroupItemActions = ({
  t,
  onDelete,
  openEditModal,
  openCopyModal,
  openMembersModal,
  openMoveModal,
  openScheduleModal,
  openAssignFilesModal,
  openAssignLayoutsModal,
  openShareModal,
  openSendCommandModal,
  collectNow,
  triggerWebhook,
}: DisplayGroupActionsProps): ((displayGroup: DisplayGroup) => ActionItem[]) => {
  return (displayGroup: DisplayGroup) => [
    // Quick action
    {
      label: t('Edit'),
      icon: Edit,
      onClick: () => openEditModal(displayGroup),
      isQuickAction: true,
      variant: 'primary' as const,
    },

    // Dropdown
    ...(displayGroup.isDynamic === 0
      ? [
          {
            label: t('Members'),
            icon: Users,
            onClick: () => openMembersModal(displayGroup),
          },
          { isSeparator: true as const },
        ]
      : []),
    {
      label: t('Edit'),
      icon: Edit,
      onClick: () => openEditModal(displayGroup),
    },
    {
      label: t('Copy'),
      icon: Copy,
      onClick: () => openCopyModal(displayGroup),
    },
    ...(openMoveModal
      ? [
          {
            label: t('Move'),
            icon: Folder,
            onClick: () => openMoveModal(displayGroup),
          },
        ]
      : []),
    {
      label: t('Share'),
      icon: UserPlus2,
      onClick: () => openShareModal(displayGroup),
    },
    { isSeparator: true },
    {
      label: t('Schedule'),
      icon: Calendar,
      onClick: () => openScheduleModal(displayGroup),
    },
    { isSeparator: true },
    {
      label: t('Assign Files'),
      onClick: () => openAssignFilesModal(displayGroup),
    },
    {
      label: t('Assign Layouts'),
      onClick: () => openAssignLayoutsModal(displayGroup),
    },

    { isSeparator: true },
    {
      label: t('Send Command'),
      onClick: () => openSendCommandModal(displayGroup),
    },
    {
      label: t('Collect Now'),
      onClick: () => collectNow(displayGroup),
    },
    {
      label: t('Trigger a web hook'),
      onClick: () => triggerWebhook(displayGroup),
    },
    { isSeparator: true },

    {
      label: t('Delete'),
      icon: Trash2,
      onClick: () => onDelete(displayGroup),
      variant: 'danger' as const,
    },
  ];
};

interface GetBulkActionsProps {
  t: TFunction;
  onDelete: () => void;
  onMove: () => void;
  onBulkSendCommand: () => void;
  onBulkTriggerWebhook: () => void;
  onBulkShare: () => void;
}

export const getBulkActions = ({
  t,
  onDelete,
  onMove,
  onBulkSendCommand,
  onBulkTriggerWebhook,
  onBulkShare,
}: GetBulkActionsProps): DataTableBulkAction<DisplayGroup>[] => [
  {
    label: t('Move'),
    icon: Folder,
    onClick: onMove,
  },
  {
    label: t('Send Command'),
    icon: Terminal,
    onClick: onBulkSendCommand,
  },
  {
    label: t('Trigger a web hook'),
    icon: Webhook,
    onClick: onBulkTriggerWebhook,
  },
  {
    label: t('Share'),
    icon: UserPlus2,
    onClick: onBulkShare,
  },
  {
    label: t('Delete Selected'),
    icon: Trash2,
    onClick: onDelete,
  },
];

export const getDisplayGroupColumns = (
  props: DisplayGroupActionsProps,
): ColumnDef<DisplayGroup>[] => {
  const { t } = props;
  const getActions = getDisplayGroupItemActions(props);
  return [
    {
      accessorKey: 'displayGroupId',
      header: t('ID'),
      size: 80,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },
    {
      accessorKey: 'displayGroup',
      header: t('Name'),
      size: 200,
      enableHiding: false,
      cell: (info) => <TextCell weight="bold">{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'description',
      header: t('Description'),
      size: 200,
      cell: (info) => <TextCell truncate>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'isDynamic',
      header: t('Is Dynamic?'),
      size: 110,
      cell: (info) => <CheckMarkCell active={info.getValue() === 1} />,
    },
    {
      accessorKey: 'dynamicCriteria',
      header: t('Criteria'),
      size: 180,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'dynamicCriteriaTags',
      header: t('Criteria Tags'),
      size: 160,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'tags',
      header: t('Tags'),
      size: 160,
      cell: ({ row }) => (
        <TagsCell
          tags={(row.original.tags ?? []).map((tag) => ({ id: tag.tagId, label: tag.tag }))}
        />
      ),
    },
    {
      accessorKey: 'groupsWithPermissions',
      header: t('Sharing'),
      size: 160,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'ref1',
      header: t('Reference 1'),
      size: 140,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'ref2',
      header: t('Reference 2'),
      size: 140,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'ref3',
      header: t('Reference 3'),
      size: 140,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'ref4',
      header: t('Reference 4'),
      size: 140,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'ref5',
      header: t('Reference 5'),
      size: 140,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'createdDt',
      header: t('Created Date'),
      size: 160,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'modifiedDt',
      header: t('Modified Date'),
      size: 160,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
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
