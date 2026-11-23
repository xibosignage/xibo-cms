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

import { beforeEach, describe, test, expect, vi } from 'vitest';

import type { LoginConfig } from '../types';

// i18n reads window.__LOGIN_CONFIG__ once at import, so each test loads a fresh copy.
const loadT = async (config: Partial<LoginConfig> | undefined) => {
  (window as unknown as { __LOGIN_CONFIG__?: Partial<LoginConfig> }).__LOGIN_CONFIG__ = config;
  vi.resetModules();
  return (await import('../i18n')).t;
};

describe('Login page translations', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  test('a label the CMS sent is shown translated', async () => {
    const t = await loadT({ i18n: { aboutLabel: 'À propos' } });

    expect(t('aboutLabel')).toBe('À propos');
  });

  test('a label the CMS did not send falls back to its key', async () => {
    const t = await loadT({ i18n: { aboutLabel: 'À propos' } });

    expect(t('closeLabel')).toBe('closeLabel');
  });

  test('every label falls back to its key when the page has no login config', async () => {
    const t = await loadT(undefined);

    expect(t('aboutLabel')).toBe('aboutLabel');
  });
});
