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

// Decodes HTML entities (named + numeric) produced by PHP's htmlentities()/htmlspecialchars().
// Uses DOMParser rather than assigning to a live element's innerHTML: DOMParser's
// document has no browsing context, so embedded scripts don't run and event handlers
// (e.g. img onerror) are never invoked, avoiding the mutation-XSS risk of the classic
// textarea.innerHTML decode trick.
export function decodeHtmlEntities(encoded: string): string {
  if (!encoded) return '';
  const doc = new DOMParser().parseFromString(encoded, 'text/html');
  return doc.documentElement.textContent ?? '';
}

// Increments a numeric suffix for media name
export function incrementName(value: string): string {
  const regex = /^(.*?)(?:\s\((\d+)\))?$/;

  const match = value.match(regex);
  if (!match) return `${value} (1)`;

  const base = match[1];
  const count = match[2] ? Number(match[2]) + 1 : 1;

  return `${base} (${count})`;
}

// Increments a numeric suffix for media file name
export function incrementFileName(fileName: string): string {
  const dotIndex = fileName.lastIndexOf('.');

  if (dotIndex === -1) {
    return incrementName(fileName);
  }

  const name = fileName.slice(0, dotIndex);
  const ext = fileName.slice(dotIndex);

  return `${incrementName(name)}${ext}`;
}

// Strips characters invalid in filenames and trailing dots, so a name is safe
// to use as a download filename base (before the extension is appended).
export function sanitizeFileName(name: string): string {
  return name
    .replace(/[/\\:*?"<>|]/g, '')
    .replace(/\s+/g, ' ')
    .trim()
    .replace(/\.+$/, '')
    .trim();
}
