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

import axios from 'axios';

import http from '@/lib/api';
import type {
  Dataset,
  DatasetConnectorAuth,
  DatasetConnectorMethod,
  DatasetLimitPolicy,
  DatasetSummarize,
} from '@/types/dataset';
import type { DatasetColumn } from '@/types/datasetColumn';
import type { DatasetRss } from '@/types/datasetRss';

export type DatasetRowValue = string | number | boolean | null | undefined;
export type DynamicRowData = Record<string | number, DatasetRowValue>;

export interface FetchDatasetRequest {
  start: number;
  length: number;
  keyword?: string;
  sortBy?: string;
  sortDir?: string;
  signal?: AbortSignal;
  folderId?: number;

  userId?: string;
  ownerUserGroupId?: string;
  lastModified?: string;
}

export interface FetchDatasetResponse {
  rows: Dataset[];
  totalCount: number;
}

export async function fetchDataset(
  options: FetchDatasetRequest = { start: 0, length: 10 },
): Promise<FetchDatasetResponse> {
  const { signal, ...queryParams } = options;

  const response = await http.get('/dataset', {
    params: queryParams,
    signal,
  });

  const rows = response.data;

  const totalCountHeader = response.headers['x-total-count'];
  const totalCount = totalCountHeader ? parseInt(totalCountHeader, 10) : 0;

  return {
    rows,
    totalCount,
  };
}

export async function getDatasetById(datasetId: string | number): Promise<Dataset> {
  const response = await http.get('/dataset', {
    params: { dataSetId: datasetId },
  });

  return response.data[0];
}

export interface CreateDatasetRequest {
  dataSet: string;
  folderId?: number | null;
  description: string;
  code: string;
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
  sourceId: '1' | '2';
  dataRoot: string;
  csvSeparator: string;
  ignoreFirstRow: boolean;
  summarize: DatasetSummarize;
  summarizeField: string;
  refreshRate: number;
  clearRate: number;
  truncateOnEmpty: number;
  runsAfter: number;
  rowLimit: number;
  limitPolicy: DatasetLimitPolicy;
}

export async function createDataset(data: UpdateDatasetRequest): Promise<Dataset> {
  const params = new URLSearchParams();

  Object.entries(data).forEach(([key, value]) => {
    if (value !== undefined && value !== null) {
      params.append(key, String(value));
    }
  });

  const response = await http.post(`/dataset`, params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });

  return response.data;
}

export interface UpdateDatasetRequest {
  testDataSetId: number;
  dataSet: string;
  folderId?: number | null;
  description: string;
  code: string;
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
  sourceId: '1' | '2';
  dataRoot: string;
  csvSeparator: string;
  ignoreFirstRow: boolean;
  summarize: DatasetSummarize;
  summarizeField: string;
  refreshRate: number;
  clearRate: number;
  truncateOnEmpty: number;
  runsAfter: number;
  rowLimit: number;
  limitPolicy: DatasetLimitPolicy;
}

export async function updateDataset(
  datasetId: number | string,
  data: UpdateDatasetRequest,
): Promise<Dataset> {
  const params = new URLSearchParams();

  Object.entries(data).forEach(([key, value]) => {
    if (value !== undefined && value !== null) {
      params.append(key, String(value));
    }
  });

  const response = await http.put(`/dataset/${datasetId}`, params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });

  return response.data;
}

export interface CloneDatasetRequest {
  datasetId: number | string;
  dataSet: string;
  description: string;
  code: string;
  copyRows: boolean;
  signal?: AbortSignal;
}

export async function cloneDataset({
  datasetId,
  dataSet,
  description,
  code,
  copyRows,
}: CloneDatasetRequest): Promise<Dataset> {
  const params = new URLSearchParams();

  params.append('dataSet', dataSet);
  params.append('description', description);
  params.append('code', code);

  if (copyRows) {
    params.append('copyRows', '1');
  }

  const response = await http.post(`/dataset/copy/${datasetId}`, params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });

  return response.data;
}

export type DeleteDatasetOptions = {
  deleteData?: boolean;
};

export async function deleteDataset(
  mediaId: number | string,
  options: DeleteDatasetOptions = {},
): Promise<void> {
  const body = new URLSearchParams();

  body.append('deleteData', options.deleteData ? '1' : '0');

  await http.delete(`/dataset/${mediaId}`, {
    data: body,
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });
}

export async function exportDatasetCsv(datasetId: number): Promise<void> {
  const response = await http.get(`/dataset/export/csv/${datasetId}`, {
    responseType: 'blob',
  });

  const contentDisposition = response.headers['content-disposition'];
  let fileName = `dataset-${datasetId}-.csv`;

  if (contentDisposition) {
    const fileNameMatch = contentDisposition.match(/filename="?(.+)"?/);
    if (fileNameMatch && fileNameMatch.length === 2) {
      fileName = fileNameMatch[1];
    }
  }

  const blob = new Blob([response.data], { type: 'text/csv;charset=utf-8;' });
  const downloadUrl = window.URL.createObjectURL(blob);

  const link = document.createElement('a');
  link.href = downloadUrl;
  link.setAttribute('donwload', fileName);
  document.body.appendChild(link);

  link.click();

  document.body.removeChild(link);
  window.URL.revokeObjectURL(downloadUrl);
}

