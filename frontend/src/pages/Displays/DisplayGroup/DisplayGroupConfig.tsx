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
  Tags,
  Trash2,
  UserPlus2,
  Users,
  Webhook,
} from 'lucide-react';
import { type ComponentProps } from 'react';

import type { FilterConfigItem } from '@/components/ui/FilterInputs';
import type { DataTableBulkAction } from '@/components/ui/table/DataTableBulkActions';
import {
  ActionsCell,
  CheckMarkCell,
  getSharingColumn,
  TagsCell,
  TextCell,
} from '@/components/ui/table/cells';
import type { DisplayGroup } from '@/types/displayGroup';
import type { ActionItem, BaseModalType } from '@/types/table';
import type { Tag } from '@/types/tag';
import type { DateLike } from '@/utils/date';

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
  | 'editTagsMultiple'
  | null;

export interface DisplayGroupFilterInput {
  displayGroup: string;
  displayGroupId: number | null;
  displayIdDropdown: number | null;
  nestedDisplayId: number | null;
  dynamicCriteria: string;
  tags: Tag[];
  logicalOperatorName?: 'OR' | 'AND';
  useRegexForName?: boolean;
  logicalOperator?: 'OR' | 'AND';
  exactTags?: boolean;
}

export const INITIAL_FILTER_STATE: DisplayGroupFilterInput = {
  displayGroup: '',
  displayGroupId: null,
  displayIdDropdown: null,
  nestedDisplayId: null,
  dynamicCriteria: '',
  tags: [],
  logicalOperatorName: 'OR',
  useRegexForName: false,
  logicalOperator: 'OR',
  exactTags: false,
};

export const getBaseFilterKeys = (
  t: TFunction,
  canTag = false,
): FilterConfigItem<DisplayGroupFilterInput>[] => [
  {
    label: t('ID'),
    name: 'displayGroupId',
    type: 'number',
    placeholder: ' ',
  },
  {
    label: t('Name'),
    name: 'displayGroup',
    type: 'text',
    className: '',
    placeholder: ' ',
    showAndOr: true,
    andOrKey: 'logicalOperatorName',
    showRegex: true,
    regexKey: 'useRegexForName',
  },
  {
    label: t('Display'),
    name: 'displayIdDropdown',
    placeholder: t('All'),
    options: [],
  },
  {
    label: t('Nested Display'),
    name: 'nestedDisplayId',
    placeholder: t('All'),
    options: [],
  },
  {
    label: t('Dynamic Criteria'),
    name: 'dynamicCriteria',
    type: 'text',
    placeholder: ' ',
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
      ] as FilterConfigItem<DisplayGroupFilterInput>[])
    : []),
];

export interface DisplayGroupActionsProps {
  t: TFunction;
  canModify?: boolean;
  canTag?: boolean;
  canUserShare?: boolean;
  canLimitedView?: boolean;
  canCommandView?: boolean;
  scheduleWithView?: boolean;
  onDelete: (displayGroup: DisplayGroup) => void;
  openEditModal: (displayGroup: DisplayGroup) => void;
  openCopyModal: (displayGroup: DisplayGroup) => void;
  openMembersModal: (displayGroup: DisplayGroup) => void;
  openMoveModal?: (displayGroup: DisplayGroup) => void;
  openScheduleModal?: (displayGroup: DisplayGroup) => void;
  openAssignFilesModal: (displayGroup: DisplayGroup) => void;
  openAssignLayoutsModal: (displayGroup: DisplayGroup) => void;
  openShareModal: (displayGroup: DisplayGroup) => void;
  openSendCommandModal: (displayGroup: DisplayGroup) => void;
  collectNow: (displayGroup: DisplayGroup) => void;
  triggerWebhook: (displayGroup: DisplayGroup) => void;
  formatDateTime: (value: DateLike) => string;
}

