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

export interface LayoutOnCampaign {
  lkCampaignLayoutId: number;
  campaignId: number;
  layoutId: number;
  displayOrder: number;
  dayPartId: number | null;
  daysOfWeek: string | null;
  geoFence: string | Record<string, unknown> | null;
  layout: string;
  layoutCampaignId: number;
  ownerId: number;
  duration: number;
  dayPart: string | null;
}

export interface CampaignPermissions {
  view?: number;
  edit?: number;
  delete?: number;
  modifyPermissions?: number;
}

export interface Campaign {
  campaignId: number;
  ownerId: number;
  type: string;

  campaign: string;

  isLayoutSpecific: number;
  numberLayouts: number;
  totalDuration: number;

  tags: Tag[];

  folderId: number;
  permissionsFolderId: number;

  cyclePlaybackEnabled: number;
  playCount: number;
  listPlayOrder: 'block' | string;

  targetType: string | null;
  target: number;

  startDt: number;
  endDt: number;

  plays: number;
  spend: number;
  impressions: number;

  lastPopId: number | null;

  ref1: string | null;
  ref2: string | null;
  ref3: string | null;
  ref4: string | null;
  ref5: string | null;

  createdAt: string;
  modifiedAt: string;
  modifiedBy: number;
  modifiedByName: string;

  displayGroupIds: number[];

  retired: number;

  layouts?: LayoutOnCampaign[];

  userPermissions?: CampaignPermissions;
}
