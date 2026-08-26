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

import { Camera, Loader2, Monitor } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { useDateFormatter } from '@/hooks/useDateFormatter';
import { LIVE_WINDOW_MS } from '@/pages/Displays/Displays/components/ScreenshotGallery';
import { useDisplayScreenshots } from '@/pages/Displays/Displays/hooks/useDisplayManageData';
import type { Display } from '@/types/display';

interface ScreenshotCardProps {
  display: Display;
}

// The Manage page's "Screenshot" card — the most recent capture from the
// display's screenshot history. The full gallery is opened from the header's
// "Request Screenshot" button rather than a link on this card.
// Replaces the thumbnail half of the old ManageHeroStatus.tsx (the status
// pill/last-seen half moved into the page header instead).
export default function ScreenshotCard({ display }: ScreenshotCardProps) {
  const { t } = useTranslation();
  const { formatRelative } = useDateFormatter();
  const { data, isPending, isError } = useDisplayScreenshots(display.displayId, true);

  const latest = data?.[0] ?? null;
  const isLive = latest !== null && Date.now() - latest.createdDt * 1000 < LIVE_WINDOW_MS;

  return (
    <div className="flex flex-col rounded-lg border border-gray-200 overflow-hidden">
      <div className="bg-gray-50 px-4 py-2.5 border-b border-gray-200 flex items-center gap-3">
        <h3 className="flex items-center gap-1.5 text-sm font-semibold text-gray-700">
          <Camera className="size-3.5 shrink-0 text-gray-400" aria-hidden="true" />
          {t('Screenshot')}
        </h3>
      </div>

      <div className="relative flex-1 min-h-[190px] bg-gradient-to-br from-gray-50 to-gray-100 flex items-center justify-center">
        {isPending ? (
          <Loader2 className="size-6 animate-spin text-gray-300" />
        ) : isError ? (
          <p className="px-4 text-center text-sm text-red-600">
            {t('Could not load the latest screenshot.')}
          </p>
        ) : latest ? (
          <img
            src={latest.url}
            alt={t('Screenshot of {{name}}', { name: display.display })}
            className="h-full w-full object-contain"
          />
        ) : (
          <Monitor className="size-10 text-gray-300" aria-hidden="true" />
        )}

        {!isPending && !isError && (
          <span className="absolute left-3 top-3 inline-flex items-center gap-1.5 rounded-full bg-black/55 px-2.5 py-1 text-[11px] text-white">
            {isLive && <span className="size-1.5 shrink-0 rounded-full bg-red-500" />}
            {latest
              ? t('Taken {{time}}', { time: formatRelative(new Date(latest.createdDt * 1000)) })
              : t('No recent capture')}
          </span>
        )}

        {!isPending && !isError && (
          <span
            className="absolute bottom-3 left-3 max-w-[80%] truncate rounded-full bg-black/55 px-2.5 py-1 text-[11px] text-white"
            title={display.currentLayout ?? undefined}
          >
            {display.currentLayout || t('No layout playing')}
          </span>
        )}
      </div>
    </div>
  );
}
