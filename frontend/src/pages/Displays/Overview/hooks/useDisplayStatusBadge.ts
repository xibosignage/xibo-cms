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

import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { getDisplayStatusInfo } from '../OverviewConfig';

import { useDateFormatter } from '@/hooks/useDateFormatter';
import type { Display } from '@/types/display';

// Status badge + thumbnail derivation for the two card-style displays of a
// Display's status (DisplayCard, ManageHeroStatus). DisplayTableView calls
// getDisplayStatusInfo directly instead, since its cell renderers aren't
// components and can't call hooks.
export function useDisplayStatusBadge(display: Display) {
  const { t } = useTranslation();
  const { formatRelative } = useDateFormatter();
  const [thumbnailErrored, setThumbnailErrored] = useState(false);

  const { bucket, colors, badgeLabel, lastSeenLabel } = getDisplayStatusInfo(
    display,
    t,
    formatRelative,
  );

  return {
    bucket,
    colors,
    badgeLabel,
    lastSeenLabel,
    showThumbnail: !!display.thumbnail && !thumbnailErrored,
    onThumbnailError: () => setThumbnailErrored(true),
  };
}
