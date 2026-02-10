import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';

import SidebarMenu from './SideBar';

import { I18nextProvider } from 'react-i18next';

const mockToggleSidebar = vi.fn();

describe('Sidebar Menu (The Navigation Bar)', () => {

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('should show the correct menu names when open', () => {
    render(
      <MemoryRouter>
        <SidebarMenu
          isCollapsed={false}
          toggleSidebar={mockToggleSidebar}
        />
      </MemoryRouter>
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
      'Settings'
    ];

    expectedMenuItems.forEach((name) => {
      const elements = screen.getAllByText(name);
      expect(elements[0]).toBeInTheDocument();
    });
  });

  it('should expand the main menus and display the submenus', () => {
    render(
      <MemoryRouter>
        <SidebarMenu
          isCollapsed={false}
          toggleSidebar={mockToggleSidebar}
        />
      </MemoryRouter>
    );

    const menuStructure = [
      {
        main: 'Schedule',
        subs: ['Event', 'Dayparting']
      },
      {
        main: 'Design',
        subs: ['Campaign', 'Layouts', 'Templates', 'Resolutions']
      },
      {
        main: 'Library',
        subs: ['Playlists', 'Media', 'Datasets']
      },
      {
        main: 'Displays',
        subs: ['Add Displays', 'Display Groups', 'Sync Groups', 'Commands']
      },
      {
        main: 'Administration',
        subs: ['Users', 'User Groups', 'Applications', 'Modules', 'Transitions', 'Tasks', 'Tags', 'Folders', 'Fonts']
      },
      {
        main: 'Reporting',
        subs: ['All Reports', 'Report Schedules', 'Saved Reports']
      },
      {
        main: 'Advanced',
        subs: ['Log', 'Sessions', 'Audit Trail', 'Report Fault']
      }
    ];

    menuStructure.forEach((group) => {
      const mainLabels = screen.getAllByText(group.main);
      const mainButton = mainLabels[0];

      fireEvent.click(mainButton!);

      group.subs.forEach((subItem) => {
        const subLabels = screen.getAllByText(subItem);
        expect(subLabels[0]).toBeVisible();
      });
    });

    const settingsLabels = screen.getAllByText('Settings');
    expect(settingsLabels[0]).toBeVisible();

    const mediaLink = screen.getByRole('link', { name: 'Media' });
    expect(mediaLink).toHaveAttribute('href', '/library/media');
  });

  it('should try to close when the hamburger button is clicked', () => {
    render(
      <MemoryRouter>
        <SidebarMenu
          isCollapsed={false}
          toggleSidebar={mockToggleSidebar}
        />
      </MemoryRouter>
    );

    const buttons = screen.getAllByRole('button');
    const toggleButton = buttons[0];

    fireEvent.click(toggleButton!);

    expect(mockToggleSidebar).toHaveBeenCalledTimes(1);
  });

  it('should hide the text labels when collapsed', () => {
    render(
      <MemoryRouter>
        <SidebarMenu 
          isCollapsed={true} 
          toggleSidebar={mockToggleSidebar} 
        />
      </MemoryRouter>
    );

    const mainLabel = screen.queryByText((content, element) => {
      return element?.tagName.toLowerCase() === 'span' && content === 'Dashboard';
    });

    expect(mainLabel).not.toBeInTheDocument();
  });
});