export interface ImportDatasetCsvOptions {
  file: File;
  overwrite: boolean;
  ignoreFirstRow: boolean;
  mappings: Record<string, number>;
}

export async function importDatasetCsv(
  datasetId: string | number,
  options: ImportDatasetCsvOptions,
  onProgress?: (percentage: number) => void,
): Promise<void> {
  const formData = new FormData();
  formData.append('files[]', options.file);
  formData.append('overwrite', options.overwrite ? '1' : '0');
  formData.append('ignorefirstrow', options.ignoreFirstRow ? '1' : '0');

  Object.entries(options.mappings).forEach(([colId, index]) => {
    formData.append(`csvImport_${colId}`, String(index));
  });

  const response = await http.post(`/dataset/import/${datasetId}`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
    responseType: 'text',
    onUploadProgress: (progressEvent) => {
      if (progressEvent.total) {
        const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total);
        if (onProgress) onProgress(percentCompleted);
      }
    },
  });

  let responseData = response.data;

  if (typeof responseData === 'string') {
    const jsonStartIndex = responseData.indexOf('{');
    if (jsonStartIndex !== -1) {
      try {
        responseData = JSON.parse(responseData.substring(jsonStartIndex));
      } catch {
        throw new Error('Failed to parse server response.');
      }
    }
  }

  const fileResult = responseData?.files?.[0];
  if (fileResult && fileResult.error) {
    throw new Error(fileResult.error);
  }
}

export async function testRemoteDataset(payload: UpdateDatasetRequest) {
  const response = await axios.post('/api/dataset/remote/test', payload);
  return response.data;
}

/* Columns */
export interface FetchDatasetColumnsRequest {
  start: number;
  length: number;
  sortBy?: string;
  sortDir?: string;
  signal?: AbortSignal;
}

export interface FetchDatasetColumnsResponse {
  rows: DatasetColumn[];
  totalCount: number;
}

export async function fetchDatasetColumns(
  datasetId: string | number,
  options: FetchDatasetColumnsRequest = { start: 0, length: 10 },
): Promise<FetchDatasetColumnsResponse> {
  const { signal, ...queryParams } = options;

  const response = await http.get(`/dataset/${datasetId}/column`, {
    params: queryParams,
    signal,
  });

  const rows = response.data;
  const totalCountHeader = response.headers['x-total-count'];
  const totalCount = totalCountHeader ? parseInt(totalCountHeader, 10) : 0;

  return {
    rows,
    totalCount,
  };
}

export interface UpdateDatasetColumnRequest {
  heading: string;
  dataSetColumnTypeId: number;
  dataTypeId: number;
  listContent?: string;
  remoteField?: string;
  columnOrder?: number;
  tooltip?: string;
  formula?: string;
  showFilter?: boolean;
  dateFormat?: string;
  showSort?: boolean;
  isRequired?: boolean;
}

export async function createDatasetColumn(
  datasetId: string | number,
  data: UpdateDatasetColumnRequest,
): Promise<DatasetColumn> {
  const params = new URLSearchParams();

  Object.entries(data).forEach(([key, value]) => {
    if (value !== undefined && value !== null) {
      const stringValue = typeof value === 'boolean' ? (value ? '1' : '0') : String(value);
      params.append(key, stringValue);
    }
  });

  const response = await http.post(`/dataset/${datasetId}/column`, params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });

  return response.data;
}

export async function updateDatasetColumn(
  datasetId: string | number,
  columnId: string | number,
  data: UpdateDatasetColumnRequest,
): Promise<DatasetColumn> {
  const params = new URLSearchParams();

  Object.entries(data).forEach(([key, value]) => {
    if (value !== undefined && value !== null) {
      const stringValue = typeof value === 'boolean' ? (value ? '1' : '0') : String(value);
      params.append(key, stringValue);
    }
  });

  const response = await http.put(`/dataset/${datasetId}/column/${columnId}`, params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });

  return response.data;
}

export async function deleteDatasetColumn(
  datasetId: string | number,
  columnId: string | number,
): Promise<void> {
  await http.delete(`/dataset/${datasetId}/column/${columnId}`, {
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
    },
  });
}

/* Data */
export interface FetchDatasetDataRequest {
  start: number;
  length: number;
  sortBy?: string;
  sortDir?: string;
  signal?: AbortSignal;
}

export interface FetchDatasetDataResponse {
  rows: DynamicRowData[];
  totalCount: number;
}

