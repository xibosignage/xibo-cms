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

import axiosLib from 'axios';

import http from '@/lib/api';
import type { Notification } from '@/types/notification';

export interface FetchNotificationRequest {
  start: number;
  length: number;
  read?: number | null;
  type?: string;
  releaseDt?: string;
  sortBy?: string;
  sortDir?: string;
  signal?: AbortSignal;
}

export interface FetchNotificationResponse {
  rows: Notification[];
  totalCount: number;
  unreadCount: number;
}

export async function fetchNotifications(
  options: FetchNotificationRequest = { start: 0, length: 10 },
): Promise<FetchNotificationResponse> {
  const { signal, ...queryParams } = options;

  const cleanedParams = Object.fromEntries(
    Object.entries(queryParams).filter(([, v]) => v !== null && v !== undefined && v !== ''),
  );

  const response = await http.get('/notification', {
    params: cleanedParams,
    signal,
  });

  const rows = response.data;
  const totalCountHeader = response.headers['x-total-count'];
  const totalCount = totalCountHeader ? parseInt(totalCountHeader, 10) : 0;

  return { rows, totalCount, unreadCount: 0 };
}

export interface CreateNotificationRequest {
  subject: string;
  releaseDt: string;
  isInterrupt: number;
  body: string;
  userGroupIds?: string[];
  displayGroupIds?: string[];
  nonusers?: string;
  attachedFilename?: string;
  clearAttachment?: boolean;
}

export async function createNotification(data: CreateNotificationRequest): Promise<void> {
  const params = new URLSearchParams();
  params.append('subject', data.subject);
  params.append('releaseDt', data.releaseDt);
  params.append('isInterrupt', String(data.isInterrupt));
  params.append('body', data.body);

  (data.userGroupIds ?? []).forEach((id) => params.append('userGroupIds[]', id));
  (data.displayGroupIds ?? []).forEach((id) => params.append('displayGroupIds[]', id));

  if (data.nonusers) params.append('nonusers', data.nonusers);
  if (data.attachedFilename) params.append('attachedFilename', data.attachedFilename);

  await http.post('/notification', params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });
}

export async function uploadNotificationAttachment(file: File): Promise<string> {
  const formData = new FormData();
  formData.append('files', file, file.name);

  // Use raw axiosLib (not the http instance) so the default Content-Type: application/json
  // is not applied — axios would otherwise JSON-serialize FormData, emptying $_FILES in PHP.
  const response = await axiosLib.post('/notification/attachment', formData, {
    withCredentials: true,
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  });

  const files = response.data?.files ?? [];
  const uploaded = files[0];
  if (!uploaded?.name) throw new Error('Upload failed: no filename returned');
  return uploaded.name as string;
}

export async function deleteNotification(notificationId: number | string): Promise<void> {
  await http.delete(`/notification/${notificationId}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  });
}

export async function markAllNotificationsRead(notificationId?: number): Promise<void> {
  const body =
    notificationId != null
      ? new URLSearchParams({ notificationId: String(notificationId) }).toString()
      : null;
  await http.put('/notification/markAsRead', body, {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });
}

export async function fetchNotificationById(id: number): Promise<Notification> {
  const response = await http.get<Notification | Notification[]>(`/notification/${id}`, {
    params: { embed: 'userGroups,displayGroups' },
  });
  const data = response.data;
  if (Array.isArray(data)) {
    if (!data[0]) throw new Error('Notification not found');
    return data[0];
  }
  return data;
}

export async function fetchMyNotifications(
  options: { start?: number; length?: number; signal?: AbortSignal } = {},
): Promise<FetchNotificationResponse> {
  const { signal, start = 0, length = 20 } = options;
  const response = await http.get('/notification/mynotifications', {
    params: { start, length },
    signal,
  });
  const rows = response.data;
  const totalCountHeader = response.headers['x-total-count'];
  const unreadCountHeader = response.headers['x-unread-count'];
  const totalCount = totalCountHeader ? parseInt(totalCountHeader, 10) : rows.length;
  const unreadCount = unreadCountHeader ? parseInt(unreadCountHeader, 10) : 0;
  return { rows, totalCount, unreadCount };
}

export async function fetchInterruptNotifications(): Promise<Notification[]> {
  const response = await http.get<Notification[]>('/notification/interrupt', {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  });
  return response.data;
}

export async function updateNotification(
  id: number,
  data: CreateNotificationRequest,
): Promise<void> {
  const params = new URLSearchParams();
  params.append('subject', data.subject);
  params.append('releaseDt', data.releaseDt);
  params.append('isInterrupt', String(data.isInterrupt));
  params.append('body', data.body);
  (data.userGroupIds ?? []).forEach((gid) => params.append('userGroupIds[]', gid));
  (data.displayGroupIds ?? []).forEach((gid) => params.append('displayGroupIds[]', gid));
  if (data.nonusers) params.append('nonusers', data.nonusers);
  if (data.attachedFilename) params.append('attachedFilename', data.attachedFilename);
  if (data.clearAttachment) params.append('clearAttachment', '1');

  await http.put(`/notification/${id}`, params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });
}
