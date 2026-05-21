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
import { useTranslation } from 'react-i18next';

import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import type { SelectOption } from '@/components/ui/forms/SelectDropdown';
import { useDebounce } from '@/hooks/useDebounce';
import { fetchPlaylistsForDashboard } from '@/services/dashboardApi';

const PAGE_SIZE = 10;

interface PlaylistDropdownProps {
  value: number | null;
  onSelect: (playlistId: number | null) => void;
}

export default function PlaylistDropdown({ value, onSelect }: PlaylistDropdownProps) {
  const { t } = useTranslation();

  const [options, setOptions] = useState<SelectOption[]>([]);
  const [totalCount, setTotalCount] = useState(0);
  const [isLoading, setIsLoading] = useState(false);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const debouncedSearch = useDebounce(searchTerm, 300);
  const pageRef = useRef(0);

  useEffect(() => {
    setIsLoading(true);
    setOptions([]);
    pageRef.current = 0;

    fetchPlaylistsForDashboard({
      start: 0,
      length: PAGE_SIZE,
      name: debouncedSearch || undefined,
    })
      .then((res) => {
        setOptions(res.rows.map((p) => ({ value: String(p.playlistId), label: p.name })));
        setTotalCount(res.totalCount);
        pageRef.current = 1;
      })
      .catch(() => setOptions([]))
      .finally(() => setIsLoading(false));
  }, [debouncedSearch]);

  const handleLoadMore = () => {
    if (isLoadingMore || options.length >= totalCount) {
      return;
    }

    setIsLoadingMore(true);
    fetchPlaylistsForDashboard({
      start: pageRef.current * PAGE_SIZE,
      length: PAGE_SIZE,
      name: debouncedSearch || undefined,
    })
      .then((res) => {
        setOptions((prev) => [
          ...prev,
          ...res.rows.map((p) => ({ value: String(p.playlistId), label: p.name })),
        ]);
        setTotalCount(res.totalCount);
        pageRef.current += 1;
      })
      .catch(() => {})
      .finally(() => setIsLoadingMore(false));
  };

  return (
    <SelectDropdown
      label={t('Playlist')}
      helpText={t('Select a Playlist to manage.')}
      value={value ? String(value) : ''}
      options={options}
      onSelect={(v) => onSelect(v ? Number(v) : null)}
      isLoading={isLoading}
      onLoadMore={handleLoadMore}
      hasMore={options.length < totalCount}
      isLoadingMore={isLoadingMore}
      searchable
      searchPlaceholder={t('Search playlists...')}
      onSearch={(v) => setSearchTerm(v)}
      clearable
    />
  );
}
