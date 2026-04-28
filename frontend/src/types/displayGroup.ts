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

import type { Tag } from '@/types/tag';

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
  createdDt: string;
  modifiedDt: string;
  folderId: number;
  permissionsFolderId: number;
  ref1: string;
  ref2: string;
  ref3: string;
  ref4: string;
  ref5: string;
}