export async function fetchDatasetData(
  datasetId: string | number,
  options: FetchDatasetDataRequest = { start: 0, length: 10 },
): Promise<FetchDatasetDataResponse> {
  const { signal, ...queryParams } = options;

  const response = await http.get(`/dataset/data/${datasetId}`, {
    params: queryParams,
    signal,
  });

  const rows = response.data;
  const totalCountHeader = response.headers['x-total-count'];
  const totalCount = totalCountHeader ? parseInt(totalCountHeader, 10) : 0;

  return {
    rows,
    totalCount,
  };
}

export async function createDatasetRow(
  datasetId: string | number,
  rowData: DynamicRowData,
): Promise<unknown> {
  const params = new URLSearchParams();

  Object.entries(rowData).forEach(([columnId, value]) => {
    if (value !== undefined && value !== null) {
      params.append(`dataSetColumnId_${columnId}`, String(value));
    }
  });

  const response = await http.post(`/dataset/data/${datasetId}`, params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });

  return response.data;
}

export async function updateDatasetRow(
  datasetId: string | number,
  rowId: string | number,
  rowData: DynamicRowData,
): Promise<unknown> {
  const params = new URLSearchParams();

  Object.entries(rowData).forEach(([columnId, value]) => {
    if (value !== undefined && value !== null) {
      params.append(`dataSetColumnId_${columnId}`, String(value));
    }
  });

  const response = await http.put(`/dataset/data/${datasetId}/${rowId}`, params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });

  return response.data;
}

export async function deleteDatasetRow(
  datasetId: string | number,
  rowId: string | number,
): Promise<void> {
  await http.delete(`/dataset/data/${datasetId}/${rowId}`, {
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
    },
  });
}

/* Rss */
export interface FetchDatasetRssRequest {
  start?: number;
  length?: number;
  sortBy?: string;
  sortDir?: string;
  useRegexForName?: boolean;
  signal?: AbortSignal;
}

export interface FetchDatasetRssResponse {
  rows: DatasetRss[];
  totalCount: number;
}

export interface DatasetRssPayload {
  title: string;
  author: string;
  titleColumnId?: number;
  summaryColumnId?: number;
  contentColumnId?: number;
  publishedDateColumnId?: number;

  sort?: string;
  useOrderingClause?: boolean;
  orderClause?: string[];
  orderClauseDirection?: string[];

  filter?: string;
  useFilteringClause?: boolean;
  filterClause?: string[];
  filterClauseOperator?: string[];
  filterClauseCriteria?: string[];
  filterClauseValue?: string[];

  regeneratePsk?: boolean;
}

function buildUrlParams(data: Partial<DatasetRssPayload>): URLSearchParams {
  const params = new URLSearchParams();

  Object.entries(data).forEach(([key, value]) => {
    if (value !== undefined && value !== null) {
      if (Array.isArray(value)) {
        if (value.length === 0) {
          params.append(`${key}[]`, '');
        } else {
          value.forEach((val) => params.append(`${key}[]`, String(val)));
        }
      } else if (typeof value === 'boolean') {
        params.append(key, value ? '1' : '0');
      } else {
        params.append(key, String(value));
      }
    }
  });

  const requiredArrays = [
    'orderClause',
    'orderClauseDirection',
    'filterClause',
    'filterClauseOperator',
    'filterClauseCriteria',
    'filterClauseValue',
  ];

  requiredArrays.forEach((arrKey) => {
    if (!data[arrKey as keyof DatasetRssPayload]) {
      params.append(`${arrKey}[]`, '');
    }
  });

  return params;
}

export async function fetchDatasetRss(
  datasetId: string | number,
  options: FetchDatasetRssRequest = { start: 0, length: 10 },
): Promise<FetchDatasetRssResponse> {
  const { signal, ...queryParams } = options;

  const response = await http.get(`/dataset/${datasetId}/rss`, {
    params: queryParams,
    signal,
  });

  const rows = response.data;
  const totalCountHeader = response.headers['x-total-count'];
  const totalCount = totalCountHeader ? parseInt(totalCountHeader, 10) : 0;

  return { rows, totalCount };
}

export async function createDatasetRss(
  datasetId: string | number,
  data: DatasetRssPayload,
): Promise<DatasetRss> {
  const params = buildUrlParams(data);

  const response = await http.post(`/dataset/${datasetId}/rss`, params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });

  return response.data;
}

export async function updateDatasetRss(
  datasetId: string | number,
  rssId: string | number,
  data: DatasetRssPayload,
): Promise<DatasetRss> {
  const params = buildUrlParams(data);

  // Corrected endpoint from /column to /rss
  const response = await http.put(`/dataset/${datasetId}/rss/${rssId}`, params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });

  return response.data;
}

export async function deleteDatasetRss(
  datasetId: string | number,
  rssId: string | number,
): Promise<void> {
  await http.delete(`/dataset/${datasetId}/rss/${rssId}`, {
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
    },
  });
}
