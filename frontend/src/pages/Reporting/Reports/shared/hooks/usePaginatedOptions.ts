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
