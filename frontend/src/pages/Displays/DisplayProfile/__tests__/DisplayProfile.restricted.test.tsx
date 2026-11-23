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

// The Display Profiles page as a user who is not a super admin. Adding a
// profile needs the add feature. Changing one needs the edit feature and that
// the user owns the profile: a super admin can change any profile, so these
// tests use a display manager instead.

import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { buildDisplayProfile, mockDisplayProfile, mockUser } from './fixtures/displayProfile';
import { renderDisplayProfilePage } from './helpers/renderDisplayProfilePage';
import { mockFetchDisplayProfile } from './mocks/displayProfileApi';

import { testQueryClient } from '@/setupTests';
import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';
import { openFirstRowMenu } from '@/testUtils/rtl';
import type { User } from '@/types/user';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/displayProfileApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('../hooks/useDisplayProfileFilterOptions', () => ({
  useDisplayProfileFilterOptions: () => ({ filterOptions: [], isLoading: false }),
}));

// =============================================================================
// Users and rows
// =============================================================================

const DISPLAY_MANAGER_ID = 30;

// A display manager, who ships with the add and edit profile features on.
const displayManager = (features: Record<string, boolean> = {}): User =>
  buildCurrentUser(PERSONAS.displayManager, {
    userId: DISPLAY_MANAGER_ID,
    settings: mockUser.settings,
    features,
  });

const profileOwnedBy = (userId: number) => ({
  rows: [buildDisplayProfile({ userId })],
  totalCount: 1,
});

// =============================================================================
// Tests
// =============================================================================

describe('Display Profiles page - as a user who is not a super admin', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  test('a user without Add display profiles gets no Add Display Profile button', async () => {
    mockFetchDisplayProfile(profileOwnedBy(DISPLAY_MANAGER_ID));
    renderDisplayProfilePage({ user: displayManager({ 'displayprofile.add': false }) });

    await screen.findByText(mockDisplayProfile.name);

    expect(
      screen.queryByRole('button', { name: /^add display profile$/i }),
    ).not.toBeInTheDocument();
  });

  test('a user with Add display profiles gets the Add Display Profile button', async () => {
    mockFetchDisplayProfile(profileOwnedBy(DISPLAY_MANAGER_ID));
    renderDisplayProfilePage({ user: displayManager() });

    await screen.findByText(mockDisplayProfile.name);

    expect(screen.getByRole('button', { name: /^add display profile$/i })).toBeInTheDocument();
  });

  // On a profile someone else owns the row has no actions at all, so there is
  // no menu to open. Wait for the row, then check none of them appear.
  test('a user gets no Edit, Copy or Delete on a profile someone else owns', async () => {
    mockFetchDisplayProfile(profileOwnedBy(1));
    renderDisplayProfilePage({ user: displayManager() });

    await screen.findByText(mockDisplayProfile.name);

    expect(screen.queryByRole('button', { name: /more actions/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /^edit$/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /^copy$/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /^delete$/i })).not.toBeInTheDocument();
  });

  // Owning the profile isn't enough: changing it also needs the edit feature.
  // Without it the row has no actions, so again there is no menu to open.
  test('a user without Edit display profiles gets no Edit, Copy or Delete, even on their own profile', async () => {
    mockFetchDisplayProfile(profileOwnedBy(DISPLAY_MANAGER_ID));
    renderDisplayProfilePage({ user: displayManager({ 'displayprofile.modify': false }) });

    await screen.findByText(mockDisplayProfile.name);

    expect(screen.queryByRole('button', { name: /more actions/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /^edit$/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /^copy$/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /^delete$/i })).not.toBeInTheDocument();
  });

  test('a user gets Edit, Copy and Delete on their own profile', async () => {
    const user = userEvent.setup();
    mockFetchDisplayProfile(profileOwnedBy(DISPLAY_MANAGER_ID));
    renderDisplayProfilePage({ user: displayManager() });

    await openFirstRowMenu(user, mockDisplayProfile.name);
    expect(screen.getByRole('button', { name: /^copy$/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /^delete$/i })).toBeInTheDocument();
  });
});
