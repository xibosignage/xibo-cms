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
}
