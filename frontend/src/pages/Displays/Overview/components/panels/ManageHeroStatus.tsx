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

import { Monitor } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import { useDisplayStatusBadge } from '../../hooks/useDisplayStatusBadge';
import DisplayStatusBadge from '../DisplayStatusBadge';

import type { Display } from '@/types/display';

interface ManageHeroStatusProps {
  display: Display;
}

// The Hero section from display-management.html's Manage Modal — a status
// thumbnail + big status pill + "Last checked in". Uses the same square
// screenshot treatment as DisplayCard.tsx (not the mock's circular
// ring/pulse avatar — this page never shows displays as circular photos).
export default function ManageHeroStatus({ display }: ManageHeroStatusProps) {
  const { t } = useTranslation();
  const { bucket, colors, badgeLabel, lastSeenLabel, showThumbnail, onThumbnailError } =
    useDisplayStatusBadge(display);

  return (
    <div
      className={twMerge(
        'flex items-center gap-4 rounded-lg border p-4',
        colors.tintBg,
        colors.tintBorder,
      )}
    >
      <div className="relative aspect-video w-28 shrink-0 overflow-hidden rounded-lg border border-white bg-gradient-to-br from-gray-50 to-gray-100 shadow-sm">
        {showThumbnail ? (
          <img
            src={display.thumbnail}
            alt={t('Screenshot of {{name}}', { name: display.display })}
            className="h-full w-full object-cover"
            onError={onThumbnailError}
          />
        ) : (
          <div className="flex h-full items-center justify-center text-gray-300">
            <Monitor className="size-6" />
          </div>
        )}
      </div>
      <div className="flex flex-col gap-1.5">
        <DisplayStatusBadge bucket={bucket} colors={colors} label={badgeLabel} className="w-fit" />
        <p className="text-xs text-gray-500">
          {t('Last checked in {{time}}', { time: lastSeenLabel })}
        </p>
      </div>
    </div>
  );
}
