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
import type { Task, TaskAvailable } from '@/types/task';

export interface FetchTaskRequest {
  start?: number;
  length?: number;
  name?: string;
  logicalOperatorName?: 'AND' | 'OR';
  useRegexForName?: boolean;
  sortBy?: string;
  sortDir?: string;
  signal?: AbortSignal;
}

export interface FetchTaskResponse {
  rows: Task[];
  totalCount: number;
}

function normalizeTaskOptions(task: Task): Task {
  if (Array.isArray(task.options)) {
    task.options = {};
  }
  return task;
}

export async function fetchTasks(options: FetchTaskRequest = {}): Promise<FetchTaskResponse> {
  const { signal, ...queryParams } = options;

  const response = await http.get('/task', {
    params: queryParams,
    signal,
  });

  const rows = (response.data as Task[]).map(normalizeTaskOptions);
  const totalCountHeader = response.headers['x-total-count'];
  const totalCount = totalCountHeader ? parseInt(totalCountHeader, 10) : rows.length;

  return { rows, totalCount };
}

export async function fetchTaskById(id: number, signal?: AbortSignal): Promise<Task> {
  const { data } = await http.get(`/task/${id}`, { signal });
  return normalizeTaskOptions(data);
}

export async function fetchAvailableTasks(signal?: AbortSignal): Promise<TaskAvailable[]> {
  const { data } = await http.get('/task/list', { signal });
  return data.tasksAvailable;
}

export async function createTask(params: {
  name: string;
  file: string;
  schedule: string;
}): Promise<Task> {
  const formData = new URLSearchParams();
  formData.append('name', params.name);
  formData.append('file', params.file);
  formData.append('schedule', params.schedule);

  const response = await http.post('/task', formData, {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });

  return response.data;
}

export interface UpdateTaskPayload {
  name: string;
  schedule: string;
  isActive: number;
  options: Record<string, string>;
}

export async function updateTask(id: number, payload: UpdateTaskPayload): Promise<Task> {
  const params = new URLSearchParams();
  params.append('name', payload.name);
  params.append('schedule', payload.schedule);
  params.append('isActive', String(payload.isActive));

  for (const [key, value] of Object.entries(payload.options)) {
    params.append(key, String(value));
  }

  const { data } = await http.put(`/task/${id}`, params.toString(), {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });
  return data;
}

export async function deleteTask(id: number): Promise<void> {
  await http.delete(`/task/${id}`);
}

export async function runTaskNow(id: number): Promise<void> {
  await http.post(`/task/${id}/run`);
}
