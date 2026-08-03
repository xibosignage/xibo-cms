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

const tagKey = (tag: Tag): string =>
  tag.value != null && tag.value !== '' ? `${tag.tag}|${tag.value}` : tag.tag;

export function formatTagsForExport(tags?: Tag[] | null): string {
  return (tags ?? []).map(tagKey).join(', ');
}

export function mergeEntityTags(items: Array<{ tags?: Tag[] | null }>): Tag[] {
  return Array.from(
    new Map(items.flatMap((item) => item.tags ?? []).map((tag) => [tagKey(tag), tag])).values(),
  );
}
