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
import { decodeHtmlEntities } from '@/utils/stringUtils';

export interface LogsFilterInput {
  fromDt?: string;
  level?: string;
  intervalType?: string;
  seconds?: string;
  runNo?: string;
  userId?: string;
  channel?: string;
  page?: string;
  function?: string;
  displayId?: string;
  display?: string;
  useRegexForName?: boolean;
  displayGroupId?: string;
  message?: string;
  excludeLog?: boolean;
}

export type ModalType = 'truncate' | null;

export const INITIAL_FILTER_STATE: LogsFilterInput = {
  fromDt: '',
  level: '',
  intervalType: '1',
  seconds: '120',
  runNo: '',
  userId: '',
  channel: '',
  page: '',
  function: '',
  displayId: '',
  display: '',
  useRegexForName: false,
  displayGroupId: '',
  message: '',
  excludeLog: true,
};

export const getBaseFilterKeys = (t: TFunction): FilterConfigItem<LogsFilterInput>[] => [
  {
    label: t('Date'),
    name: 'fromDt',
    type: 'date',
    tooltip: t(
      'Set the time to start searching for logs based on the interval filter. Leave empty to start from the current time.',
    ),
  },
  {
    label: t('Level'),
    name: 'level',
    type: 'text',
  },
  {
    label: t('Interval'),
    name: 'intervalType',
    type: 'select',
    clearable: false,
    searchable: false,
    options: [
      { label: t('Seconds'), value: '1' },
      { label: t('Minutes'), value: '60' },
      { label: t('Hours'), value: '3600' },
    ],
  },
  {
    label: t('Duration Back'),
    name: 'seconds',
    type: 'number',
  },
  {
    label: t('Run'),
    name: 'runNo',
    type: 'text',
  },
  {
    label: t('User'),
    name: 'userId',
    type: 'select',
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
    name: 'displayId',
    type: 'select',
  },
  {
    label: t('Display Name'),
    name: 'display',
    type: 'text',
    showRegex: true,
    regexKey: 'useRegexForName',
  },
  {
    label: t('Display Group'),
    name: 'displayGroupId',
    type: 'select',
  },
  {
    label: t('Message'),
    name: 'message',
    type: 'text',
  },
  {
    label: t('Exclude logs common to each request?'),
    name: 'excludeLog',
    type: 'checkbox',
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
    header: t('Run'),
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
    cell: (info) => <TextCell wrap>{decodeHtmlEntities(info.getValue<string>())}</TextCell>,
  },
];
