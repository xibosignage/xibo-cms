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
  tagsType: '',
  exactTags: false,
  logicalOperator: 'OR',
  groupBy: '',
  sortBy: '',
};

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
  { value: '', label: 'None' },
  { value: 'dg', label: 'Display Group' },
  { value: 'media', label: 'Media' },
  { value: 'layout', label: 'Layout' },
];

export const SORT_BY_OPTIONS = [
  { value: '', label: 'Default' },
  { value: 'widgetId', label: 'Widget ID' },
  { value: 'type', label: 'Type' },
  { value: 'display', label: 'Display' },
  { value: 'media', label: 'Media' },
  { value: 'layout', label: 'Layout' },
  { value: 'tag', label: 'Tag' },
];
