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

    const menuStructure = [
      {
        main: 'Schedule',
        subs: [
          { name: 'Event', href: '/schedule/view' },
          { name: 'Dayparting', href: '/dayparting/view' },
        ],
      },
      {
        main: 'Design',
        subs: [
          { name: 'Campaign', href: '/campaign/view' },
          { name: 'Layouts', href: '/layout/view' },
          { name: 'Templates', href: '/template/view' },
          { name: 'Resolutions', href: '/resolution/view' },
        ],
      },
      {
        main: 'Library',
        subs: [
          { name: 'Playlists', href: '/playlist/view' },
          { name: 'Media', href: '/library/media' },
          { name: 'Datasets', href: '/dataset/view' },
        ],
      },
      {
        main: 'Displays',
        subs: [
          { name: 'Add Displays', href: '/display/view' },
          { name: 'Display Groups', href: '/displaygroup/view' },
          { name: 'Sync Groups', href: '/syncgroup/view' },
          { name: 'Commands', href: '/command/view' },
        ],
      },
      {
        main: 'Administration',
        subs: [
          { name: 'Users', href: '/user/view' },
          { name: 'User Groups', href: '/group/view' },
          { name: 'Applications', href: '/application/view' },
          { name: 'Modules', href: '/module/view' },
          { name: 'Transitions', href: '/transition/view' },
          { name: 'Tasks', href: '/task/view' },
          { name: 'Tags', href: '/tag/view' },
          { name: 'Folders', href: '/folders/view' },
          { name: 'Fonts', href: '/fonts/view' },
        ],
      },
      {
        main: 'Reporting',
        subs: [
          { name: 'All Reports', href: '/report/view' },
          { name: 'Report Schedules', href: '/report/reportschedule/view' },
          { name: 'Saved Reports', href: '/report/savedreport/view' },
        ],
      },
      {
        main: 'Advanced',
        subs: [
          // Note: "Log" usually points to savedreport based on your earlier HTML dump
          { name: 'Log', href: '/log/view' },
          { name: 'Sessions', href: '/sessions/view' },
          { name: 'Audit Trail', href: '/audit/view' },
          { name: 'Report Fault', href: '/fault/view' },
        ],
      },
      {
        main: 'Developer',
        subs: [{ name: 'Developer', href: '/developer/template/view' }],
      },
    ];

    menuStructure.forEach((group) => {
      const mainLabels = screen.getAllByText(group.main);
      fireEvent.click(mainLabels[0]!);

      group.subs.forEach((subItem) => {
        const link = screen.getByRole('link', { name: new RegExp(subItem.name, 'i') });

        expect(link).toHaveAttribute('href', subItem.href);
        expect(link).toBeVisible();
      });
    });

    const settingsLinks = screen.getAllByRole('link', { name: 'Settings' });

    expect(
      settingsLinks.find((l) => l.getAttribute('href') === '/displayprofile/view'),
    ).toBeVisible();

    expect(settingsLinks.find((l) => l.getAttribute('href') === '/admin/view')).toBeVisible();
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
