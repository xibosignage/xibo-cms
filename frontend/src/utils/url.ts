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

// React does not strip javascript:/data: hrefs (it only logs a dev warning), so anywhere a
// user- or admin-supplied URL is rendered as a clickable link, gate it through this check first.
export function isSafeHttpUrl(url: string | null | undefined): boolean {
  if (!url) {
    return false;
  }

  try {
    const parsed = new URL(url, window.location.origin);
    return parsed.protocol === 'http:' || parsed.protocol === 'https:';
  } catch {
    return false;
  }
}

// Starts a browser download by navigating to a same-origin URL, so the server may redirect it to a
// storage URL without that host needing CORS (a fetched blob would). The server names the file, and
// the download attribute keeps an error response from replacing the app. downloadLink=1 makes an
// expired session answer 401, a failed download, instead of saving the login page as the file.
export function navigateToDownload(url: string): void {
  const link = document.createElement('a');

  link.href = `${url}${url.includes('?') ? '&' : '?'}downloadLink=1`;
  link.setAttribute('download', '');

  document.body.appendChild(link); // Required for Firefox
  link.click();

  link.parentNode?.removeChild(link);
}
