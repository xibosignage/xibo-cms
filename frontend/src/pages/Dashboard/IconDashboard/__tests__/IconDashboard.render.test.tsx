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

import { screen } from '@testing-library/react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { LINK_CARDS, renderIconDashboard, visibleCards } from './helpers/renderIconDashboard';

import { testQueryClient } from '@/setupTests';
import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';
import type { User } from '@/types/user';

// =============================================================================
// Module mocks
// =============================================================================

// AboutModal is not opened in this file; the stub keeps its content out of the render.
vi.mock('@/components/layout/UserMenu/AboutModal', () => ({
  default: ({ onClose }: { onClose: () => void }) => (
    <div role="dialog" aria-label="About">
      <button onClick={onClose}>Close</button>
    </div>
  ),
}));

// =============================================================================
// Tests
// =============================================================================

describe('Icon Dashboard - cards for each kind of user', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  test('a Super Admin sees all six cards, in order', () => {
    renderIconDashboard(PERSONAS.superAdmin);

    expect(visibleCards()).toEqual([
      'Schedule',
      'Templates',
      'Layouts',
      'Users',
      'Library',
      'About',
    ]);
  });

  test('each link card goes to the right page', () => {
    renderIconDashboard(PERSONAS.superAdmin);

    for (const { name, href } of LINK_CARDS) {
      expect(screen.getByRole('link', { name })).toHaveAttribute('href', href);
    }
  });

  test('About is a button that opens a window, not a link', () => {
    renderIconDashboard(PERSONAS.superAdmin);

    expect(screen.getByRole('button', { name: 'About' })).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'About' })).not.toBeInTheDocument();
  });

  test('a Group Admin sees Users', () => {
    renderIconDashboard(PERSONAS.groupAdmin);

    expect(visibleCards()).toEqual(['Schedule', 'Layouts', 'Users', 'Library', 'About']);
  });

  // Rule 1.11 / K3: the Users card needs the admin rank as well as the feature. A plain User
  // with permission to view users must still not see it.
  test('a plain User does not see Users, even with permission to view users', () => {
    expect(PERSONAS.user.features['users.view']).toBe(true);

    renderIconDashboard(PERSONAS.user);

    expect(screen.queryByRole('link', { name: 'Users' })).not.toBeInTheDocument();
    expect(visibleCards()).toEqual(['Schedule', 'Layouts', 'Library', 'About']);
  });

  test('a Content Manager sees Templates, Layouts, Library and About only', () => {
    renderIconDashboard(PERSONAS.contentManager);

    expect(visibleCards()).toEqual(['Templates', 'Layouts', 'Library', 'About']);
  });

  test('a Playlist Manager sees only About', () => {
    renderIconDashboard(PERSONAS.playlistManager);

    expect(visibleCards()).toEqual(['About']);
  });

  // Super Admin passes every feature check (K4), so each case starts from a non-admin who
  // normally has the card, and switches off just that one feature.
  test.each<[string, string, User, string[]]>([
    ['Schedule', 'schedule.view', PERSONAS.user, ['Layouts', 'Library', 'About']],
    ['Layouts', 'layout.view', PERSONAS.user, ['Schedule', 'Library', 'About']],
    ['Library', 'library.view', PERSONAS.user, ['Schedule', 'Layouts', 'About']],
    ['Templates', 'template.view', PERSONAS.contentManager, ['Layouts', 'Library', 'About']],
    ['Users', 'users.view', PERSONAS.groupAdmin, ['Schedule', 'Layouts', 'Library', 'About']],
  ])(
    'taking away the %s permission (%s) hides only that card',
    (_card, feature, base, expected) => {
      renderIconDashboard(buildCurrentUser(base, { features: { [feature]: false } }));

      expect(visibleCards()).toEqual(expected);
    },
  );

  test('with no logged-in user, no cards are shown', () => {
    renderIconDashboard(null);

    expect(visibleCards()).toEqual([]);
  });
});
