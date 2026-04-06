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

import type { DatasetColumn } from './datasetColumn';

export interface DatasetPermissions {
  view?: number;
  edit?: number;
  delete?: number;
  modifyPermissions?: number;
}

export interface Dataset {
  dataSetId: number;
  dataSet: string;
  description: string;
  userId: number;
  owner: string;
  lastDataEdit: number;
  groupsWithPermissions: string;
  code: string;
  isLookup: boolean;
  isRemote: boolean;
  isRealTime: boolean;
  dataConnectorSource: string;
  method: DatasetConnectorMethod;
  uri: string;
  postData: string;
  authentication: DatasetConnectorAuth;
  username: string;
  password: string;
  customHeaders: string;
  userAgent: string;
  refreshRate: number;
  clearRate: number;
  truncateOnEmpty: number;
  runsAfter: number;
  lastSync: number;
  lastClear: number;
  dataRoot: string;
  summarize: DatasetSummarize;
  summarizeField: string;
  sourceId: '1' | '2';
  ignoreFirstRow: boolean;
  rowLimit: number;
  limitPolicy: DatasetLimitPolicy;
  csvSeparator: string;
  folderId: number;
  permissionsFolderId: number;
  permissions: DatasetPermissions[];
  columns?: DatasetColumn[];
  isActive?: boolean;
}

export type DatasetConnectorAuth = 'none' | 'basic' | 'digest' | 'ntlm' | 'bearer';
export type DatasetConnectorMethod = 'GET' | 'POST';
export type DatasetSummarize = 'none' | 'sum' | 'count';
export type DatasetLimitPolicy = 'stop' | 'fifo' | 'truncate';
