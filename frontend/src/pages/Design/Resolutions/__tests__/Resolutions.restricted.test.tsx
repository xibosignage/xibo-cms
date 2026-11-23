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

// The Resolutions page as a user who is not a super admin. The default user in
// Setup.tsx is a super admin with an empty feature list, which passes every
// feature check, so it can't show whether Add Resolution is gated.

import { screen } from '@testing-library/react';
import { describe, test, expect, beforeEach, vi } from 'vitest';

import Resolution from '../Resolutions';

import { mockResolutions, mockUser, renderWithClient } from './Setup';

import { fetchResolution } from '@/services/resolutionApi';
import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';
import type { User } from '@/types/user';

vi.mock('@/services/resolutionApi', () => ({
  fetchResolution: vi.fn(),
  createResolution: vi.fn(),
  updateResolution: vi.fn(),
  deleteResolution: vi.fn(),
}));

vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));

// A content manager, who ships with Add resolution on.
const contentManager = (features: Record<string, boolean> = {}): User =>
  buildCurrentUser(PERSONAS.contentManager, {
    settings: mockUser.settings,
    features,
  });

describe('Resolutions page - as a user who is not a super admin', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(fetchResolution).mockResolvedValue({
      rows: mockResolutions,
      totalCount: mockResolutions.length,
    });
  });

  test('a user without Add resolution gets no Add Resolution button', async () => {
    renderWithClient(<Resolution />, { user: contentManager({ 'resolution.add': false }) });

    await screen.findByText('1080p');

    expect(screen.queryByRole('button', { name: /^add resolution$/i })).not.toBeInTheDocument();
  });

  test('a user with Add resolution gets the Add Resolution button', async () => {
    renderWithClient(<Resolution />, { user: contentManager() });

    await screen.findByText('1080p');

    expect(screen.getByRole('button', { name: /^add resolution$/i })).toBeInTheDocument();
  });
});
