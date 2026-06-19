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
import { ArrowLeft, Calendar, ExternalLink, FileDown, Trash2 } from 'lucide-react';
import type { ComponentProps } from 'react';

import type { FilterConfigItem } from '@/components/ui/FilterInputs';
import type { DataTableBulkAction } from '@/components/ui/table/DataTableBulkActions';
import { ActionsCell, TextCell } from '@/components/ui/table/cells';
import type { SavedReport } from '@/types/savedReport';
import type { ActionItem } from '@/types/table';
import { formatDateTime } from '@/utils/date';

export interface SavedReportFilterInput {
  saveAs: string;
  useRegexForName: boolean;
  logicalOperatorName: 'OR' | 'AND';
  userId: string;
  reportName: string;
  onlyMyReports: string;
}

export type ModalType = 'delete';

export const INITIAL_FILTER_STATE: SavedReportFilterInput = {
  saveAs: '',
  useRegexForName: false,
  logicalOperatorName: 'OR',
  userId: '',
  reportName: '',
  onlyMyReports: '',
};

function formatUnixTimestamp(ts: number): string {
  if (!ts) {
    return '';
  }
  return formatDateTime(new Date(ts * 1000));
}

export const getBaseFilterKeys = (t: TFunction): FilterConfigItem<SavedReportFilterInput>[] => [
  {
    label: t('Name'),
    name: 'saveAs',
    type: 'text',
    placeholder: ' ',
    showAndOr: true,
    andOrKey: 'logicalOperatorName',
    showRegex: true,
    regexKey: 'useRegexForName',
  },
  {
    label: t('Owner'),
    name: 'userId',
    options: [{ label: t('Select Owner'), value: null }],
  },
  {
    label: t('Type'),
    name: 'reportName',
    options: [],
  },
  {
    label: t('Only My Reports'),
    name: 'onlyMyReports',
    options: [
      { label: t('All'), value: '' },
      { label: t('Yes'), value: '1' },
    ],
  },
];

export interface SavedReportActionsProps {
  t: TFunction;
  reportDescriptionMap: Record<string, string>;
  onDelete: (id: number) => void;
  onGoToSchedule: (report: SavedReport) => void;
}

export const getSavedReportItemActions = (
  props: SavedReportActionsProps,
): ((report: SavedReport) => ActionItem[]) => {
  const { t, onDelete, onGoToSchedule } = props;
  return (report: SavedReport) => {
    const openUrl = `/report/savedreport/${report.savedReportId}/report/${report.reportName}/open`;
    const exportUrl = `/report/savedreport/${report.savedReportId}/report/${report.reportName}/export`;
    return [
      {
        label: t('Open'),
        icon: ExternalLink,
        onClick: () => {
          window.location.href = openUrl;
        },
        isQuickAction: true,
        variant: 'primary' as const,
      },
      {
        label: t('Open'),
        icon: ExternalLink,
        onClick: () => {
          window.location.href = openUrl;
        },
      },
      {
        label: t('Back to Reports'),
        icon: ArrowLeft,
        onClick: () => {
          window.location.href = `/report/form/${report.reportName}`;
        },
      },
      {
        label: t('Go to Schedule'),
        icon: Calendar,
        onClick: () => onGoToSchedule(report),
      },
      {
        label: t('Export as PDF'),
        icon: FileDown,
        onClick: () => {
          window.location.href = exportUrl;
        },
      },
      { isSeparator: true },
      {
        label: t('Delete'),
        icon: Trash2,
        onClick: () => onDelete(report.savedReportId),
        variant: 'danger' as const,
      },
    ];
  };
};

export const getSavedReportColumns = (props: SavedReportActionsProps): ColumnDef<SavedReport>[] => {
  const { t, reportDescriptionMap } = props;
  const getActions = getSavedReportItemActions(props);

  return [
    {
      accessorKey: 'reportScheduleName',
      header: t('Report Schedule'),
      size: 200,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'saveAs',
      header: t('Saved As'),
      size: 200,
      enableHiding: false,
      cell: (info) => <TextCell weight="bold">{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'reportName',
      header: t('Report Type'),
      size: 160,
      cell: (info) => {
        const internalName = info.getValue<string>();
        return <TextCell>{reportDescriptionMap[internalName] ?? internalName}</TextCell>;
      },
    },
    {
      accessorKey: 'generatedOn',
      header: t('Generated On'),
      size: 160,
      cell: (info) => <TextCell>{formatUnixTimestamp(info.getValue<number>())}</TextCell>,
    },
    {
      accessorKey: 'owner',
      header: t('Owner'),
      size: 140,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      id: 'tableActions',
      header: '',
      size: 120,
      minSize: 120,
      maxSize: 120,
      enableHiding: false,
      enableSorting: false,
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
}

export const getBulkActions = ({
  t,
  onDelete,
}: GetBulkActionsProps): DataTableBulkAction<SavedReport>[] => [
  {
    label: t('Delete Selected'),
    icon: Trash2,
    onClick: onDelete,
  },
];
