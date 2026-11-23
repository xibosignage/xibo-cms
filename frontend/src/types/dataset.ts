/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
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
  groupsWithPermissionsList?: string[] | null;
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
  userPermissions?: DatasetPermissions;
  columns?: DatasetColumn[];
  isActive?: boolean;
}

export type DatasetConnectorAuth = 'none' | 'basic' | 'digest' | 'ntlm' | 'bearer';
export type DatasetConnectorMethod = 'GET' | 'POST';
export type DatasetSummarize = 'none' | 'sum' | 'count';
export type DatasetLimitPolicy = 'stop' | 'fifo' | 'truncate';
