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

import { useEffect, useRef, useState } from 'react';

import type { SelectOption } from '@/components/ui/forms/SelectDropdown';

export type OptionsLoader = (
  search: string,
  start: number,
  signal?: AbortSignal,
) => Promise<{ options: SelectOption[]; totalCount: number }>;

interface UsePaginatedOptionsArgs {
  loader: OptionsLoader;
  enabled?: boolean;
  resetKey?: string;
}

export function usePaginatedOptions({
  loader,
  enabled = true,
  resetKey = '',
}: UsePaginatedOptionsArgs) {
  const [options, setOptions] = useState<SelectOption[]>([]);
  const [searchTerm, setSearchTerm] = useState('');
  const [start, setStart] = useState(0);
  const [hasMore, setHasMore] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [isLoadingMore, setIsLoadingMore] = useState(false);

  const loaderRef = useRef(loader);
  loaderRef.current = loader;

  const abortRef = useRef<AbortController | null>(null);

  const load = async (search: string, startAt: number, append: boolean) => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    if (startAt === 0) {
      setIsLoading(true);
    } else {
      setIsLoadingMore(true);
    }
    try {
      const result = await loaderRef.current(search, startAt, controller.signal);
      if (controller.signal.aborted) {
        return;
      }
      setOptions((prev) => (append ? [...prev, ...result.options] : result.options));
      const loaded = startAt + result.options.length;
      setHasMore(loaded < result.totalCount);
      setStart(loaded);
    } catch {
      // Silently ignore load errors for option lists.
    } finally {
      if (!controller.signal.aborted) {
        setIsLoading(false);
        setIsLoadingMore(false);
      }
    }
  };

  useEffect(() => {
    if (!enabled) {
      setOptions([]);
      return;
    }
    setSearchTerm('');
    void load('', 0, false);
    return () => abortRef.current?.abort();
  }, [enabled, resetKey]);

  const onSearch = (term: string) => {
    setSearchTerm(term);
    void load(term, 0, false);
  };

  const onLoadMore = () => {
    void load(searchTerm, start, true);
  };

  return { options, isLoading, isLoadingMore, hasMore, onSearch, onLoadMore };
}
