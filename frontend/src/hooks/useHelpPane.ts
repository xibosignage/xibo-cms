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

import { useQuery } from '@tanstack/react-query';
import { useLocation } from 'react-router-dom';

import { getHelpKeyForPath } from '@/config/helpKeys';
import { getHelpPageLinks } from '@/services/helpApi';

export function useHelpKey(): string | undefined {
  const { pathname } = useLocation();
  return getHelpKeyForPath(pathname);
}

export function useHelpPageLinks(helpKey: string | undefined, enabled: boolean) {
  return useQuery({
    queryKey: ['help', 'pageLinks', helpKey ?? null],
    queryFn: ({ signal }) => getHelpPageLinks(helpKey, signal),
    enabled,
    staleTime: 1000 * 60 * 10,
  });
}
