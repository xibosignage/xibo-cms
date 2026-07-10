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
