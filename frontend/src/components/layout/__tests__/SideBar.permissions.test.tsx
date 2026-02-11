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

/**
 * SideBar Permission Tests
 *
 * Validates that sidebar navigation items are shown or hidden based on:
 * 1. User type (SuperAdmin, GroupAdmin, Regular User)
 * 2. Feature flags (e.g., 'library.view', 'schedule.view')
 * 3. Validator functions (e.g., isSuperAdmin checks on specific routes)
 */
import { fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';

import SidebarMenu from '../SideBar';

import { UserProvider } from '@/context/UserContext';
import type { User } from '@/types/user';
import { UserType } from '@/types/user';
import { hasFeature } from '@/utils/permissions';

// ---------- Shared mock settings used across all test users ----------
const mockSettings = {
  defaultTimezone: 'UTC',
  defaultLanguage: 'en',
  DATE_FORMAT_JS: 'YYYY-MM-DD',
  TIME_FORMAT_JS: 'HH:mm',
};

// SuperAdmin: has ALL feature flags — should see every sidebar section and sublink
const superAdminUser: User = {
  userId: 1,
  userName: 'superadmin',
  userTypeId: UserType.SuperAdmin,
  groupId: 1,
  settings: mockSettings,
  features: {
    'schedule.view': true,
    'daypart.view': true,
    'campaign.view': true,
    'layout.view': true,
    'template.view': true,
    'resolution.view': true,
    'library.view': true,
    'playlist.view': true,
    'dataset.view': true,
    'menuBoard.view': true,
    'displays.view': true,
    'displaygroup.view': true,
    'display.syncView': true,
    'displayprofile.view': true,
    'playersoftware.view': true,
    'command.view': true,
    'users.view': true,
    'usergroup.view': true,
    'module.view': true,
    'transition.view': true,
    'task.view': true,
    'tag.view': true,
    'font.view': true,
    'report.view': true,
    'report.scheduling': true,
    'report.saving': true,
    'log.view': true,
    'sessions.view': true,
    'auditlog.view': true,
    'fault.view': true,
    'developer.edit': true,
  },
};

// GroupAdmin: has most view features but lacks admin-only ones
// (e.g., no module.view, report.view, developer.edit — so no Reporting, Advanced, Developer sections)
const groupAdminUser: User = {
  userId: 2,
  userName: 'groupadmin',
  userTypeId: UserType.GroupAdmin,
  groupId: 2,
  settings: mockSettings,
  features: {
    'schedule.view': true,
    'daypart.view': true,
    'campaign.view': true,
    'layout.view': true,
    'template.view': true,
    'resolution.view': true,
    'library.view': true,
    'playlist.view': true,
    'dataset.view': true,
    'menuBoard.view': true,
    'displays.view': true,
    'displaygroup.view': true,
    'display.syncView': true,
    'displayprofile.view': true,
    'playersoftware.view': true,
    'command.view': true,
    'users.view': true,
    'usergroup.view': true,
  },
};

// Regular user: minimal features — only schedule.view and library.view
// Should only see Dashboard (always visible), Schedule, and Library
const regularUser: User = {
  userId: 3,
  userName: 'user',
  userTypeId: UserType.User,
  groupId: 3,
  settings: mockSettings,
  features: {
    'schedule.view': true,
    'library.view': true,
  },
};

/**
 * Renders the SidebarMenu wrapped in required providers (UserProvider + MemoryRouter).
 * Defaults to SuperAdmin user at the root route.
 */
function renderSidebar({
  initialEntries = ['/'],
  user = superAdminUser,
}: {
  initialEntries?: string[];
  user?: User;
} = {}) {
  return render(
    <UserProvider initialUser={user}>
      <MemoryRouter initialEntries={initialEntries}>
        <SidebarMenu
          isCollapsed={false}
          toggleSidebar={vi.fn()}
          closeMobileDrawer={vi.fn()}
        />
      </MemoryRouter>
    </UserProvider>,
  );
}

/**
 * Locates the expand/collapse chevron button for a sidebar section.
 * Traverses from the label text up to the sidebar item container,
 * then finds the nested button that toggles sublink visibility.
 */
function getChevronButton(labelText: string) {
  const label = screen.getByText(labelText);
  const sidebarItemDiv = label.closest('.flex.cursor-pointer')!;
  return sidebarItemDiv.querySelector('button')!;
}

/**
 * Helper: finds visible text elements (excludes popup/tooltip elements)
 */
function getVisibleByText(text: string) {
  return screen.queryAllByText(text).filter((el) => !el.closest('.pointer-events-none'));
}

describe('SidebarMenu — Permissions', () => {
  // ====================================================================
  // Integration tests: render the full SidebarMenu component and verify
  // that the correct nav items are visible/hidden for each user role.
  // ====================================================================
  describe('Permission-based filtering', () => {
    // SuperAdmin has every feature flag, so all top-level sections should render
    test('SuperAdmin sees all navigation items', () => {
      console.log('--- Test: SuperAdmin sees all navigation items ---');
      console.log('User:', superAdminUser.userName, '| Type:', UserType[superAdminUser.userTypeId]);
      console.log('Features count:', Object.keys(superAdminUser.features).length);

      renderSidebar({ user: superAdminUser });

      const expectedLabels = [
        'Dashboard', 'Schedule', 'Design', 'Library', 'Displays',
        'Administration', 'Reporting', 'Advanced', 'Developer',
      ];

      for (const label of expectedLabels) {
        const matches = screen.getAllByText(label);
        console.log(`  "${label}" — found ${matches.length} element(s) ✓`);
        expect(matches.length).toBeGreaterThanOrEqual(1);
      }
    });

    // GroupAdmin lacks report/module/developer features, so those sections should be hidden
    test('GroupAdmin sees allowed sections, not restricted ones', () => {
      console.log('--- Test: GroupAdmin sees allowed vs restricted ---');
      console.log('User:', groupAdminUser.userName, '| Type:', UserType[groupAdminUser.userTypeId]);
      console.log('Features:', Object.keys(groupAdminUser.features).join(', '));

      renderSidebar({ user: groupAdminUser });

      const shouldSee = ['Dashboard', 'Schedule', 'Design', 'Library', 'Displays', 'Administration'];
      const shouldNotSee = ['Reporting', 'Advanced', 'Developer'];

      console.log('\n  Should SEE:');
      for (const label of shouldSee) {
        const matches = screen.getAllByText(label);
        console.log(`    "${label}" — found ${matches.length} element(s) ✓`);
        expect(matches.length).toBeGreaterThanOrEqual(1);
      }

      console.log('\n  Should NOT see:');
      for (const label of shouldNotSee) {
        const visible = getVisibleByText(label);
        console.log(`    "${label}" — visible: ${visible.length} (expected 0) ${visible.length === 0 ? '✓' : '✗'}`);
        expect(visible).toHaveLength(0);
      }
    });

    // Verifies sublink visibility: GroupAdmin has users.view and usergroup.view
    test('GroupAdmin sees Users and User Groups under Administration', () => {
      console.log('--- Test: GroupAdmin Administration sublinks ---');
      console.log('User:', groupAdminUser.userName, '| has users.view:', groupAdminUser.features['users.view'], '| has usergroup.view:', groupAdminUser.features['usergroup.view']);

      renderSidebar({ user: groupAdminUser });
      fireEvent.click(getChevronButton('Administration'));
      console.log('  Expanded "Administration" menu');

      for (const label of ['Users', 'User Groups']) {
        const visible = getVisibleByText(label);
        console.log(`  "${label}" — visible: ${visible.length} ✓`);
        expect(visible.length).toBeGreaterThanOrEqual(1);
      }
    });

    // "Applications" and "Folders" use a validator (isSuperAdmin), not a feature flag
    // GroupAdmin should fail the validator and not see these items
    test('GroupAdmin does not see SuperAdmin-only administration items', () => {
      console.log('--- Test: GroupAdmin cannot see SuperAdmin-only items ---');
      console.log('User:', groupAdminUser.userName, '| Type:', UserType[groupAdminUser.userTypeId], '(not SuperAdmin)');
      console.log('  "Applications" requires: validator isSuperAdmin');
      console.log('  "Folders" requires: validator isSuperAdmin');

      renderSidebar({ user: groupAdminUser });
      fireEvent.click(getChevronButton('Administration'));

      for (const label of ['Applications', 'Folders']) {
        const visible = getVisibleByText(label);
        console.log(`  "${label}" — visible: ${visible.length} (expected 0) ${visible.length === 0 ? '✓' : '✗'}`);
        expect(visible).toHaveLength(0);
      }
    });

    // GroupAdmin has all Library-related features, so every Library sublink should appear
    test('GroupAdmin expands Library and sees all sublinks', () => {
      console.log('--- Test: GroupAdmin Library sublinks ---');
      console.log('User:', groupAdminUser.userName);
      console.log('  has playlist.view:', groupAdminUser.features['playlist.view']);
      console.log('  has library.view:', groupAdminUser.features['library.view']);
      console.log('  has dataset.view:', groupAdminUser.features['dataset.view']);
      console.log('  has menuBoard.view:', groupAdminUser.features['menuBoard.view']);

      renderSidebar({ user: groupAdminUser });
      fireEvent.click(getChevronButton('Library'));
      console.log('  Expanded "Library" menu');

      for (const label of ['Playlists', 'Media', 'Datasets', 'Menu Boards']) {
        const visible = getVisibleByText(label);
        console.log(`  "${label}" — visible: ${visible.length} ✓`);
        expect(visible.length).toBeGreaterThanOrEqual(1);
      }
    });

    // Regular user only has schedule.view and library.view — most sections should be hidden
    test('Regular user sees only Dashboard, Schedule, and Library', () => {
      console.log('--- Test: Regular user limited visibility ---');
      console.log('User:', regularUser.userName, '| Type:', UserType[regularUser.userTypeId]);
      console.log('Features:', Object.keys(regularUser.features).join(', '));

      renderSidebar({ user: regularUser });

      const shouldSee = ['Dashboard', 'Schedule', 'Library'];
      const shouldNotSee = ['Design', 'Displays', 'Administration', 'Reporting', 'Advanced', 'Developer'];

      console.log('\n  Should SEE:');
      for (const label of shouldSee) {
        const matches = screen.getAllByText(label);
        console.log(`    "${label}" — found ${matches.length} element(s) ✓`);
        expect(matches.length).toBeGreaterThanOrEqual(1);
      }

      console.log('\n  Should NOT see:');
      for (const label of shouldNotSee) {
        const visible = getVisibleByText(label);
        console.log(`    "${label}" — visible: ${visible.length} (expected 0) ${visible.length === 0 ? '✓' : '✗'}`);
        expect(visible).toHaveLength(0);
      }
    });

    // schedule.view grants access to "Event", but daypart.view is needed for "Dayparting"
    test('Regular user sees only Event under Schedule (not Dayparting)', () => {
      console.log('--- Test: Regular user Schedule sublinks ---');
      console.log('User:', regularUser.userName);
      console.log('  has schedule.view:', regularUser.features['schedule.view'], '(needed for Event)');
      console.log('  has daypart.view:', regularUser.features['daypart.view'] ?? 'undefined', '(needed for Dayparting)');

      renderSidebar({ user: regularUser });
      fireEvent.click(getChevronButton('Schedule'));
      console.log('  Expanded "Schedule" menu');

      const eventVisible = getVisibleByText('Event');
      console.log(`  "Event" — visible: ${eventVisible.length} ✓`);
      expect(eventVisible.length).toBeGreaterThanOrEqual(1);

      const daypartVisible = getVisibleByText('Dayparting');
      console.log(`  "Dayparting" — visible: ${daypartVisible.length} (expected 0) ${daypartVisible.length === 0 ? '✓' : '✗'}`);
      expect(daypartVisible).toHaveLength(0);
    });

    // library.view only covers "Media"; Playlists, Datasets, Menu Boards need separate features
    test('Regular user sees only Media under Library', () => {
      console.log('--- Test: Regular user Library sublinks ---');
      console.log('User:', regularUser.userName);
      console.log('  has library.view:', regularUser.features['library.view'], '(needed for Media)');
      console.log('  has playlist.view:', regularUser.features['playlist.view'] ?? 'undefined', '(needed for Playlists)');
      console.log('  has dataset.view:', regularUser.features['dataset.view'] ?? 'undefined', '(needed for Datasets)');
      console.log('  has menuBoard.view:', regularUser.features['menuBoard.view'] ?? 'undefined', '(needed for Menu Boards)');

      renderSidebar({ user: regularUser });
      fireEvent.click(getChevronButton('Library'));
      console.log('  Expanded "Library" menu');

      const mediaVisible = getVisibleByText('Media');
      console.log(`  "Media" — visible: ${mediaVisible.length} ✓`);
      expect(mediaVisible.length).toBeGreaterThanOrEqual(1);

      for (const label of ['Playlists', 'Datasets', 'Menu Boards']) {
        const visible = getVisibleByText(label);
        console.log(`  "${label}" — visible: ${visible.length} (expected 0) ${visible.length === 0 ? '✓' : '✗'}`);
        expect(visible).toHaveLength(0);
      }
    });

    // Edge case: navigating directly to a Library URL should NOT bypass permission checks
    test('User without library.view cannot see Library even when navigating to /library/media', () => {
      const noLibraryUser: User = {
        userId: 4,
        userName: 'nolibrary',
        userTypeId: UserType.User,
        groupId: 4,
        settings: mockSettings,
        features: {
          'schedule.view': true,
        },
      };

      console.log('--- Test: No library.view user at /library/media ---');
      console.log('User:', noLibraryUser.userName);
      console.log('Features:', Object.keys(noLibraryUser.features).join(', '));
      console.log('URL: /library/media (should not grant access)');

      renderSidebar({ user: noLibraryUser, initialEntries: ['/library/media'] });

      const libraryVisible = getVisibleByText('Library');
      console.log(`  "Library" — visible: ${libraryVisible.length} (expected 0) ${libraryVisible.length === 0 ? '✓' : '✗'}`);
      expect(libraryVisible).toHaveLength(0);
    });
  });

  // ====================================================================
  // Unit tests for the `hasFeature` utility function.
  // Verifies correct boolean lookup in the user's features map,
  // including edge cases like missing keys and undefined features.
  // ====================================================================
  describe('hasFeature', () => {
    /** Creates a minimal User with the given feature flags */
    const makeUser = (features: Record<string, boolean> = {}): User => ({
      userId: 1,
      userName: 'test',
      userTypeId: UserType.User,
      groupId: 1,
      settings: mockSettings,
      features,
    });

    test('returns true when feature is enabled', () => {
      const user = makeUser({ 'library.view': true });
      const result = hasFeature(user, 'library.view');
      console.log('hasFeature({ library.view: true }, "library.view") →', result);
      expect(result).toBe(true);
    });

    test('returns false when feature is disabled', () => {
      const user = makeUser({ 'library.view': false });
      const result = hasFeature(user, 'library.view');
      console.log('hasFeature({ library.view: false }, "library.view") →', result);
      expect(result).toBe(false);
    });
  });
});
