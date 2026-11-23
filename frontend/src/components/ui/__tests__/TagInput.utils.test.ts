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

// =============================================================================
// Test type: Pure function test
// The tag text helpers exported from TagInput. Every form that saves tags sends
// serializeTags(collectTags(...)) to the API, so these decide what is saved.
// No DOM, no mocks.
// =============================================================================

import { describe, test, expect } from 'vitest';

import { buildTag } from './fixtures/tags';

import { collectTags, parseTag, serializeTags } from '@/components/ui/forms/TagInput';

describe('parseTag', () => {
  test('a plain name becomes a tag with no value', () => {
    expect(parseTag(' lobby ')).toEqual({ tag: 'lobby', value: '', tagId: 0 });
  });

  test('"name|text" gives a trimmed text value', () => {
    expect(parseTag('floor| north ')).toEqual({ tag: 'floor', value: 'north', tagId: 0 });
  });

  test('"name|42" gives a numeric value', () => {
    const parsed = parseTag('floor|42');
    expect(parsed?.value).toBe(42);
  });

  test('empty, spaces-only and nameless input is ignored', () => {
    expect(parseTag('')).toBeNull();
    expect(parseTag('   ')).toBeNull();
    expect(parseTag('|x')).toBeNull();
  });

  test('"name|" gives a tag with no value', () => {
    expect(parseTag('floor|')).toEqual({ tag: 'floor', value: '', tagId: 0 });
  });
});

describe('collectTags', () => {
  test('uncommitted text is added to the list', () => {
    const tags = [buildTag({ tag: 'lobby' })];
    expect(collectTags(tags, 'lounge|2')).toEqual([tags[0], { tag: 'lounge', value: 2, tagId: 0 }]);
  });

  test('duplicate or empty uncommitted text leaves the list unchanged', () => {
    const tags = [buildTag({ tag: 'lobby' })];
    expect(collectTags(tags, 'lobby')).toBe(tags);
    expect(collectTags(tags, '  ')).toBe(tags);
  });
});

describe('serializeTags', () => {
  test('tags become comma-separated text, with "name|value" only when a value is set', () => {
    const tags = [
      buildTag({ tag: 'lobby' }),
      buildTag({ tag: 'floor', value: 3 }),
      buildTag({ tag: 'zone', value: 'a' }),
    ];
    expect(serializeTags(tags)).toBe('lobby,floor|3,zone|a');
  });
});
