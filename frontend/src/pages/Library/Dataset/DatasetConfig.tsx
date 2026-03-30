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
import { CopyCheck, Edit, FileDown, FileUp, FolderInput, Trash2, UserPlus2 } from 'lucide-react';
import { type ComponentProps } from 'react';

import type { FilterConfigItem } from '@/components/ui/FilterInputs';
import type { DataTableBulkAction } from '@/components/ui/table/DataTableBulkActions';
import { TextCell, ActionsCell, CheckMarkCell } from '@/components/ui/table/cells';
import { getCommonFormOptions } from '@/config/commonForms';
import type { Dataset } from '@/types/dataset';
import type { ActionItem, BaseModalType } from '@/types/table';

export interface DatasetFilterInput {
  dataSetId: string;
  code: string;
  userId: string;
  lastModified: string;
}

export type ModalType = BaseModalType | null;

export const INITIAL_FILTER_STATE: DatasetFilterInput = {
  dataSetId: '',
  userId: '',
  code: '',
  lastModified: '',
};

export const getBaseFilterKeys = (t: TFunction): FilterConfigItem<DatasetFilterInput>[] => [
  {
    label: t('Dataset ID'),
    placeholder: t('Enter ID'),
    name: 'dataSetId',
    type: 'number',
  },
  {
    label: t('Code'),
    placeholder: t('Enter Code'),
    name: 'code',
    type: 'text',
  },
  {
    label: t('Owner'),
    name: 'userId',
    className: '',
    shouldTranslateOptions: false,
    showAllOption: false,
    options: [{ label: t('Select Owner'), value: null }],
  },
  {
    label: t('Last Modified'),
    name: 'lastModified',
    className: '',
    shouldTranslateOptions: true,
    showAllOption: false,
    allowCustomRange: true,
    options: getCommonFormOptions(t).lastModifiedFilter,
  },
];

export interface DatasetActionsProps {
  t: TFunction;
  onDelete: (id: number) => void;
  openAddEditModal: (row: Dataset) => void;
  openShareModal?: (id: number) => void;
  openMoveModal?: (row: Dataset | Dataset[]) => void;
  copyDataset?: (row: number) => void;
  onNavigate: (path: string) => void;
  onExportCsv?: (id: number) => void;
  onImportCsv?: (id: number) => void;
}

export const getDatasetItemActions = ({
  t,
  onDelete,
  openAddEditModal,
  openShareModal,
  openMoveModal,
  copyDataset,
  onNavigate,
  onExportCsv,
  onImportCsv,
}: DatasetActionsProps): ((dataset: Dataset) => ActionItem[]) => {
  return (dataset: Dataset) => [
    // Quick Actions
    {
      label: t('Edit'),
      icon: Edit,
      onClick: () => openAddEditModal(dataset),
      isQuickAction: true,
      variant: 'primary' as const,
    },

    // Dropdown Menu Actions
    {
      label: t('Edit'),
      icon: Edit,
      onClick: () => openAddEditModal(dataset),
    },
    {
      label: t('Make a Copy'),
      icon: CopyCheck,
      onClick: () => copyDataset && copyDataset(dataset.dataSetId),
    },
    {
      label: t('Move'),
      icon: FolderInput,
      onClick: () => openMoveModal && openMoveModal(dataset),
    },
    {
      label: t('Share'),
      icon: UserPlus2,
      onClick: () => openShareModal && openShareModal(dataset.dataSetId),
    },
    {
      label: t('Import CSV'),
      icon: FileUp,
      onClick: () => {
        if (onImportCsv) {
          onImportCsv(dataset.dataSetId);
        }
      },
    },
    {
      label: t('Export CSV'),
      icon: FileDown,
      onClick: () => {
        if (onExportCsv) {
          onExportCsv(dataset.dataSetId);
        }
      },
    },
    { isSeparator: true },
    {
      label: t('View Data'),
      isNavigation: true,
      onClick: () => {
        onNavigate(`/library/datasets/${dataset.dataSetId}/data`);
      },
    },
    {
      label: t('View Columns'),
      isNavigation: true,
      onClick: () => {
        onNavigate(`/library/datasets/${dataset.dataSetId}/columns`);
      },
    },
    {
      label: t('View RSS'),
      isNavigation: true,
      onClick: () => {
        onNavigate(`/library/datasets/${dataset.dataSetId}/rss`);
      },
    },
    { isSeparator: true },
    {
      label: t('Delete'),
      icon: Trash2,
      onClick: () => onDelete(dataset.dataSetId),
      variant: 'danger' as const,
    },
  ];
};

export const getDatasetColumns = (props: DatasetActionsProps): ColumnDef<Dataset>[] => {
  const { t } = props;
  const getActions = getDatasetItemActions(props);
  return [
    {
      accessorKey: 'dataSetId',
      header: t('ID'),
      size: 60,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },
    {
      accessorKey: 'dataSet',
      header: t('Name'),
      size: 150,
      enableHiding: false,
      cell: (info) => <TextCell weight="bold">{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'description',
      header: t('Description'),
      size: 150,
      cell: (info) => <TextCell weight="bold">{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'code',
      header: t('Code'),
      size: 100,
      cell: (info) => <TextCell weight="bold">{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'isRemote',
      header: t('Remote'),
      size: 100,
      cell: (info) => <CheckMarkCell active={info.getValue() === 1} />,
    },
    {
      accessorKey: 'isRealTime',
      header: t('Real time?'),
      size: 100,
      cell: (info) => <CheckMarkCell active={info.getValue() === 1} />,
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
      size: 120,
      cell: (info) => {
        const groups = info.getValue() as string;
        return <TextCell className="italic text-gray-500">{groups || t('Private')}</TextCell>;
      },
    },
    {
      accessorKey: 'dataLastModified',
      header: t('Modified'),
      size: 160,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'lastSync',
      header: t('Last Sync'),
      size: 160,
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
  onMove: () => void;
  onShare: () => void;
}

export const getBulkActions = ({
  t,
  onDelete,
  onMove,
  onShare,
}: GetBulkActionsProps): DataTableBulkAction<Dataset>[] => {
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
