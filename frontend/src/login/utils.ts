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

// Re-exported for existing importers; the canonical definition lives in @/config/publicPath.
import { publicPath } from '@/config/publicPath';

export { publicPath };

/**
 * Validates a priorRoute value before navigation. Mirrors PHP's
 * sanitizePriorRouteForOutput() + getRedirect() logic on the client side.
 * Returns the safe URL, or the fallback if the value is rejected.
 */
export function getSafeRedirectUrl(
  priorRoute: string | undefined,
  fallback = `${publicPath}`,
): string {
  if (!priorRoute) return fallback;

  try {
    // Resolve against current origin to detect absolute URLs with a different origin
    const resolved = new URL(priorRoute, window.location.origin);
    if (resolved.origin !== window.location.origin) return fallback;

    const path = resolved.pathname;
    const loginPath = `${publicPath}login`;
    if (path === '' || path === '/' || path === publicPath || path.startsWith(loginPath)) {
      return fallback;
    }

    let safe = path;
    if (resolved.search) safe += resolved.search;
    if (resolved.hash) safe += resolved.hash;
    return safe;
  } catch {
    return fallback;
  }
}
