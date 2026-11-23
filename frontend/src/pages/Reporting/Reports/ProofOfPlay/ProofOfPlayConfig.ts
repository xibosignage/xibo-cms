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

export type ProofOfPlayFilter = {
  reportFilter: string;
  type: string;
  layoutId: number[];
  mediaId: number[];
  displayId: number | null;
  displayGroupId: number[];
  parentCampaignId: number | null;
  tags: Tag[];
  tagsType: string;
  exactTags: boolean;
  logicalOperator: string;
  groupBy: string;
  sortBy: string;
};

export const INITIAL_FILTER_STATE: ProofOfPlayFilter = {
  reportFilter: 'lastweek',
  type: '',
  layoutId: [],
  mediaId: [],
  displayId: null,
  displayGroupId: [],
  parentCampaignId: null,
  tags: [],
  tagsType: 'dg',
  exactTags: false,
  logicalOperator: 'OR',
  groupBy: '',
  sortBy: 'widgetId',
};

export const ACTIVE_FILTER_KEYS: (keyof ProofOfPlayFilter)[] = [
  'reportFilter',
  'type',
  'layoutId',
  'mediaId',
  'displayId',
  'displayGroupId',
  'parentCampaignId',
  'tags',
];

export const DATE_RANGE_OPTIONS = [
  { value: 'today', label: 'Today' },
  { value: 'yesterday', label: 'Yesterday' },
  { value: 'thisweek', label: 'This Week' },
  { value: 'thismonth', label: 'This Month' },
  { value: 'thisyear', label: 'This Year' },
  { value: 'lastweek', label: 'Last Week' },
  { value: 'lastmonth', label: 'Last Month' },
  { value: 'lastyear', label: 'Last Year' },
];

export const TYPE_OPTIONS = [
  { value: '', label: 'All' },
  { value: 'layout', label: 'Layout' },
  { value: 'media', label: 'Media' },
  { value: 'widget', label: 'Widget' },
  { value: 'event', label: 'Event' },
];

export const GROUP_BY_OPTIONS = [
  { value: '', label: 'None' },
  { value: 'display', label: 'Display' },
  { value: 'displayGroup', label: 'Display Group' },
  { value: 'tag', label: 'Tag' },
];

export const TAGS_TYPE_OPTIONS = [
  { value: 'dg', label: 'Display Group' },
  { value: 'media', label: 'Media' },
  { value: 'layout', label: 'Layout' },
];

export const SORT_BY_OPTIONS = [
  { value: 'widgetId', label: 'Widget ID' },
  { value: 'type', label: 'Type' },
  { value: 'display', label: 'Display' },
  { value: 'displayId', label: 'Display ID' },
  { value: 'media', label: 'Media' },
  { value: 'layout', label: 'Layout' },
  { value: 'layoutId', label: 'Layout ID' },
  { value: 'tag', label: 'Tag' },
];
