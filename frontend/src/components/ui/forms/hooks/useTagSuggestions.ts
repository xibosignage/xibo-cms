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

import { keepPreviousData, useQuery } from '@tanstack/react-query';

import { useDebounce } from '@/hooks/useDebounce';
import { fetchTags } from '@/services/tagApi';
import type { Tag } from '@/types/tag';

const SUGGESTION_LIMIT = 10;

export function useTagSuggestions(term: string, enabled: boolean) {
  const debounced = useDebounce(term, 300);
  const trimmed = debounced.trim();

  const query = useQuery({
    queryKey: ['tagSuggestions', trimmed],
    queryFn: ({ signal }) =>
      fetchTags({ tag: trimmed, allTags: 1, length: SUGGESTION_LIMIT, signal }),
    enabled: enabled && trimmed.length > 0,
    placeholderData: keepPreviousData,
    staleTime: 1000 * 60,
  });

  const suggestions: Tag[] = query.data?.rows ?? [];

  return { suggestions, isLoading: query.isFetching };
}
