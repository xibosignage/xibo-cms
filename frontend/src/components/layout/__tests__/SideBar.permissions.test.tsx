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
import { fireEvent, screen } from '@testing-library/react';

import {
  createGroupAdmin,
  createUser,
  getChevronButton,
  getVisibleByText,
  groupAdminUser,
  displayManagerUser,
  contentManagerUser,
  playlistManagerUser,
  scheduleManagerUser,
  regularUser,
  renderSidebar,
  superAdminUser,
} from './sidebarTestUtils';

import { type User } from '@/types/user';
import { hasFeature } from '@/utils/permissions';

describe('SidebarMenu — Permissions', () => {
  // ====================================================================
  // Integration tests: render the full SidebarMenu component and verify
  // that the correct nav items are visible/hidden for each user role.
  // ====================================================================
  describe('Permission-based filtering', () => {
    // SuperAdmin has every feature flag, so all top-level sections should render
    test('SuperAdmin sees all navigation items', () => {
      renderSidebar({ user: superAdminUser });

      const expectedLabels = [
        'Dashboard',
        'Schedule',
        'Design',
        'Library',
        'Displays',
        'Administration',
        'Reporting',
        'Advanced',
        'Developer',
      ];

      for (const label of expectedLabels) {
        const matches = screen.getAllByText(label);
        expect(matches.length).toBeGreaterThanOrEqual(1);
      }
    });

    // GroupAdmin has schedule/library/layout/playlist/users — no displays, reporting, or advanced
    test('GroupAdmin sees allowed sections, not restricted ones', () => {
      renderSidebar({ user: groupAdminUser });

      const shouldSee = ['Dashboard', 'Schedule', 'Design', 'Library', 'Administration'];
      const shouldNotSee = ['Displays', 'Reporting', 'Advanced', 'Developer'];

      for (const label of shouldSee) {
        const matches = screen.getAllByText(label);
        expect(matches.length).toBeGreaterThanOrEqual(1);
      }

      for (const label of shouldNotSee) {
        const visible = getVisibleByText(label);
        expect(visible).toHaveLength(0);
      }
    });

    // GroupAdmin has users.view but not usergroup.view — only Users sublink should appear
    test('GroupAdmin sees Users but not User Groups under Administration', () => {
      renderSidebar({ user: groupAdminUser });
      fireEvent.click(getChevronButton('Administration'));

      const usersVisible = getVisibleByText('Users');
      expect(usersVisible.length).toBeGreaterThanOrEqual(1);

      const userGroupsVisible = getVisibleByText('User Groups');
      expect(userGroupsVisible).toHaveLength(0);
    });

    // "Applications" and "Folders" use a validator (isSuperAdmin), not a feature flag
    // GroupAdmin should fail the validator and not see these items
    test('GroupAdmin does not see SuperAdmin-only administration items', () => {
      renderSidebar({ user: groupAdminUser });
      fireEvent.click(getChevronButton('Administration'));

      for (const label of ['Applications', 'Folders']) {
        const visible = getVisibleByText(label);
        expect(visible).toHaveLength(0);
      }
    });

    // GroupAdmin has library.view and playlist.view but not dataset.view or menuBoard.view
    test('GroupAdmin expands Library and sees Media and Playlists only', () => {
      renderSidebar({ user: groupAdminUser });
      fireEvent.click(getChevronButton('Library'));

      for (const label of ['Media', 'Playlists']) {
        const visible = getVisibleByText(label);
        expect(visible.length).toBeGreaterThanOrEqual(1);
      }

      for (const label of ['Datasets', 'Menu Boards']) {
        const visible = getVisibleByText(label);
        expect(visible).toHaveLength(0);
      }
    });

    // schedule.view grants Event but not Dayparting (no daypart.view)
    test('GroupAdmin sees only Event under Schedule', () => {
      renderSidebar({ user: groupAdminUser });
      fireEvent.click(getChevronButton('Schedule'));

      const eventVisible = getVisibleByText('Event');
      expect(eventVisible.length).toBeGreaterThanOrEqual(1);

      const daypartVisible = getVisibleByText('Dayparting');
      expect(daypartVisible).toHaveLength(0);
    });

    // layout.view grants Layouts but not Campaign, Templates, or Resolutions
    test('GroupAdmin sees only Layouts under Design', () => {
      renderSidebar({ user: groupAdminUser });
      fireEvent.click(getChevronButton('Design'));

      const layoutsVisible = getVisibleByText('Layouts');
      expect(layoutsVisible.length).toBeGreaterThanOrEqual(1);

      for (const label of ['Campaign', 'Templates', 'Resolutions']) {
        const visible = getVisibleByText(label);
        expect(visible).toHaveLength(0);
      }
    });

    // Regular user has schedule/library/layout/playlist/users — sees Design too, but not Displays/Admin/Reporting/Advanced/Developer
    test('Regular user sees correct top-level sections', () => {
      renderSidebar({ user: regularUser });

      const shouldSee = ['Dashboard', 'Schedule', 'Design', 'Library'];
      const shouldNotSee = ['Displays', 'Administration', 'Reporting', 'Advanced', 'Developer'];

      for (const label of shouldSee) {
        const matches = screen.getAllByText(label);
        expect(matches.length).toBeGreaterThanOrEqual(1);
      }

      for (const label of shouldNotSee) {
        const visible = getVisibleByText(label);
        expect(visible).toHaveLength(0);
      }
    });

    // schedule.view grants access to "Event", but daypart.view is needed for "Dayparting"
    test('Regular user sees only Event under Schedule (not Dayparting)', () => {
      renderSidebar({ user: regularUser });
      fireEvent.click(getChevronButton('Schedule'));

      const eventVisible = getVisibleByText('Event');
      expect(eventVisible.length).toBeGreaterThanOrEqual(1);

      const daypartVisible = getVisibleByText('Dayparting');
      expect(daypartVisible).toHaveLength(0);
    });

    // library.view covers Media, playlist.view covers Playlists; Datasets and Menu Boards need separate features
    test('Regular user sees Media and Playlists under Library', () => {
      renderSidebar({ user: regularUser });
      fireEvent.click(getChevronButton('Library'));

      for (const label of ['Media', 'Playlists']) {
        const visible = getVisibleByText(label);
        expect(visible.length).toBeGreaterThanOrEqual(1);
      }

      for (const label of ['Datasets', 'Menu Boards']) {
        const visible = getVisibleByText(label);
        expect(visible).toHaveLength(0);
      }
    });

    // layout.view grants Layouts but not Campaign, Templates, or Resolutions
    test('Regular user sees only Layouts under Design', () => {
      renderSidebar({ user: regularUser });
      fireEvent.click(getChevronButton('Design'));

      const layoutsVisible = getVisibleByText('Layouts');
      expect(layoutsVisible.length).toBeGreaterThanOrEqual(1);

      for (const label of ['Campaign', 'Templates', 'Resolutions']) {
        const visible = getVisibleByText(label);
        expect(visible).toHaveLength(0);
      }
    });

    // users.view is true but canViewUsers validator also requires GroupAdmin or SuperAdmin type
    // Regular user (userTypeId: 3) fails the type check — Administration must be hidden
    test('Regular user cannot see Administration despite having users.view', () => {
      renderSidebar({ user: regularUser });

      const adminVisible = getVisibleByText('Administration');
      expect(adminVisible).toHaveLength(0);
    });

    // canViewUsers is a strict AND: feature flag AND user type must both pass.
    // The previous test proves: feature true + wrong type → hidden.
    // This test proves the complement: correct type + feature false → hidden.
    // Together they show neither condition alone is sufficient.
    test('GroupAdmin with users.view disabled cannot see Administration', () => {
      const user = createGroupAdmin({ 'users.view': false });

      renderSidebar({ user });

      const adminVisible = getVisibleByText('Administration');
      expect(adminVisible).toHaveLength(0);
    });

    // Edge case: navigating directly to a Library URL should NOT bypass permission checks
    test('User without library.view cannot see Library even when navigating to /library/media', () => {
      const noLibraryUser = createUser({
        userId: 4,
        userName: 'nolibrary',
        groupId: 4,
        features: { 'schedule.view': true },
      });

      renderSidebar({ user: noLibraryUser, initialRoute: '/library/media' });

      const libraryVisible = getVisibleByText('Library');
      expect(libraryVisible).toHaveLength(0);
    });

    // Display Manager: display-focused user — sees Schedule, Displays, Reporting, Advanced
    // but NOT Design, Library, Administration, or Developer
    test('Display Manager sees correct top-level sections', () => {
      renderSidebar({ user: displayManagerUser });

      const shouldSee = ['Dashboard', 'Schedule', 'Displays', 'Reporting', 'Advanced'];
      const shouldNotSee = ['Design', 'Library', 'Administration', 'Developer'];

      for (const label of shouldSee) {
        const matches = screen.getAllByText(label);
        expect(matches.length).toBeGreaterThanOrEqual(1);
      }

      for (const label of shouldNotSee) {
        const visible = getVisibleByText(label);
        expect(visible).toHaveLength(0);
      }
    });

    // schedule.view grants Event but not Dayparting (requires daypart.view)
    test('Display Manager sees only Event under Schedule', () => {
      renderSidebar({ user: displayManagerUser });
      fireEvent.click(getChevronButton('Schedule'));

      const eventVisible = getVisibleByText('Event');
      expect(eventVisible.length).toBeGreaterThanOrEqual(1);

      const daypartVisible = getVisibleByText('Dayparting');
      expect(daypartVisible).toHaveLength(0);
    });

    // Display Manager has display-related features: displays.view, displaygroup.view,
    // displayprofile.view, playersoftware.view, command.view — but NOT display.syncView
    test('Display Manager sees correct sublinks under Displays', () => {
      renderSidebar({ user: displayManagerUser });
      fireEvent.click(getChevronButton('Displays'));

      for (const label of [
        'Add Displays',
        'Display Groups',
        'Settings',
        'Player Versions',
        'Commands',
      ]) {
        const visible = getVisibleByText(label);
        expect(visible.length).toBeGreaterThanOrEqual(1);
      }

      const syncVisible = getVisibleByText('Sync Groups');
      expect(syncVisible).toHaveLength(0);
    });

    // report.view grants All Reports but not Report Schedules or Saved Reports
    test('Display Manager sees only All Reports under Reporting', () => {
      renderSidebar({ user: displayManagerUser });
      fireEvent.click(getChevronButton('Reporting'));

      const allReportsVisible = getVisibleByText('All Reports');
      expect(allReportsVisible.length).toBeGreaterThanOrEqual(1);

      for (const label of ['Report Schedules', 'Saved Reports']) {
        const visible = getVisibleByText(label);
        expect(visible).toHaveLength(0);
      }
    });

    // log.view grants Log but not Sessions, Audit Trail, or Report Fault
    test('Display Manager sees only Log under Advanced', () => {
      renderSidebar({ user: displayManagerUser });
      fireEvent.click(getChevronButton('Advanced'));

      const logVisible = getVisibleByText('Log');
      expect(logVisible.length).toBeGreaterThanOrEqual(1);

      for (const label of ['Sessions', 'Audit Trail', 'Report Fault']) {
        const visible = getVisibleByText(label);
        expect(visible).toHaveLength(0);
      }
    });
  });

  // ====================================================================
  // Edge cases: minimal and inconsistent user data.
  // These guard against regressions in the filtering logic when the
  // feature object is empty, sparse, or missing entirely.
  // ====================================================================
  describe('Edge cases — minimal and missing data', () => {
    const ALL_SECTIONS = [
      'Schedule',
      'Design',
      'Library',
      'Displays',
      'Administration',
      'Reporting',
      'Advanced',
      'Developer',
    ];

    // A user with no features at all should see only Dashboard.
    // All other sections require at least one gated sublink to be visible.
    test('User with no features sees only Dashboard', () => {
      const user = createUser({ features: {} });

      renderSidebar({ user });

      const dashboardVisible = screen.getAllByText('Dashboard');
      expect(dashboardVisible.length).toBeGreaterThanOrEqual(1);

      for (const label of ALL_SECTIONS) {
        const visible = getVisibleByText(label);
        expect(visible).toHaveLength(0);
      }
    });

    // A user with a single feature should only unlock the corresponding section.
    // All other sections must remain hidden.
    test('User with only schedule.view sees Dashboard and Schedule only', () => {
      const user = createUser({ features: { 'schedule.view': true } });

      renderSidebar({ user });

      for (const label of ['Dashboard', 'Schedule']) {
        const visible = screen.getAllByText(label);
        expect(visible.length).toBeGreaterThanOrEqual(1);
      }

      const hiddenSections = ALL_SECTIONS.filter((s) => s !== 'Schedule');
      for (const label of hiddenSections) {
        const visible = getVisibleByText(label);
        expect(visible).toHaveLength(0);
      }
    });

    // NOTE: createUser({ features: undefined }) does NOT produce features: undefined —
    // the factory applies `features ?? {}` so undefined becomes {}.
    // To simulate a truly malformed API response the factory must be bypassed.

    // features: undefined — API omitted the field entirely.
    // hasFeature guards: `if (!user.features) return false`
    test('User with undefined features object sees only Dashboard without crashing', () => {
      const user = { ...createUser(), features: undefined } as unknown as User;

      renderSidebar({ user });

      const dashboardVisible = screen.getAllByText('Dashboard');
      expect(dashboardVisible.length).toBeGreaterThanOrEqual(1);

      for (const label of ALL_SECTIONS) {
        const visible = getVisibleByText(label);
        expect(visible).toHaveLength(0);
      }
    });

    // features: null — API returned an explicit null (common in JSON APIs).
    // hasFeature guards: `if (!user.features) return false` — null is falsy, handled identically.
    test('User with null features object sees only Dashboard without crashing', () => {
      const user = { ...createUser(), features: null } as unknown as User;

      renderSidebar({ user });

      const dashboardVisible = screen.getAllByText('Dashboard');
      expect(dashboardVisible.length).toBeGreaterThanOrEqual(1);

      for (const label of ALL_SECTIONS) {
        const visible = getVisibleByText(label);
        expect(visible).toHaveLength(0);
      }
    });
  });

  // ====================================================================
  // Parent visibility derived from child flags.
  // No parent section has its own feature gate — visibility is derived
  // entirely from children. A single accessible child must surface the parent.
  // This guards against a common regression where a "primary" child flag
  // is accidentally treated as the parent gate.
  // ====================================================================
  describe('Parent visibility derived from child flags', () => {
    // report.view is the "obvious" flag for Reporting, but report.saving is equally valid.
    // If only report.saving is true, Reporting must still appear — with only Saved Reports inside.
    test('Reporting appears when only report.saving is true (not report.view)', () => {
      const user = createUser({ features: { 'report.saving': true } });

      renderSidebar({ user });

      const reportingVisible = screen.getAllByText('Reporting');
      expect(reportingVisible.length).toBeGreaterThanOrEqual(1);

      fireEvent.click(getChevronButton('Reporting'));

      const savedReportsVisible = getVisibleByText('Saved Reports');
      expect(savedReportsVisible.length).toBeGreaterThanOrEqual(1);

      for (const label of ['All Reports', 'Report Schedules']) {
        const visible = getVisibleByText(label);
        expect(visible).toHaveLength(0);
      }
    });

    // Users/Applications/etc. are the "obvious" Administration items,
    // but font.view alone is enough to surface the Administration section.
    test('Administration appears when only font.view is true', () => {
      const user = createUser({ features: { 'font.view': true } });

      renderSidebar({ user });

      const adminVisible = screen.getAllByText('Administration');
      expect(adminVisible.length).toBeGreaterThanOrEqual(1);

      fireEvent.click(getChevronButton('Administration'));

      const fontsVisible = getVisibleByText('Fonts');
      expect(fontsVisible.length).toBeGreaterThanOrEqual(1);

      for (const label of ['Users', 'User Groups', 'Modules', 'Transitions', 'Tasks', 'Tags']) {
        const visible = getVisibleByText(label);
        expect(visible).toHaveLength(0);
      }
    });
  });

  // ====================================================================
  // Parent section hidden when all children hidden.
  // If every child is filtered out, the parent must not render as an
  // empty container. Tested by taking a real user with known visible
  // children and disabling exactly those children via factory overrides.
  // ====================================================================
  describe('Parent section hidden when all children hidden', () => {
    // GroupAdmin has library.view and playlist.view true; dataset.view and
    // menuBoard.view are already false. Disabling the remaining two removes
    // all Library children — Library itself must disappear.
    test('Library hidden when all Library children disabled', () => {
      const user = createGroupAdmin({ 'library.view': false, 'playlist.view': false });

      renderSidebar({ user });

      const libraryVisible = getVisibleByText('Library');
      expect(libraryVisible).toHaveLength(0);

      // Other accessible sections are unaffected
      for (const label of ['Schedule', 'Design', 'Administration']) {
        const visible = screen.getAllByText(label);
        expect(visible.length).toBeGreaterThanOrEqual(1);
      }
    });

    // GroupAdmin has schedule.view true; daypart.view is already false.
    // Disabling schedule.view removes the only Schedule child (Event) —
    // Schedule itself must disappear.
    test('Schedule hidden when all Schedule children disabled', () => {
      const user = createGroupAdmin({ 'schedule.view': false });

      renderSidebar({ user });

      const scheduleVisible = getVisibleByText('Schedule');
      expect(scheduleVisible).toHaveLength(0);

      // Other accessible sections are unaffected
      for (const label of ['Design', 'Library', 'Administration']) {
        const visible = screen.getAllByText(label);
        expect(visible.length).toBeGreaterThanOrEqual(1);
      }
    });

    // GroupAdmin has layout.view true; campaign.view, template.view, resolution.view
    // are already false. Disabling layout.view removes the only Design child (Layouts) —
    // Design itself must disappear.
    test('Design hidden when all Design children disabled', () => {
      const user = createGroupAdmin({ 'layout.view': false });

      renderSidebar({ user });

      const designVisible = getVisibleByText('Design');
      expect(designVisible).toHaveLength(0);

      // Other accessible sections are unaffected
      for (const label of ['Schedule', 'Library', 'Administration']) {
        const visible = screen.getAllByText(label);
        expect(visible.length).toBeGreaterThanOrEqual(1);
      }
    });
  });

  // ====================================================================
  // Content Manager: a Regular User whose permissions are scoped to
  // content creation only — Design, Library, and Administration (via
  // tag.view).
  // ====================================================================
  describe('Content Manager', () => {
    // Content Manager has Design, Library, and tag.view — no Schedule, Displays, Reporting, Advanced
    test('Content Manager sees correct top-level sections', () => {
      renderSidebar({ user: contentManagerUser });

      const shouldSee = ['Dashboard', 'Design', 'Library', 'Administration'];
      const shouldNotSee = ['Schedule', 'Displays', 'Reporting', 'Advanced', 'Developer'];

      for (const label of shouldSee) {
        const matches = screen.getAllByText(label);
        expect(matches.length).toBeGreaterThanOrEqual(1);
      }

      for (const label of shouldNotSee) {
        const visible = getVisibleByText(label);
        expect(visible).toHaveLength(0);
      }
    });

    // Content Manager has all four Design features: campaign.view, layout.view,
    // template.view, resolution.view — all sublinks should appear
    test('Content Manager sees all sublinks under Design', () => {
      renderSidebar({ user: contentManagerUser });
      fireEvent.click(getChevronButton('Design'));

      for (const label of ['Campaign', 'Layouts', 'Templates', 'Resolutions']) {
        const visible = getVisibleByText(label);
        expect(visible.length).toBeGreaterThanOrEqual(1);
      }
    });

    // library.view → Media, playlist.view → Playlists, dataset.view → Datasets
    // menuBoard.view is false — Menu Boards must not appear
    test('Content Manager sees Media, Playlists and Datasets under Library but not Menu Boards', () => {
      renderSidebar({ user: contentManagerUser });
      fireEvent.click(getChevronButton('Library'));

      for (const label of ['Media', 'Playlists', 'Datasets']) {
        const visible = getVisibleByText(label);
        expect(visible.length).toBeGreaterThanOrEqual(1);
      }

      const menuBoardsVisible = getVisibleByText('Menu Boards');
      expect(menuBoardsVisible).toHaveLength(0);
    });

    // tag.view alone must surface the Administration section.
    // Only Tags should appear — no Users, User Groups, Modules, Transitions, Tasks, or Fonts.
    test('Administration appears for Content Manager via tag.view only — Tags is the only sublink', () => {
      renderSidebar({ user: contentManagerUser });
      fireEvent.click(getChevronButton('Administration'));

      const tagsVisible = getVisibleByText('Tags');
      expect(tagsVisible.length).toBeGreaterThanOrEqual(1);

      for (const label of [
        'Users',
        'User Groups',
        'Modules',
        'Transitions',
        'Tasks',
        'Fonts',
        'Settings',
        'Applications',
        'Folders',
      ]) {
        const visible = getVisibleByText(label);
        expect(visible).toHaveLength(0);
      }
    });
  });

  // ====================================================================
  // Playlist Manager
  // ====================================================================
  describe('Playlist Manager', () => {
    // playlist.view is false — the sidebar Playlists link requires it.
    // dashboard.playlist is a dashboard widget flag, not a sidebar nav flag.
    // No sidebar section has a passing feature gate, so only Dashboard renders.
    test('Playlist Manager sees only Dashboard', () => {
      renderSidebar({ user: playlistManagerUser });

      const dashboardVisible = screen.getAllByText('Dashboard');
      expect(dashboardVisible.length).toBeGreaterThanOrEqual(1);

      for (const label of [
        'Schedule',
        'Design',
        'Library',
        'Displays',
        'Administration',
        'Reporting',
        'Advanced',
        'Developer',
      ]) {
        const visible = getVisibleByText(label);
        expect(visible).toHaveLength(0);
      }
    });
  });

  // ====================================================================
  // Schedule Manager: scoped entirely to event scheduling.
  // ====================================================================
  describe('Schedule Manager', () => {
    // schedule.view and daypart.view are both true — Schedule is the only
    // accessible section beyond Dashboard
    test('Schedule Manager sees only Dashboard and Schedule', () => {
      renderSidebar({ user: scheduleManagerUser });

      for (const label of ['Dashboard', 'Schedule']) {
        const matches = screen.getAllByText(label);
        expect(matches.length).toBeGreaterThanOrEqual(1);
      }

      for (const label of [
        'Design',
        'Library',
        'Displays',
        'Administration',
        'Reporting',
        'Advanced',
        'Developer',
      ]) {
        const visible = getVisibleByText(label);
        expect(visible).toHaveLength(0);
      }
    });

    test('Schedule Manager sees both Event and Dayparting under Schedule', () => {
      renderSidebar({ user: scheduleManagerUser });
      fireEvent.click(getChevronButton('Schedule'));

      for (const label of ['Event', 'Dayparting']) {
        const visible = getVisibleByText(label);
        expect(visible.length).toBeGreaterThanOrEqual(1);
      }
    });
  });

  // ====================================================================
  // Unit tests for the `hasFeature` utility function.
  // Verifies correct boolean lookup in the user's features map,
  // including edge cases like missing keys and undefined features.
  // ====================================================================
  describe('hasFeature', () => {
    test('returns true when feature is enabled', () => {
      const user = createUser({ features: { 'library.view': true } });
      const result = hasFeature(user, 'library.view');
      expect(result).toBe(true);
    });

    test('returns false when feature is disabled', () => {
      const user = createUser({ features: { 'library.view': false } });
      const result = hasFeature(user, 'library.view');
      expect(result).toBe(false);
    });
  });
});
