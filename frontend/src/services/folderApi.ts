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
import type { Folder } from '@/types/folder';

export interface FolderPermissions {
  create?: boolean;
  modify?: boolean;
  delete?: boolean;
  share?: boolean;
  move?: boolean;
}

export interface CreateFolderRequest {
  text: string;
  parentId?: number;
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

export async function fetchFolderTree(signal?: AbortSignal): Promise<Folder[]> {
  const response = await http.get<Folder[]>('/folders', { signal });
  return response.data;
}

export async function fetchFolderById(id: number, signal?: AbortSignal): Promise<Folder> {
  const response = await http.get<Folder>(`/folders/${id}`, {
    signal,
  });
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

export async function createFolder(data: CreateFolderRequest): Promise<Folder> {
  const formData = new FormData();
  formData.append('text', data.text);
  if (data.parentId) {
    formData.append('parentId', data.parentId.toString());
  }

  const response = await http.post<Folder>('/folders', formData);
  return response.data;
}

export async function editFolder(data: EditFolderRequest): Promise<Folder> {
  const formData = new URLSearchParams();
  formData.append('text', data.text);

  const response = await http.put<Folder>(`/folders/${data.id}`, formData);
  return response.data;
}

export async function deleteFolder(folderId: number): Promise<void> {
  await http.delete(`/folders/${folderId}`);
}

export async function moveFolder(data: MoveFolderRequest): Promise<void> {
  const formData = new URLSearchParams();
  formData.append('folderId', data.targetId.toString());
  if (data.merge) {
    formData.append('merge', '1');
  }

  await http.put(`/folders/${data.id}/move`, formData);
}
