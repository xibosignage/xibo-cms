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

import type { FilterConfigItem } from '@/components/ui/FilterInputs';
import { TextCell } from '@/components/ui/table/cells';
import type { LogEntry } from '@/types/log';

function decodeHtmlEntities(encoded: string): string {
  const textarea = document.createElement('textarea');
  textarea.innerHTML = encoded;
  return textarea.value;
}

export interface LogsFilterInput {
  fromDt?: string;
  seconds?: string;
  intervalType?: string;
  level?: string;
  runNo?: string;
  userId?: string;
  channel?: string;
  page?: string;
  function?: string;
  displayId?: string;
  display?: string;
  useRegexForName?: string;
  displayGroupId?: string;
  message?: string;
  excludeLog?: string;
}

export type ModalType = 'truncate' | null;

export const INITIAL_FILTER_STATE: LogsFilterInput = {
  fromDt: '',
  seconds: '24',
  intervalType: '3600',
  level: '',
  runNo: '',
  userId: '',
  channel: '',
  page: '',
  function: '',
  displayId: '',
  display: '',
  useRegexForName: '',
  displayGroupId: '',
  message: '',
  excludeLog: '',
};

export const getBaseFilterKeys = (t: TFunction): FilterConfigItem<LogsFilterInput>[] => [
  {
    label: t('From Date'),
    name: 'fromDt',
    type: 'date',
  },
  {
    label: t('Duration Back'),
    name: 'seconds',
    type: 'number',
  },
  {
    label: t('Interval Type'),
    name: 'intervalType',
    type: 'select',
    options: [
      { label: t('Seconds'), value: '1' },
      { label: t('Minutes'), value: '60' },
      { label: t('Hours'), value: '3600' },
    ],
  },
  {
    label: t('Level'),
    name: 'level',
    type: 'text',
  },
  {
    label: t('Run No'),
    name: 'runNo',
    type: 'text',
  },
  {
    label: t('User ID'),
    name: 'userId',
    type: 'number',
  },
  {
    label: t('Channel'),
    name: 'channel',
    type: 'text',
  },
  {
    label: t('Page'),
    name: 'page',
    type: 'text',
  },
  {
    label: t('Function'),
    name: 'function',
    type: 'select',
    options: [
      { label: t('All'), value: '' },
      { label: 'GET', value: 'GET' },
      { label: 'POST', value: 'POST' },
      { label: 'PUT', value: 'PUT' },
      { label: 'DELETE', value: 'DELETE' },
      { label: 'HEAD', value: 'HEAD' },
      { label: 'PATCH', value: 'PATCH' },
    ],
  },
  {
    label: t('Display'),
    name: 'display',
    type: 'text',
    showRegex: true,
    regexKey: 'useRegexForName',
  },
  {
    label: t('Display ID'),
    name: 'displayId',
    type: 'number',
  },
  {
    label: t('Display Group ID'),
    name: 'displayGroupId',
    type: 'number',
  },
  {
    label: t('Message'),
    name: 'message',
    type: 'text',
  },
  {
    label: t('Exclude Log'),
    name: 'excludeLog',
    type: 'select',
    options: [
      { label: t('All'), value: '' },
      { label: t('Yes'), value: '1' },
    ],
  },
];

export const getLogsColumns = (t: TFunction): ColumnDef<LogEntry>[] => [
  {
    accessorKey: 'logId',
    header: t('ID'),
    size: 80,
    cell: (info) => <TextCell weight="bold">{String(info.getValue<number>())}</TextCell>,
  },
  {
    accessorKey: 'runNo',
    header: t('Run No'),
    size: 100,
    cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
  },
  {
    accessorKey: 'logDate',
    header: t('Date'),
    size: 180,
    cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
  },
  {
    accessorKey: 'channel',
    header: t('Channel'),
    size: 100,
    cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
  },
  {
    accessorKey: 'function',
    header: t('Function'),
    size: 90,
    cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
  },
  {
    accessorKey: 'type',
    header: t('Level'),
    size: 90,
    cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
  },
  {
    accessorKey: 'display',
    header: t('Display'),
    size: 140,
    cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
  },
  {
    accessorKey: 'page',
    header: t('Page'),
    size: 160,
    cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
  },
  {
    accessorKey: 'message',
    header: t('Message'),
    size: 300,
    cell: (info) => <TextCell>{decodeHtmlEntities(info.getValue<string>())}</TextCell>,
  },
];
