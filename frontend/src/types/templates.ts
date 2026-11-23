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

import type { Tag } from './tag';

export interface Template {
  layoutId: number;
  campaignId: number;
  parentId: number | null;
  backgroundImageId: number | null;

  layout: string;
  description: string;
  backgroundColor: string;
  schemaVersion: number;
  code: string | null;
  thumbnail?: string;

  publishedStatusId: number;
  publishedStatus: 'Draft' | 'Published' | 'Pending Approval';
  publishedDate: string | null;
  status: number;
  retired: number;
  isLocked: boolean | null;

  width: number;
  height: number;
  orientation: 'landscape' | 'portrait' | 'square';
  backgroundzIndex: number;

  ownerId: number;
  owner: string;
  groupsWithPermissions: string | null;
  groupsWithPermissionsList?: string[] | null;
  folderId: number;
  permissionsFolderId: number;

  duration: number;
  enableStat: number;
  displayOrder: number | null;
  autoApplyTransitions: number;
  statusMessage: string | null;

  createdDt: string;
  modifiedDt: string;
  resolutionId: number;

  userPermissions?: UserPermission;

  tags: Tag[];
}

export interface UserPermission {
  view?: number;
  edit?: number;
  delete?: number;
  modifyPermissions?: number;
}
