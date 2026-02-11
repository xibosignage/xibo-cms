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
