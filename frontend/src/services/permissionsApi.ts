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

export interface PermissionEntry {
  permissionId: number;
  entityId: number;
  groupId: number;
  objectId: number;
  isUser: number | null;
  entity: string;
  group: string;
  view: number;
  edit: number;
  delete: number;
  isUserSpecific: number;
}

interface MultiPermissionItem {
  groupId: number;
  group: string;
  isUser: number | null;
  entity: string;
  isUserSpecific?: number;
  permissions: Record<
    string,
    {
      permissionId: number;
      view: number;
      edit: number;
      delete: number;
    }
  >;
}

export interface PermissionSet {
  view: number;
  edit: number;
  delete: number;
}

export type GroupPermissionsMap = Record<number | string, PermissionSet>;

export type UserType = 'all' | 'user' | 'group';

// Fetch request/response interfaces
export interface FetchPermissionsRequest {
  entity: string;
  id: number | string;
  start?: number;
  length?: number;
  name?: string;
  type?: UserType;
  signal?: AbortSignal;
}

export interface FetchMultiPermissionsRequest {
  entity: string;
  ids: (number | string)[];
  name?: string;
  type?: UserType;
  start?: number;
  length?: number;
  signal?: AbortSignal;
}

export interface FetchPermissionsResponse {
  rows: PermissionEntry[];
  totalCount: number;
}

// Save request interfaces
export interface SavePermissionsRequest {
  entity: string;
  id: number | string;
  groupIds: GroupPermissionsMap;
  ownerId?: number | string;
}

export interface SaveMultiPermissionsRequest {
  entity: string;
  ids: (number | string)[];
  groupIds: GroupPermissionsMap;
  ownerId?: number | string;
}

const buildFetchParams = (options: {
  start?: number;
  length?: number;
  name?: string;
  type?: UserType;
  ids?: string;
}) => {
  const params: Record<string, string | number> = {
    start: options.start ?? 0,
    length: options.length ?? 10,
  };
  if (options.name) {
    params.name = options.name;
  }

  if (options.ids) {
    params.ids = options.ids;
  }

  if (options.type === 'user') {
    params.isUserSpecific = 1;
  } else if (options.type === 'group') {
    params.isUserSpecific = 0;
  }

  return params;
};

export async function fetchPermissions({
  entity,
  id,
  start,
  length,
  name,
  type,
  signal,
}: FetchPermissionsRequest): Promise<FetchPermissionsResponse> {
  const response = await http.get<PermissionEntry[]>(`/user/permissions/${entity}/${id}`, {
    params: buildFetchParams({ start, length, name, type }),
    signal,
  });

  const totalCountHeader = response.headers['x-total-count'];
  const totalCount = totalCountHeader ? parseInt(totalCountHeader, 10) : 0;

  return {
    rows: response.data,
    totalCount,
  };
}

export async function fetchMultiPermissions({
  entity,
  ids,
  name,
  start,
  length,
  signal,
}: FetchMultiPermissionsRequest): Promise<FetchPermissionsResponse> {
  const response = await http.get<Record<string, MultiPermissionItem>>(
    `/user/permissions/${entity}`,
    {
      params: buildFetchParams({ ids: ids.join(','), name, start, length }),
      signal,
    },
  );

  const totalCountHeader = response.headers['x-total-count'];
  const totalCount = totalCountHeader ? parseInt(totalCountHeader, 10) : 0;

  // Helper to determine mixed state
  const calculateState = (values: number[]): number => {
    if (values.length === 0) return 0;
    const hasTrue = values.some((v) => v === 1);
    const hasFalse = values.some((v) => v === 0);

    if (hasTrue && hasFalse) {
      // Mixed
      return -1;
    }

    if (hasTrue) {
      // True
      return 1;
    }

    // False
    return 0;
  };

  // Flatten into an array
  const rows: PermissionEntry[] = Object.values(response.data).map((item) => {
    const perms = Object.values(item.permissions || {});

    // Permission is true if all elements have it as true
    // Mixed if some have true and some false
    // False if all false
    const viewState = calculateState(perms.map((p) => p.view));
    const editState = calculateState(perms.map((p) => p.edit));
    const deleteState = calculateState(perms.map((p) => p.delete));

    return {
      permissionId: 0,
      entityId: 0,
      groupId: item.groupId,
      objectId: 0,
      isUser: item.isUser,
      entity: item.entity,
      group: item.group,
      view: viewState,
      edit: editState,
      delete: deleteState,
    } as PermissionEntry;
  });

  return {
    rows,
    totalCount,
  };
}

function buildPermissionParams(
  groupIds: GroupPermissionsMap,
  ownerId?: number | string,
  ids?: (number | string)[],
): URLSearchParams {
  const params = new URLSearchParams();

  if (ids && ids.length > 0) {
    params.append('ids', ids.join(','));
  }

  if (ownerId !== undefined && ownerId !== null) {
    params.append('ownerId', ownerId.toString());
  }

  // Skip entries if permissions are not set ( -1 )
  Object.entries(groupIds).forEach(([id, perms]) => {
    if (perms.view !== -1) {
      params.append(`groupIds[${id}][view]`, perms.view.toString());
    }
    if (perms.edit !== -1) {
      params.append(`groupIds[${id}][edit]`, perms.edit.toString());
    }
    if (perms.delete !== -1) {
      params.append(`groupIds[${id}][delete]`, perms.delete.toString());
    }
  });

  return params;
}

export async function savePermissions({
  entity,
  id,
  groupIds,
  ownerId,
}: SavePermissionsRequest): Promise<void> {
  const params = buildPermissionParams(groupIds, ownerId);

  await http.post(`/user/permissions/${entity}/${id}`, params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });
}

export async function saveMultiPermissions({
  entity,
  ids,
  groupIds,
  ownerId,
}: SaveMultiPermissionsRequest): Promise<void> {
  const params = buildPermissionParams(groupIds, ownerId, ids);

  await http.post(`/user/permissions/${entity}`, params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });
}
