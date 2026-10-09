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

import { readFileSync } from 'node:fs';

/**
 * The server's sort allow-lists, read straight from the PHP factories.
 *
 * `BaseFactory::buildSortQuery()` silently drops any sort key that is not in the factory's
 * `$allowedColumns` (or a key of `$customColumns`) and falls back to the default order. A grid
 * column whose sort key is missing here shows a sort arrow but never reorders (report 221). Reading
 * the PHP keeps a single source of truth: a test cannot drift from the server.
 */

export type SortFactory = 'ScheduleFactory' | 'MediaFactory' | 'LayoutFactory';

// Read from disk, not imported through Vite: Vite refuses files outside frontend/ (fs.allow), and
// widening that would also expose them through the dev server. `import.meta.url` goes through a
// variable because Vite rewrites a literal `new URL(path, import.meta.url)` into an asset import.
const thisFile = import.meta.url;
const readFactory = (factory: SortFactory) =>
  readFileSync(new URL(`../../../lib/Factory/${factory}.php`, thisFile), 'utf8');

/** The body of the first `$name = [ ... ];` array inside the factory's query() method. */
function arrayBody(source: string, name: string): string {
  const query = source.slice(source.indexOf('function query('));
  const match = new RegExp(`\\$${name}\\s*=\\s*\\[([\\s\\S]*?)\\];`).exec(query);
  return match?.[1] ?? '';
}

/** Every sort key the factory's query() accepts. Throws if the PHP shape is not recognised. */
export function readSortAllowList(factory: SortFactory): string[] {
  const source = readFactory(factory);
  const allowed = [...arrayBody(source, 'allowedColumns').matchAll(/'([A-Za-z0-9_]+)'/g)].map(
    (m) => m[1]!,
  );
  const custom = [...arrayBody(source, 'customColumns').matchAll(/'([A-Za-z0-9_]+)'\s*=>/g)].map(
    (m) => m[1]!,
  );

  if (allowed.length === 0) {
    throw new Error(`Could not read $allowedColumns from ${factory}.php; has its shape changed?`);
  }
  return [...new Set([...allowed, ...custom])];
}
