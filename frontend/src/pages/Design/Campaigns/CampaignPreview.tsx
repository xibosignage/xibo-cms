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

import { useInfiniteQuery, useQuery } from '@tanstack/react-query';
import { ArrowLeft, Maximize2 } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate, useParams } from 'react-router-dom';

import { fetchCampaignById } from '@/services/campaignApi';
import { fetchLayouts } from '@/services/layoutsApi';
import type { Layout } from '@/types/layout';
import { formatDuration } from '@/utils/formatters';

const PAGE_SIZE = 30;

function LayoutPreviewCard({ layout }: { layout: Layout }) {
  const { t } = useTranslation();

  return (
    <div className="flex flex-col gap-2 rounded-sm overflow-hidden shadow-sm">
      <div className="relative w-full aspect-4/3 bg-black rounded overflow-hidden">
        {layout.previewUrl ? (
          <iframe
            sandbox="allow-scripts"
            src={layout.previewUrl}
            title={t('Layout {{id}}', { id: layout.layoutId })}
            className="absolute inset-0 w-full h-full border-0 overflow-hidden"
          />
        ) : (
          <div className="absolute inset-0 flex items-center justify-center">
            <span className="text-xs text-gray-400">{t('Preview not available')}</span>
          </div>
        )}
      </div>
      <div className="flex items-end justify-between gap-x-2 p-2">
        <div className="text-xs font-semibold text-gray-800 min-w-0">
          <p>
            <span>{t('ID')}:</span> {layout.layoutId}
          </p>
          <p className="truncate">
            <span>{t('NAME')}:</span> {layout.layout}
          </p>
          <p>
            <span>{t('DURATION')}:</span> {formatDuration(layout.duration || 0)}
          </p>
        </div>
        {layout.previewUrl && (
          <a
            href={layout.previewUrl}
            target="_blank"
            rel="noopener noreferrer"
            className="shrink-0"
            aria-label={t('Open preview in new tab for {{name}}', { name: layout.layout })}
          >
            <Maximize2 className="h-4 w-4" />
          </a>
        )}
      </div>
    </div>
  );
}

export default function CampaignPreview() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { id } = useParams<{ id: string }>();
  const campaignId = Number(id);
  const isValidId = !Number.isNaN(campaignId) && campaignId > 0;

  const {
    data: campaign,
    isLoading: campaignLoading,
    isError: isCampaignError,
    error: campaignError,
  } = useQuery({
    queryKey: ['campaign', campaignId],
    queryFn: () => fetchCampaignById(campaignId),
    enabled: isValidId,
  });

  const {
    data: layoutsData,
    isLoading: layoutsLoading,
    isError: isLayoutsError,
    error: layoutsError,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = useInfiniteQuery({
    queryKey: ['campaign-preview-layouts', campaignId],
    queryFn: ({ pageParam = 0 }) =>
      fetchLayouts({
        start: pageParam,
        length: PAGE_SIZE,
        campaignId,
        sortBy: 'displayOrder',
        sortDir: 'ASC',
      }),
    initialPageParam: 0,
    getNextPageParam: (lastPage, allPages) => {
      const loaded = allPages.reduce((total, page) => total + page.rows.length, 0);
      return loaded < lastPage.totalCount ? loaded : undefined;
    },
    enabled: isValidId,
  });

  const layouts = layoutsData?.pages.flatMap((page) => page.rows) ?? [];
  const isLoading = campaignLoading || layoutsLoading;

  const sentinelRef = useRef<HTMLDivElement>(null);
  const scrollContainerRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!hasNextPage || !sentinelRef.current || !scrollContainerRef.current) {
      return;
    }

    const el = sentinelRef.current;
    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0]?.isIntersecting && !isFetchingNextPage) {
          fetchNextPage();
        }
      },
      { threshold: 0.1, root: scrollContainerRef.current },
    );

    observer.observe(el);
    return () => observer.disconnect();
  }, [hasNextPage, isFetchingNextPage, fetchNextPage]);
  const isError = isCampaignError || isLayoutsError;
  const errorMessage =
    campaignError instanceof Error
      ? campaignError.message
      : layoutsError instanceof Error
        ? layoutsError.message
        : '';

  const title = campaign?.isLayoutSpecific
    ? t('Layout Preview for {{name}}', { name: campaign?.campaign })
    : t('Campaign Preview for {{name}}', { name: campaign?.campaign });

  return (
    <section className="flex flex-col h-full w-full min-h-0 px-5 pb-5">
      <div className="flex items-center py-4 gap-x-3">
        <button
          type="button"
          aria-label={t('Back')}
          onClick={() => navigate('/design/campaign')}
          className="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200"
        >
          <ArrowLeft className="h-5 w-5" />
        </button>
        {!isLoading && campaign && (
          <div className="flex items-center gap-x-3 gap-y-1 w-full">
            <h1 className="text-xl font-semibold text-gray-900">{title}</h1>
            <p className="text-xs font-semibold whitespace-nowrap">
              <span className=" text-gray-500">{t('TOTAL DURATION')}:</span>{' '}
              <span className="text-gray-800">{formatDuration(campaign.totalDuration || 0)}</span>{' '}
              <span className="italic text-gray-500">({t('hours:min:sec')})</span>
            </p>
            <hr className="border-gray-400  h-3 border-l" />
            <p className="text-xs font-semibold whitespace-nowrap">
              <span className="text-gray-500">{t('NO. OF LAYOUTS')}:</span> {campaign.numberLayouts}
            </p>
          </div>
        )}
      </div>

      {!isValidId ? (
        <div className="flex-1 flex items-center justify-center bg-gray-50 rounded-lg border border-gray-200">
          <span className="text-gray-500">{t('Invalid campaign ID.')}</span>
        </div>
      ) : isLoading ? (
        <div className="flex-1 flex items-center justify-center bg-gray-50 animate-pulse rounded-lg border border-gray-200">
          <span className="text-gray-400 font-medium">{t('Loading preview...')}</span>
        </div>
      ) : isError ? (
        <div className="flex-1 flex items-center justify-center bg-red-50 rounded-lg border border-red-200">
          <span className="text-red-600">
            {errorMessage || t('Failed to load campaign preview.')}
          </span>
        </div>
      ) : layouts.length === 0 ? (
        <div className="flex-1 flex items-center justify-center bg-gray-50 rounded-lg border border-gray-200">
          <span className="text-gray-500">{t('No layouts found in this campaign.')}</span>
        </div>
      ) : (
        <div ref={scrollContainerRef} className="overflow-y-auto pb-6">
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-6">
            {layouts.map((layout) => (
              <LayoutPreviewCard key={layout.layoutId} layout={layout} />
            ))}
          </div>
          {hasNextPage && <div ref={sentinelRef} className="h-1" />}
          {isFetchingNextPage && (
            <p className="text-xs text-gray-400 text-center py-3">{t('Loading…')}</p>
          )}
        </div>
      )}
    </section>
  );
}
