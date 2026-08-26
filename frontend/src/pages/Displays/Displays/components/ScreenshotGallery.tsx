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

import { ImageOff, Loader2, RefreshCw, TriangleAlert } from 'lucide-react';
import { DateTime } from 'luxon';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { useDisplayScreenshots } from '../hooks/useDisplayManageData';

import Button from '@/components/ui/Button';
import { useUserContext } from '@/context/UserContext';
import type { Display } from '@/types/display';
import type { DisplayScreenshot } from '@/types/displayManage';

/**
 * How recent a screenshot has to be to count as live. Exported for reuse by
 * the Overview Manage page's ScreenshotCard, which shows the same "Live"
 * badge convention for its single most-recent capture.
 */
export const LIVE_WINDOW_MS = 2 * 60 * 1000;

/**
 * Cap on how many of the newest screenshots may carry the badge. On a short interval several
 * would sit inside the window at once and the badge would stop meaning "the latest".
 */
const MAX_LIVE_BADGES = 2;

interface ScreenshotGalleryProps {
  display: Display;
  onSelect: (screenshot: DisplayScreenshot) => void;
}

/**
 * A display's recent screenshots as a clickable thumbnail grid, newest first. Shared by the
 * legacy Manage modal and the Overview page's Screenshots quick action so both read from the
 * same `/display/screenshot/{id}/history` history rather than each growing its own gallery.
 */
export default function ScreenshotGallery({ display, onSelect }: ScreenshotGalleryProps) {
  const { t } = useTranslation();
  const { user } = useUserContext();
  const cmsTimeZone = user?.settings?.defaultTimezone ?? 'UTC';

  const [brokenIds, setBrokenIds] = useState<Set<number>>(new Set());

  const {
    data: screenshots,
    isPending,
    isError,
    refetch,
    isFetching,
  } = useDisplayScreenshots(display.displayId, true);

  const items = screenshots ?? [];
  const now = Date.now();

  // Loading/error/empty all share the same minimum height as the grid below —
  // matching the "No results found" convention used by DataTable/DataGrid —
  // so the modal doesn't visibly collapse and pop back open as the query
  // settles or a display's history goes from empty to populated.
  if (isPending) {
    return (
      <div className="flex min-h-64 flex-1 items-center justify-center">
        <Loader2 className="size-6 animate-spin text-gray-400" />
      </div>
    );
  }

  // The history request itself failed (e.g. a 500 from the CMS) — shown as a
  // generic, actionable message rather than the raw HTTP error text, with a
  // way to try again without having to close and reopen the modal.
  if (isError) {
    return (
      <div className="flex min-h-64 flex-1 flex-col items-center justify-center gap-3 px-4 text-center">
        <div className="inline-flex size-15.5 items-center justify-center rounded-full border-7 border-red-50 bg-red-100 text-red-800">
          <TriangleAlert className="size-5 shrink-0" />
        </div>
        <h3 className="text-lg font-semibold text-gray-800">{t('Could not load screenshots')}</h3>
        <p className="text-gray-500">{t('Something went wrong. Please try again.')}</p>
        <Button
          variant="tertiary"
          onClick={() => refetch()}
          disabled={isFetching}
          leftIcon={RefreshCw}
        >
          {t('Retry')}
        </Button>
      </div>
    );
  }

  if (items.length === 0) {
    return (
      <div className="flex min-h-64 flex-1 flex-col items-center justify-center gap-3 px-4 text-center">
        <div className="inline-flex size-15.5 items-center justify-center rounded-full border-7 border-gray-50 bg-gray-100 text-gray-500">
          <ImageOff className="size-5 shrink-0" />
        </div>
        <h3 className="text-lg font-semibold text-gray-800">{t('No screenshots yet')}</h3>
        <p className="text-gray-500">{t('Request one, or set an interval to collect them.')}</p>
      </div>
    );
  }

  return (
    <div className="p-4">
      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
        {items.map((shot, index) => {
          const takenAt = shot.createdDt * 1000;
          const isLive = index < MAX_LIVE_BADGES && now - takenAt < LIVE_WINDOW_MS;
          const isBroken = brokenIds.has(shot.displayScreenshotId);

          return (
            <button
              key={shot.displayScreenshotId}
              type="button"
              onClick={() => onSelect(shot)}
              className="group relative block cursor-pointer overflow-hidden rounded-lg border border-gray-200 bg-gray-50 text-left transition-shadow hover:shadow-md focus:outline-none focus:ring-2 focus:ring-xibo-blue-500"
            >
              {isBroken ? (
                <div className="flex aspect-video w-full items-center justify-center bg-gray-100 text-gray-300">
                  <ImageOff className="size-6" />
                </div>
              ) : (
                <img
                  src={shot.url}
                  alt={t('Screenshot')}
                  loading="lazy"
                  className="aspect-video w-full bg-white object-contain"
                  onError={() =>
                    setBrokenIds((prev) => new Set(prev).add(shot.displayScreenshotId))
                  }
                />
              )}

              {isLive && (
                <span className="absolute left-2 top-2 inline-flex items-center gap-1 rounded-full bg-red-600 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">
                  <span className="h-1.5 w-1.5 rounded-full bg-white" />
                  {t('Live')}
                </span>
              )}

              <span className="block border-t border-gray-200 bg-white px-2 py-1.5 text-xs text-gray-600">
                {DateTime.fromMillis(takenAt).setZone(cmsTimeZone).toFormat('dd LLL HH:mm:ss')}
              </span>
            </button>
          );
        })}
      </div>

      <p className="mt-3 text-xs text-gray-500">
        {t('The most recent screenshots are kept, oldest removed automatically.')}
      </p>
    </div>
  );
}
