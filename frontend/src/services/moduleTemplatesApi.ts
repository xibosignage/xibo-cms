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

import http from '@/lib/api';
import type { DataType, ModuleTemplate, ModuleTemplateProperty } from '@/types/moduleTemplates';

export interface FetchModuleTemplatesRequest {
  start: number;
  length: number;
  sortBy?: string;
  sortDir?: string;
  signal?: AbortSignal;
  id?: number | null;
  templateId?: string | null;
  dataType?: string | null;
  keyword?: string;
}

export interface FetchModuleTemplatesResponse {
  rows: ModuleTemplate[];
  totalCount: number;
}

export async function fetchModuleTemplates(
  params: FetchModuleTemplatesRequest,
): Promise<FetchModuleTemplatesResponse> {
  const { signal, ...rest } = params;
  const response = await http.get('/developer/template', {
    params: {
      draw: 1,
      ...rest,
    },
    signal,
  });
  return {
    rows: response.data.data ?? [],
    totalCount: response.data.recordsTotal ?? 0,
  };
}

export async function fetchModuleTemplate(id: number): Promise<ModuleTemplate> {
  const response = await http.get(`/developer/template/${id}`);
  return response.data.data;
}

export async function fetchDataTypes(): Promise<DataType[]> {
  const response = await http.get('/developer/template/datatypes');
  return response.data.data ?? [];
}

export async function fetchTemplatesByDataType(
  dataType: string,
): Promise<Array<{ templateId: string; title: string }>> {
  const response = await http.get(`/module/templates/${encodeURIComponent(dataType)}`);
  return response.data ?? [];
}

export interface AddModuleTemplateRequest {
  templateId: string;
  title: string;
  dataType: string;
  showIn: string;
  copyTemplateId?: string;
}

export async function addModuleTemplate(params: AddModuleTemplateRequest): Promise<ModuleTemplate> {
  const formData = new URLSearchParams();
  formData.append('templateId', params.templateId);
  formData.append('title', params.title);
  formData.append('dataType', params.dataType);
  formData.append('showIn', params.showIn);
  if (params.copyTemplateId) {
    formData.append('copyTemplateId', params.copyTemplateId);
  }
  const response = await http.post('/developer/template', formData, {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });
  return response.data.data;
}

export interface EditModuleTemplateRequest {
  templateId: string;
  title: string;
  dataType: string;
  showIn: string;
  enabled: boolean;
  twig: string;
  hbs: string;
  style: string;
  head: string;
  onTemplateRender: string;
  onTemplateVisible: string;
  properties: ModuleTemplateProperty[];
  isInvalidateWidget: boolean;
}

export async function editModuleTemplate(
  id: number,
  params: EditModuleTemplateRequest,
): Promise<void> {
  const formData = new URLSearchParams();
  formData.append('templateId', params.templateId);
  formData.append('title', params.title);
  formData.append('dataType', params.dataType);
  formData.append('showIn', params.showIn);
  formData.append('enabled', params.enabled ? '1' : '0');
  formData.append('twig', params.twig);
  formData.append('hbs', params.hbs);
  formData.append('style', params.style);
  formData.append('head', params.head);
  formData.append('onTemplateRender', params.onTemplateRender);
  formData.append('onTemplateVisible', params.onTemplateVisible);
  formData.append('properties', JSON.stringify(params.properties));
  formData.append('isInvalidateWidget', params.isInvalidateWidget ? '1' : '0');
  await http.put(`/developer/template/${id}`, formData, {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });
}

export async function deleteModuleTemplate(id: number): Promise<void> {
  await http.delete(`/developer/template/${id}`);
}

export interface CopyModuleTemplateRequest {
  templateId: string;
}

export async function copyModuleTemplate(
  id: number,
  params: CopyModuleTemplateRequest,
): Promise<ModuleTemplate> {
  const formData = new URLSearchParams();
  formData.append('templateId', params.templateId);
  const response = await http.post(`/developer/template/${id}/copy`, formData, {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });
  return response.data.data;
}

export async function importModuleTemplateXml(file: File): Promise<void> {
  const formData = new FormData();
  formData.append('files[]', file);
  await http.post('/developer/template/import', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
}