export const getDisplayGroupItemActions = ({
  t,
  canModify = false,
  canUserShare = false,
  canLimitedView = false,
  canCommandView = false,
  scheduleWithView = false,
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
  return (displayGroup: DisplayGroup) => {
    const canEdit = !!displayGroup.userPermissions?.edit;
    const canDelete = !!displayGroup.userPermissions?.delete;
    const canShare = !!displayGroup.userPermissions?.modifyPermissions;

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
        onClick: () => openEditModal(displayGroup),
        isQuickAction: true,
        variant: 'primary' as const,
      });
    }

    if (canModify && canEdit && Number(displayGroup.isDynamic) === 0) {
      actions.push({
        label: t('Members'),
        icon: Users,
        onClick: () => openMembersModal(displayGroup),
      });
      actions.push({ isSeparator: true });
    }

    if (canModify && canEdit) {
      actions.push({
        label: t('Edit'),
        icon: Edit,
        onClick: () => openEditModal(displayGroup),
      });
    }

    if (canModify && canEdit) {
      actions.push({
        label: t('Copy'),
        icon: Copy,
        onClick: () => openCopyModal(displayGroup),
      });
    }

    if (canModify && canEdit && openMoveModal) {
      actions.push({
        label: t('Move'),
        icon: Folder,
        onClick: () => openMoveModal(displayGroup),
      });
    }

    if (canModify && canShare && canUserShare) {
      actions.push({
        label: t('Share'),
        icon: UserPlus2,
        onClick: () => openShareModal(displayGroup),
      });
    }

    if (openScheduleModal && (canEdit || scheduleWithView)) {
      actions.push({ isSeparator: true });
      actions.push({
        label: t('Schedule'),
        icon: Calendar,
        onClick: () => openScheduleModal(displayGroup),
      });
    }

    if (canModify && canEdit) {
      actions.push({ isSeparator: true });
      actions.push({
        label: t('Assign Files'),
        onClick: () => openAssignFilesModal(displayGroup),
      });
      actions.push({
        label: t('Assign Layouts'),
        onClick: () => openAssignLayoutsModal(displayGroup),
      });
    }

    const canSendCommand = canModify || canCommandView;
    const canCollectNow = (canModify && canEdit) || canLimitedView;

    if (canSendCommand || canCollectNow) {
      addSeparator();
    }

    if (canSendCommand) {
      actions.push({
        label: t('Send Command'),
        onClick: () => openSendCommandModal(displayGroup),
      });
    }

    if (canCollectNow) {
      actions.push({
        label: t('Collect Now'),
        onClick: () => collectNow(displayGroup),
      });
    }

    if (canModify && canEdit) {
      actions.push({
        label: t('Trigger a web hook'),
        onClick: () => triggerWebhook(displayGroup),
      });
    }

    if (canModify && canDelete) {
      actions.push({ isSeparator: true });
      actions.push({
        label: t('Delete'),
        icon: Trash2,
        onClick: () => onDelete(displayGroup),
        variant: 'danger' as const,
      });
    }

    return actions;
  };
};

interface GetBulkActionsProps {
  t: TFunction;
  canModify?: boolean;
  canLimitedView?: boolean;
  onDelete: () => void;
  onMove: () => void;
  onBulkSendCommand: () => void;
  onBulkTriggerWebhook: () => void;
  onBulkShare: () => void;
  onEditTags?: () => void;
}

export const getBulkActions = ({
  t,
  canModify = false,
  canLimitedView = false,
  onDelete,
  onMove,
  onBulkSendCommand,
  onBulkTriggerWebhook,
  onBulkShare,
  onEditTags,
}: GetBulkActionsProps): DataTableBulkAction<DisplayGroup>[] => {
  const actions: DataTableBulkAction<DisplayGroup>[] = [];

  if (canModify) {
    actions.push({
      label: t('Move'),
      icon: Folder,
      onClick: onMove,
    });
  }

  if (canModify || canLimitedView) {
    actions.push({
      label: t('Send Command'),
      icon: Terminal,
      onClick: onBulkSendCommand,
    });
    actions.push({
      label: t('Trigger a web hook'),
      icon: Webhook,
      onClick: onBulkTriggerWebhook,
    });
  }

  if (canModify) {
    actions.push({
      label: t('Share'),
      icon: UserPlus2,
      onClick: onBulkShare,
    });
  }

  if (canModify && onEditTags) {
    actions.push({
      label: t('Edit Tags'),
      icon: Tags,
      onClick: onEditTags,
    });
  }

  if (canModify) {
    actions.push({
      label: t('Delete Selected'),
      icon: Trash2,
      onClick: onDelete,
    });
  }

  return actions;
};

export const getDisplayGroupColumns = (
  props: DisplayGroupActionsProps,
): ColumnDef<DisplayGroup>[] => {
  const { t, formatDateTime, canTag = false } = props;
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
    ...(canTag
      ? ([
          {
            accessorKey: 'tags',
            header: t('Tags'),
            size: 160,
            cell: ({ row }) => (
              <TagsCell
                tags={(row.original.tags ?? []).map((tag) => ({
                  id: tag.tagId,
                  label: tag.value ? `${tag.tag}|${tag.value}` : tag.tag,
                }))}
              />
            ),
          },
        ] as ColumnDef<DisplayGroup>[])
      : []),
    getSharingColumn<DisplayGroup>(t),
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
      cell: (info) => <TextCell>{formatDateTime(info.getValue<string>())}</TextCell>,
    },
    {
      accessorKey: 'modifiedDt',
      header: t('Modified Date'),
      size: 160,
      cell: (info) => <TextCell>{formatDateTime(info.getValue<string>())}</TextCell>,
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
