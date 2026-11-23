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
import { describe, expect, test } from 'vitest';

import { getEditDisplaySchema } from '@/schema/display';

const t = ((s: string) => s) as unknown as TFunction;

// Regression cover for xibosignage/xibo#3873: the schema had no `license`
// field, and zod drops keys it doesn't know, so a display could be saved with
// its Hardware Key cleared. The CMS identifies a player by that key.
describe('getEditDisplaySchema - Hardware Key', () => {
  test('rejects an empty Hardware Key', () => {
    const result = getEditDisplaySchema(t).safeParse({ display: 'Lobby', license: '' });

    expect(result.success).toBe(false);
    expect(result.error?.flatten().fieldErrors.license).toEqual(['Hardware Key is required']);
  });

  test('rejects a display with no Hardware Key at all', () => {
    const result = getEditDisplaySchema(t).safeParse({ display: 'Lobby' });

    expect(result.success).toBe(false);
  });

  test('accepts a display with a Hardware Key', () => {
    const result = getEditDisplaySchema(t).safeParse({ display: 'Lobby', license: 'ABC-123' });

    expect(result.success).toBe(true);
  });
});
