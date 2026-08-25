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
import { Search } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import type { FilterConfigItem } from '@/components/ui/FilterInputs';
import { TextCell } from '@/components/ui/table/cells';
import type { AuditLog } from '@/types/auditTrail';
import { type DateLike, formatCmsDateTime } from '@/utils/date';

export interface AuditTrailFilterInput {
  fromDt?: string;
  toDt?: string;
  user?: string;
  entity?: string;
  entityId?: string;
  ipAddress?: string;
  message?: string;
}

export type ModalType = 'export' | null;

export const INITIAL_FILTER_STATE: AuditTrailFilterInput = {
  fromDt: '',
  toDt: '',
  user: '',
  entity: '',
  entityId: '',
  ipAddress: '',
  message: '',
};

export const getBaseFilterKeys = (t: TFunction): FilterConfigItem<AuditTrailFilterInput>[] => [
  {
    label: t('From Date'),
    name: 'fromDt',
    type: 'date',
    showTimePicker: false,
  },
  {
    label: t('To Date'),
    name: 'toDt',
    type: 'date',
    showTimePicker: false,
  },
  {
    label: t('User'),
    name: 'user',
    type: 'text',
  },
  {
    label: t('Entity'),
    name: 'entity',
    type: 'text',
  },
  {
    label: t('Entity ID'),
    name: 'entityId',
    type: 'text',
  },
  {
    label: t('IP Address'),
    name: 'ipAddress',
    type: 'text',
  },
  {
    label: t('Message'),
    name: 'message',
    type: 'text',
  },
];

function ObjectAfterCell({ value }: { value: Record<string, unknown> | null }) {
  const { t } = useTranslation();
  const [open, setOpen] = useState(false);

  if (!value || Object.keys(value).length === 0) return null;

  return (
    <div>
      <button
        type="button"
        onClick={() => setOpen((prev) => !prev)}
        className="text-xibo-blue-600 hover:text-xibo-blue-800 flex items-center gap-1 cursor-pointer"
        title={t('View object')}
      >
        <Search size={14} />
      </button>

      {open && (
        <table className="mt-2 w-full text-xs border border-gray-200 rounded overflow-hidden">
          <thead>
            <tr className="bg-gray-50">
              <th className="px-2 py-1 border-b border-gray-200 text-left font-semibold uppercase text-gray-500">
                {t('Property')}
              </th>
              <th className="px-2 py-1 border-b border-gray-200 text-left font-semibold uppercase text-gray-500">
                {t('Value')}
              </th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-200">
            {Object.entries(value).map(([key, val]) => (
              <tr key={key} className="bg-white">
                <td className="px-2 py-1 font-medium text-gray-700 align-top">{key}</td>
                <td className="px-2 py-1 text-gray-600 break-all">
                  {val !== null && typeof val === 'object'
                    ? JSON.stringify(val)
                    : String(val ?? '')}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
}

export const getAuditTrailColumns = (
  t: TFunction,
  formatDateTime: (value: DateLike) => string = (value) => formatCmsDateTime(value),
): ColumnDef<AuditLog>[] => [
  {
    accessorKey: 'logId',
    header: t('ID'),
    size: 80,
    cell: (info) => <TextCell weight="bold">{String(info.getValue<number>())}</TextCell>,
  },
  {
    accessorKey: 'logDate',
    header: t('Date'),
    size: 180,
    cell: (info) => {
      const ts = info.getValue<number>();
      return <TextCell>{ts ? formatDateTime(new Date(ts * 1000)) : ''}</TextCell>;
    },
    meta: {
      getExportValue: (row) => (row.logDate ? formatDateTime(new Date(row.logDate * 1000)) : ''),
    },
  },
  {
    accessorKey: 'userName',
    header: t('User'),
    size: 140,
    cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
  },
  {
    accessorKey: 'entity',
    header: t('Entity'),
    size: 140,
    cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
  },
  {
    accessorKey: 'entityId',
    header: t('Entity ID'),
    size: 90,
    cell: (info) => {
      const val = info.getValue<number | null>();
      return <TextCell>{val !== null && val !== 0 ? String(val) : ''}</TextCell>;
    },
  },
  {
    accessorKey: 'ipAddress',
    header: t('IP Address'),
    size: 140,
    cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
  },
  {
    accessorKey: 'message',
    header: t('Message'),
    size: 250,
    cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
  },
  {
    id: 'objectAfter',
    header: t('Object'),
    size: 250,
    enableSorting: false,
    accessorFn: (row) => {
      if (!row.objectAfter || Object.keys(row.objectAfter).length === 0) return '';
      return JSON.stringify(row.objectAfter);
    },
    cell: (info) => <ObjectAfterCell value={info.row.original.objectAfter} />,
    meta: {
      excludeFromExport: true,
    },
  },
];
