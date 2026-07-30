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

import { isAxiosError } from 'axios';

import http from '@/lib/api';
import type { Folder } from '@/types/folder';

export type ApiResult<T = void> =
  | { success: true; data: T }
  | { success: false; error: string; status?: number };

export interface FolderPermissions {
  create?: boolean;
  modify?: boolean;
  delete?: boolean;
  share?: boolean;
  move?: boolean;
}

export interface CreateFolderRequest {
  folderName: string;
  parentId: number;
}

export interface EditFolderRequest {
  id: number;
  text: string;
}

export interface MoveFolderRequest {
  id: number;
  targetId: number;
  merge?: boolean;
}

export interface SelectFolderRequest {
  targetType: string;
  targetId: number;
  folderId?: number;
}

function handleApiError(error: unknown): { success: false; error: string; status?: number } {
  console.error('API Error:', error);

  let errorMessage = 'An unknown error occurred';
  let status: number | undefined;

  if (isAxiosError(error)) {
    status = error.response?.status;
    errorMessage = error.response?.data?.message || error.message;
  } else if (error instanceof Error) {
    errorMessage = error.message;
  }

  return {
    success: false,
    error: errorMessage,
    status,
  };
}

export async function fetchFolderTree(signal?: AbortSignal): Promise<Folder[]> {
  const response = await http.get<Folder[]>('/folders', { signal });
  return response.data;
}

export async function fetchFolderById(id: number, signal?: AbortSignal): Promise<Folder> {
  const response = await http.get<Folder>(`/folders/${id}`, { signal });
  return response.data;
}

export async function searchFolders(query: string, signal?: AbortSignal): Promise<Folder[]> {
  const response = await http.get<Folder[]>('/folders', {
    params: { folderName: query },
    signal,
  });
  return response.data;
}

export async function fetchContextButtons(
  folderId: number,
  signal?: AbortSignal,
): Promise<FolderPermissions> {
  const response = await http.get<FolderPermissions>(`/folders/contextButtons/${folderId}`, {
    signal,
  });
  return response.data;
}

export async function createFolder(data: CreateFolderRequest): Promise<ApiResult<Folder>> {
  try {
    const formData = new FormData();
    formData.append('parentId', data.parentId.toString());
    formData.append('text', data.folderName);

    const response = await http.post<Folder>('/folders', formData);

    return { success: true, data: response.data };
  } catch (error) {
    return handleApiError(error);
  }
}

export async function editFolder(data: EditFolderRequest): Promise<ApiResult<Folder>> {
  try {
    const payload = {
      text: data.text,
    };

    const response = await http.put<Folder>(`/folders/${data.id}`, payload);
    return { success: true, data: response.data };
  } catch (error) {
    return handleApiError(error);
  }
}

export async function deleteFolder(folderId: number): Promise<ApiResult> {
  try {
    await http.delete(`/folders/${folderId}`);
    return { success: true, data: undefined };
  } catch (error) {
    return handleApiError(error);
  }
}

export async function moveFolder(data: MoveFolderRequest): Promise<ApiResult> {
  try {
    const payload = {
      folderId: data.targetId,
      merge: data.merge ? 1 : 0,
    };

    await http.put(`/folders/${data.id}/move`, payload);
    return { success: true, data: undefined };
  } catch (error) {
    return handleApiError(error);
  }
}

export async function selectFolder(data: SelectFolderRequest): Promise<ApiResult> {
  try {
    const payload = {
      folderId: data.folderId,
    };

    await http.put(`/${data.targetType}/${data.targetId}/selectfolder`, payload);
    return { success: true, data: undefined };
  } catch (error) {
    return handleApiError(error);
  }
}
