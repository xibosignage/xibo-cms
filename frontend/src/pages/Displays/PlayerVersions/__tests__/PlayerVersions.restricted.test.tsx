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
// Test type: Page integration — Player Versions for a user who is not a
// Super Admin.
// Any user who could open the page could upload player software (xibo#4032).
// xibo-cms PR #3988 limited uploading to Super Admins: Add Version checks
// 'playersoftware.add', which no user group can be given, so only the Super
// Admin bypass passes it. There is therefore no "user with the feature" case.
// =============================================================================

import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockPlayerVersion, mockUser, SINGLE_PLAYER_VERSION } from './fixtures/playerVersion';
import { renderPlayerVersionsPage } from './helpers/renderPlayerVersionsPage';
import { mockFetchPlayerVersions } from './mocks/playerVersionApi';

import { testQueryClient } from '@/setupTests';
import { knownFailure } from '@/testUtils/knownFailure';
import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';
import { openFirstRowMenu } from '@/testUtils/rtl';
import type { User } from '@/types/user';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/playerVersionApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
  fetchUsers: vi.fn().mockResolvedValue([]),
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('../hooks/usePlayerVersionsFilterOptions', () => ({
  usePlayerVersionFilterOptions: () => ({ filterOptions: [] }),
}));

// =============================================================================
// Users
// =============================================================================

// A display manager can view Player Versions, but is not a Super Admin.
const displayManager: User = buildCurrentUser(PERSONAS.displayManager, {
  userId: 30,
  userName: 'display.manager',
  settings: mockUser.settings,
});

// =============================================================================
// Tests
// =============================================================================

describe('Player Versions page — a user who is not a Super Admin', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchPlayerVersions(SINGLE_PLAYER_VERSION);
  });

  test("a user who can view player versions doesn't get Add Version", async () => {
    renderPlayerVersionsPage({ user: displayManager });

    await screen.findByText(mockPlayerVersion.playerShowVersion);

    expect(screen.queryByRole('button', { name: /^add version$/i })).not.toBeInTheDocument();
  });

  test('a super admin gets Add Version', async () => {
    renderPlayerVersionsPage({
      user: buildCurrentUser(PERSONAS.superAdmin, { settings: mockUser.settings }),
    });

    expect(await screen.findByRole('button', { name: /^add version$/i })).toBeInTheDocument();
  });

  // xibo#3977 — open. The page has one feature for every write action, so a
  // user who may only view player versions still gets Edit and Delete. This pin
  // checks Delete only, as one defect per pin; Edit comes from the same check,
  // so the fix should add an Edit assertion when it turns this into a test.
  knownFailure(
    'a user who can only view player versions gets no Delete on a version',
    async () => {
      const user = userEvent.setup();
      renderPlayerVersionsPage({ user: displayManager });

      // Download is offered to anyone who can view, so the menu always has it.
      await openFirstRowMenu(user, mockPlayerVersion.playerShowVersion, /^download$/i);
    },
    () => expect(screen.queryByRole('button', { name: /^delete$/i })).not.toBeInTheDocument(),
  );
});
