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

import { buildTag } from '@/pages/Administration/Tags/__tests__/fixtures/tag';
import type { FetchTagsResponse } from '@/services/tagApi';
import type { Tag } from '@/types/tag';

// -----------------------------------------------------------------------------
// Tags reuse the Administration › Tags factory. Its defaults (tag 'location',
// value '', isRequired 0, options null) are TagInput's "plain tag" branch;
// tests name every tag they render, so the default name never shows up.
// Used both as TagInput's value (chips) and as fetchTags rows (suggestions and
// the value-entry lookup).
// -----------------------------------------------------------------------------
export { buildTag };

// Suggestions for the term "lo" — three, so arrow-key wrapping is observable.
export const mockLoungeTag = buildTag({ tagId: 2, tag: 'lounge' });
// Required tag with a fixed list of values (options is a JSON string from the API).
export const mockLocationTag = buildTag({
  tagId: 3,
  tag: 'location',
  isRequired: 1,
  options: '["north","south"]',
});

export const suggestionTags: Tag[] = [
  buildTag({ tagId: 1, tag: 'lobby' }),
  mockLoungeTag,
  mockLocationTag,
];

export const EMPTY_TAG_TABLE: FetchTagsResponse = {
  rows: [],
  totalCount: 0,
};

export const SUGGESTION_TAGS: FetchTagsResponse = {
  rows: suggestionTags,
  totalCount: suggestionTags.length,
};
