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
 * Sidebar Tests
 *
 * Test includes:
 * 1. Check if main menus are present when sidebar is opened
 * 2. Check submenus under main menus when sidebar is opened
 * 3. Check if sidebar is closed if hamburger icon is clicked
 * 4. Check if no main menus are present when sidebar is closed
 */
import { render, screen, fireEvent } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { describe, it, expect, vi } from 'vitest';

import SidebarMenu from '../SideBar';

import { UserProvider } from '@/context/UserContext';

// --- DEFINE DATA (Just a regular variable now) ---
const mockSettings = {
  defaultTimezone: 'UTC',
  defaultLanguage: 'en',
  DATE_FORMAT_JS: 'YYYY-MM-DD',
  TIME_FORMAT_JS: 'HH:mm',
};

const superAdminUser = {
  userId: 1,
  userName: 'superadmin',
  userTypeId: 1,
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
    'folder.view': true,
    'font.view': true,
    'report.view': true,
    'report.scheduling': true,
    'report.saving': true,
    'log.view': true,
    'session.view': true,
    'auditlog.view': true,
    'fault.view': true,
    'developer.edit': true,
  },
};

const mockToggleSidebar = vi.fn();

describe('Sidebar Menu (The Navigation Bar)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('should show the correct menu names when open', () => {
    render(
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      <UserProvider initialUser={superAdminUser as any}>
        <MemoryRouter>
          <SidebarMenu isCollapsed={false} toggleSidebar={mockToggleSidebar} />
        </MemoryRouter>
      </UserProvider>,
    );

    const expectedMenuItems = [
      'Dashboard',
      'Schedule',
      'Design',
      'Library',
      'Displays',
      'Administration',
      'Reporting',
      'Advanced',
      'Developer',
      'Settings',
    ];

    expectedMenuItems.forEach((name) => {
      const elements = screen.getAllByText(name);
      expect(elements[0]).toBeInTheDocument();
    });
  });

  it('should expand the main menus and display the submenus and its corresponding links', () => {
    render(
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      <UserProvider initialUser={superAdminUser as any}>
        <MemoryRouter>
          <SidebarMenu isCollapsed={false} toggleSidebar={mockToggleSidebar} />
        </MemoryRouter>
      </UserProvider>,
    );

    // Representative sample — one link per group, mixing React Router and external URL types.
    // This proves the sidebar reads appRoutes config and builds correct hrefs for both kinds,
    // without exhaustively re-testing every route (that would just duplicate appRoutes.ts).
    // When a route moves between types (external ↔ React Router) update the href here.
    const sampleLinks = [
      { group: 'Schedule', name: 'Dayparting', href: '/schedule/dayparting' }, // React Router
      { group: 'Design', name: 'Layouts', href: '/design/layout' }, // React Router
      { group: 'Library', name: 'Media', href: '/library/media' }, // React Router
      { group: 'Displays', name: 'Commands', href: '/command/view' }, // external
      { group: 'Administration', name: 'Tags', href: '/tag/view' }, // external
      { group: 'Reporting', name: 'All Reports', href: '/report/view' }, // external
      { group: 'Advanced', name: 'Sessions', href: '/advanced/sessions' }, // React Router
    ];

    sampleLinks.forEach(({ group, name, href }) => {
      const groupLabels = screen.getAllByText(group);
      fireEvent.click(groupLabels[0]!);

      // getAllByRole handles the rare case where a label appears in multiple groups.
      const links = screen.getAllByRole('link', { name: new RegExp(name, 'i') });
      const link = links.find((l) => l.getAttribute('href') === href);

      expect(link).toBeVisible();
    });

    // Developer is a top-level external link (no subLinks) — always visible, not expandable.
    expect(screen.getByRole('link', { name: /developer/i })).toHaveAttribute(
      'href',
      '/developer/template/view',
    );

    // Displays > Display Settings is a React Router link; Administration > Settings is external.
    expect(screen.getByRole('link', { name: 'Display Settings' })).toHaveAttribute(
      'href',
      '/displays/settings',
    );
    expect(screen.getByRole('link', { name: 'Settings' })).toHaveAttribute('href', '/admin/view');
  });

  it('should try to close when the hamburger button is clicked', () => {
    render(
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      <UserProvider initialUser={superAdminUser as any}>
        <MemoryRouter>
          <SidebarMenu isCollapsed={false} toggleSidebar={mockToggleSidebar} />
        </MemoryRouter>
      </UserProvider>,
    );

    const buttons = screen.getAllByRole('button');
    const toggleButton = buttons[0];

    fireEvent.click(toggleButton!);

    expect(mockToggleSidebar).toHaveBeenCalledTimes(1);
  });

  it('should hide the text labels when collapsed', () => {
    render(
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      <UserProvider initialUser={superAdminUser as any}>
        <MemoryRouter>
          <SidebarMenu isCollapsed={true} toggleSidebar={mockToggleSidebar} />
        </MemoryRouter>
      </UserProvider>,
    );

    const mainLabel = screen.queryByText((content, element) => {
      return element?.tagName.toLowerCase() === 'span' && content === 'Dashboard';
    });

    expect(mainLabel).not.toBeInTheDocument();
  });
});
