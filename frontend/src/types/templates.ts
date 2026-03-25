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

  userPermissions: UserPermission;

  tags: Tag[];
}

export interface UserPermission {
  view?: number;
  edit?: number;
  delete?: number;
  modifyPermissions?: number;
}
