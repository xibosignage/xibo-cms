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

import type { TFunction } from 'i18next';

export const getCommonFormOptions = (t: TFunction) => ({
  orientation: [
    { label: t('Portrait'), value: 'portrait' },
    { label: t('Landscape'), value: 'landscape' },
    { label: t('Square'), value: 'square' },
  ],
  inherit: [
    { label: t('Off'), value: 'Off' },
    { label: t('On'), value: 'On' },
    { label: t('Inherit'), value: 'Inherit' },
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
