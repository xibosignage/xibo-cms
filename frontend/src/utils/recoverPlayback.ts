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

// Retries within this time of the last reload are real failures, not an expired link.
const RETRY_INTERVAL_MS = 10_000;

const lastReloads = new WeakMap<HTMLMediaElement, { at: number; hasLoaded: boolean }>();

/**
 * Reloads a media element whose source failed, as its link may have expired, and returns whether it
 * did. It's reloaded again only once the last reload had loaded, so a source that keeps failing
 * shows its error.
 */
export function recoverPlayback(
  element: HTMLMediaElement,
  url: string,
  now: number = Date.now(),
): boolean {
  const position = element.currentTime;
  const lastReload = lastReloads.get(element);
  if (
    lastReload !== undefined &&
    (!lastReload.hasLoaded || now - lastReload.at < RETRY_INTERVAL_MS)
  ) {
    return false;
  }

  const reload = { at: now, hasLoaded: false };
  lastReloads.set(element, reload);
  element.addEventListener(
    'loadedmetadata',
    () => {
      reload.hasLoaded = true;
      element.currentTime = position;
    },
    { once: true },
  );
  element.src = url;
  return true;
}
