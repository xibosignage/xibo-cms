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

export const getCommonFormOptions = (t: TFunction) => ({
  orientation: [
    { label: t('Portrait'), value: 'portrait' },
    { label: t('Landscape'), value: 'landscape' },
    { label: t('Square'), value: 'square' },
  ],
  inherit: [
    { label: t('Off'), value: 'off' },
    { label: t('On'), value: 'on' },
    { label: t('Inherit'), value: 'inherit' },
  ],
  lastModifiedFilter: [
    { label: t('Any time'), value: '' },
    { label: t('Today'), value: 'today' },
    { label: t('Last 7 days'), value: '7d' },
    { label: t('Last 30 days'), value: '30d' },
    { label: t('This year'), value: '1y' },
  ],
  retired: [
    { label: t('Any'), value: null },
    { label: t('No'), value: 0 },
    { label: t('Yes'), value: 1 },
  ],
});
