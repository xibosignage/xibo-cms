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

import type { TFunction } from 'i18next';
import { useEffect, useState } from 'react';

import { getBaseFilterKeys } from '../EventsConfig';

import type { FilterOption } from '@/components/ui/SelectFilter';
import { useDebounce } from '@/hooks/useDebounce';
import { fetchCampaigns } from '@/services/campaignApi';

const PAGE_SIZE = 10;

export function useEventFilterOptions(t: TFunction) {
  const [layoutOptions, setLayoutOptions] = useState<FilterOption[]>([]);
  const [layoutPage, setLayoutPage] = useState(0);
  const [hasMoreLayouts, setHasMoreLayouts] = useState(false);
  const [isLoadingLayouts, setIsLoadingLayouts] = useState(false);
  const [isLoadingMoreLayouts, setIsLoadingMoreLayouts] = useState(false);
  const [layoutSearch, setLayoutSearch] = useState('');
  const debouncedLayoutSearch = useDebounce(layoutSearch, 300);

  const [campaignOptions, setCampaignOptions] = useState<FilterOption[]>([]);
  const [campaignPage, setCampaignPage] = useState(0);
  const [hasMoreCampaigns, setHasMoreCampaigns] = useState(false);
  const [isLoadingCampaigns, setIsLoadingCampaigns] = useState(false);
  const [isLoadingMoreCampaigns, setIsLoadingMoreCampaigns] = useState(false);
  const [campaignSearch, setCampaignSearch] = useState('');
  const debouncedCampaignSearch = useDebounce(campaignSearch, 300);

  useEffect(() => {
    setIsLoadingLayouts(true);
    setLayoutOptions([]);
    setLayoutPage(0);
    fetchCampaigns({
      start: 0,
      length: PAGE_SIZE,
      isLayoutSpecific: 1,
      keyword: debouncedLayoutSearch || undefined,
    })
      .then((res) => {
        setLayoutOptions(res.rows.map((c) => ({ label: c.campaign, value: c.campaignId })));
        setHasMoreLayouts(res.rows.length === PAGE_SIZE);
      })
      .catch(() => {})
      .finally(() => setIsLoadingLayouts(false));
  }, [debouncedLayoutSearch]);

  useEffect(() => {
    setIsLoadingCampaigns(true);
    setCampaignOptions([]);
    setCampaignPage(0);
    fetchCampaigns({
      start: 0,
      length: PAGE_SIZE,
      isLayoutSpecific: 0,
      keyword: debouncedCampaignSearch || undefined,
    })
      .then((res) => {
        setCampaignOptions(res.rows.map((c) => ({ label: c.campaign, value: c.campaignId })));
        setHasMoreCampaigns(res.rows.length === PAGE_SIZE);
      })
      .catch(() => {})
      .finally(() => setIsLoadingCampaigns(false));
  }, [debouncedCampaignSearch]);

  const handleLoadMoreLayouts = () => {
    if (isLoadingMoreLayouts || !hasMoreLayouts) {
      return;
    }
    const nextPage = layoutPage + 1;
    setIsLoadingMoreLayouts(true);
    fetchCampaigns({
      start: nextPage * PAGE_SIZE,
      length: PAGE_SIZE,
      isLayoutSpecific: 1,
      keyword: debouncedLayoutSearch || undefined,
    })
      .then((res) => {
        setLayoutOptions((prev) => [
          ...prev,
          ...res.rows.map((c) => ({ label: c.campaign, value: c.campaignId })),
        ]);
        setLayoutPage(nextPage);
        setHasMoreLayouts(res.rows.length === PAGE_SIZE);
      })
      .catch(() => {})
      .finally(() => setIsLoadingMoreLayouts(false));
  };

  const handleLoadMoreCampaigns = () => {
    if (isLoadingMoreCampaigns || !hasMoreCampaigns) {
      return;
    }
    const nextPage = campaignPage + 1;
    setIsLoadingMoreCampaigns(true);
    fetchCampaigns({
      start: nextPage * PAGE_SIZE,
      length: PAGE_SIZE,
      isLayoutSpecific: 0,
      keyword: debouncedCampaignSearch || undefined,
    })
      .then((res) => {
        setCampaignOptions((prev) => [
          ...prev,
          ...res.rows.map((c) => ({ label: c.campaign, value: c.campaignId })),
        ]);
        setCampaignPage(nextPage);
        setHasMoreCampaigns(res.rows.length === PAGE_SIZE);
      })
      .catch(() => {})
      .finally(() => setIsLoadingMoreCampaigns(false));
  };

  const filterOptions = getBaseFilterKeys(t).map((item) => {
    if (item.name === 'layoutCampaignId') {
      return {
        ...item,
        options: layoutOptions,
        onLoadMore: handleLoadMoreLayouts,
        hasMore: hasMoreLayouts,
        isLoadingMore: isLoadingMoreLayouts,
        isLoading: isLoadingLayouts,
        onSearch: (term: string) => setLayoutSearch(term),
      };
    }

    if (item.name === 'campaignId') {
      return {
        ...item,
        options: campaignOptions,
        onLoadMore: handleLoadMoreCampaigns,
        hasMore: hasMoreCampaigns,
        isLoadingMore: isLoadingMoreCampaigns,
        isLoading: isLoadingCampaigns,
        onSearch: (term: string) => setCampaignSearch(term),
      };
    }

    return item;
  });

  return { filterOptions };
}
