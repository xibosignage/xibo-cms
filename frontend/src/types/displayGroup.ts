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

import type { Tag } from '@/types/tag';

export interface DisplayGroupPermissions {
  view?: number;
  edit?: number;
  delete?: number;
  modifyPermissions?: number;
}

export interface DisplayGroup {
  displayGroupId: number;
  displayGroup: string;
  description: string;
  isDisplaySpecific: number;
  isDynamic: number;
  dynamicCriteria: string;
  dynamicCriteriaLogicalOperator: string;
  dynamicCriteriaTags: string;
  dynamicCriteriaExactTags: number;
  dynamicCriteriaTagsLogicalOperator: string;
  userId: number;
  tags: Tag[];
  bandwidthLimit: number;
  groupsWithPermissions: string;
  groupsWithPermissionsList?: string[] | null;
  createdDt: string;
  modifiedDt: string;
  folderId: number;
  permissionsFolderId: number;
  ref1: string;
  ref2: string;
  ref3: string;
  ref4: string;
  ref5: string;
  userPermissions?: DisplayGroupPermissions;
}
