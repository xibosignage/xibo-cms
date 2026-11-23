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

import axios from 'axios';

export interface ProofOfPlayRequest {
  reportFilter?: string;
  statsFromDt?: string;
  statsToDt?: string;
  statsFromDtTime?: string;
  statsToDtTime?: string;
  type?: string;
  layoutId?: number[];
  mediaId?: number[];
  displayId?: number | null;
  displayGroupId?: number[];
  parentCampaignId?: number | null;
  tags?: string;
  tagsType?: string;
  exactTags?: boolean;
  logicalOperator?: string;
  groupBy?: string;
  sortBy?: string;
  signal?: AbortSignal;
}

export interface ProofOfPlayRow {
  type: string;
  displayId: number;
  display: string;
  layoutId: number;
  layout: string;
  parentCampaignId: number;
  parentCampaign: string;
  widgetId: number;
  media: string;
  tag: string;
  numberPlays: number;
  duration: number;
  minStart: string;
  maxEnd: string;
  mediaId: number;
  displayGroup?: string;
  displayGroupId?: number;
  tagName?: string;
  tagId?: number;
}

export interface ProofOfPlayResponse {
  metadata: {
    periodStart: string;
    periodEnd: string;
  };
  table: ProofOfPlayRow[];
  recordsTotal: number;
  error?: string | null;
}

export async function fetchProofOfPlay(req: ProofOfPlayRequest): Promise<ProofOfPlayResponse> {
  const params = new URLSearchParams();

  if (req.reportFilter) {
    params.append('reportFilter', req.reportFilter);
  } else {
    if (req.statsFromDt) {
      params.append('statsFromDt', req.statsFromDt);
    }
    if (req.statsToDt) {
      params.append('statsToDt', req.statsToDt);
    }
    if (req.statsFromDtTime) {
      params.append('statsFromDtTime', req.statsFromDtTime);
    }
    if (req.statsToDtTime) {
      params.append('statsToDtTime', req.statsToDtTime);
    }
  }

  if (req.type) {
    params.append('type', req.type);
  }
  req.layoutId?.forEach((id) => params.append('layoutId[]', String(id)));
  req.mediaId?.forEach((id) => params.append('mediaId[]', String(id)));

  if (req.displayId) {
    params.append('displayId', String(req.displayId));
  }
  req.displayGroupId?.forEach((id) => params.append('displayGroupId[]', String(id)));

  if (req.parentCampaignId) {
    params.append('parentCampaignId', String(req.parentCampaignId));
  }
  if (req.tags) {
    params.append('tags', req.tags);
  }
  if (req.tagsType) {
    params.append('tagsType', req.tagsType);
  }
  params.append('exactTags', req.exactTags ? '1' : '0');
  if (req.logicalOperator) {
    params.append('logicalOperator', req.logicalOperator);
  }
  if (req.groupBy) {
    params.append('groupBy', req.groupBy);
  }
  if (req.sortBy) {
    params.append('sortBy', req.sortBy);
  }

  const response = await axios.get<ProofOfPlayResponse>('/report/data/proofofplayReport', {
    params,
    signal: req.signal,
    withCredentials: true,
  });

  return response.data;
}
