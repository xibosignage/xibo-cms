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
import { Copy, Download, Edit, Share2, Trash2 } from 'lucide-react';
import type { ComponentProps } from 'react';

import type { FilterConfigItem } from '@/components/ui/FilterInputs';
import type { SelectOption } from '@/components/ui/forms/SelectDropdown';
import { ActionsCell, TextCell } from '@/components/ui/table/cells';
import { withPublicPath } from '@/config/publicPath';
import type { ModuleTemplate } from '@/types/moduleTemplates';
import type { ActionItem } from '@/types/table';

export const getShowInOptions = (t: TFunction): SelectOption[] => [
  { label: t('None'), value: 'none' },
  { label: t('Layout Editor'), value: 'layout' },
  { label: t('Playlist Editor'), value: 'playlist' },
  { label: t('Both'), value: 'both' },
];

export interface ModuleTemplateFilterInput {
  id?: number | null;
  templateId?: string | null;
  dataType?: string | null;
}

export type ModalType = 'add' | 'copy' | 'delete' | 'share' | null;

export const INITIAL_FILTER_STATE: ModuleTemplateFilterInput = {
  id: null,
  templateId: null,
  dataType: null,
};

export const getFilterKeys = (
  t: TFunction,
  dataTypeOptions: Array<{ label: string; value: string }>,
): FilterConfigItem<ModuleTemplateFilterInput>[] => [
  {
    label: t('ID'),
    name: 'id',
    type: 'number',
    placeholder: ' ',
  },
  {
    label: t('Template ID'),
    name: 'templateId',
    type: 'text',
    placeholder: ' ',
  },
  {
    label: t('Data Type'),
    name: 'dataType',
    type: 'select',
    options: dataTypeOptions,
    placeholder: ' ',
  },
];

export interface ModuleTemplateActionsProps {
  t: TFunction;
  onEdit: (template: ModuleTemplate) => void;
  onCopy: (template: ModuleTemplate) => void;
  onDelete: (template: ModuleTemplate) => void;
  onShare: (template: ModuleTemplate) => void;
}

export const getModuleTemplateItemActions = ({
  t,
  onEdit,
  onCopy,
  onDelete,
  onShare,
}: ModuleTemplateActionsProps): ((template: ModuleTemplate) => ActionItem[]) => {
  return (template: ModuleTemplate) => {
    const actions: ActionItem[] = [];

    if (template.ownership === 'user') {
      actions.push(
        {
          label: t('Edit'),
          icon: Edit,
          onClick: () => onEdit(template),
          isQuickAction: true,
          variant: 'primary' as const,
        },
        {
          label: t('Edit'),
          icon: Edit,
          onClick: () => onEdit(template),
        },
        {
          label: t('Export XML'),
          icon: Download,
          onClick: () => {
            window.location.href = withPublicPath(`developer/template/${template.id}/export`);
          },
        },
        {
          label: t('Copy'),
          icon: Copy,
          onClick: () => onCopy(template),
        },
      );
    }

    actions.push({ isSeparator: true });
    actions.push({
      label: t('Share'),
      icon: Share2,
      onClick: () => onShare(template),
    });

    if (template.ownership === 'user') {
      actions.push({ isSeparator: true });
      actions.push({
        label: t('Delete'),
        icon: Trash2,
        onClick: () => onDelete(template),
        variant: 'danger' as const,
      });
    }

    return actions;
  };
};

export const getModuleTemplateColumns = (
  props: ModuleTemplateActionsProps,
): ColumnDef<ModuleTemplate>[] => {
  const { t } = props;
  const getActions = getModuleTemplateItemActions(props);
  return [
    {
      accessorKey: 'id',
      header: t('ID'),
      size: 70,
      cell: (info) => <TextCell>{String(info.getValue<number>())}</TextCell>,
    },
    {
      accessorKey: 'templateId',
      header: t('Template ID'),
      size: 200,
      enableHiding: false,
      cell: (info) => <TextCell weight="bold">{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'dataType',
      header: t('Data Type'),
      size: 150,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'title',
      header: t('Title'),
      size: 250,
      enableSorting: false,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'type',
      header: t('Type'),
      size: 100,
      enableSorting: false,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'groupsWithPermissions',
      header: t('Sharing'),
      size: 200,
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
