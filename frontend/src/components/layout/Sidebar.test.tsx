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
      'Developer' 
    ];

    expectedMenuItems.forEach((name) => {
      const elements = screen.getAllByText(name);
      expect(elements[0]).toBeInTheDocument();
    });
  });

  it('should expand the Library menu and show a link to the Media page', () => {
    render(
      <MemoryRouter>
        <SidebarMenu
          isCollapsed={false}
          toggleSidebar={mockToggleSidebar}
        />
      </MemoryRouter>
    );

    const libraryLabels = screen.getAllByText('Library');
    const libraryButton = libraryLabels[0];

    // 2. Click it to expand the menu
    fireEvent.click(libraryButton!);

    // 3. Check if the submenus appeared
    expect(screen.getByText('Playlists')).toBeVisible();
    expect(screen.getByText('Media')).toBeVisible();
    expect(screen.getByText('Datasets')).toBeVisible();

    const mediaLink = screen.getByRole('link', { name: 'Media' });
    
    // This checks if the <a> tag looks like: <a href="/library/media">
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

    // 1. We create a custom search function.
    // "Find an element where the TAG is 'SPAN' and the TEXT is 'Dashboard'"
    const mainLabel = screen.queryByText((content, element) => {
      return element?.tagName.toLowerCase() === 'span' && content === 'Dashboard';
    });

    // 2. Since the sidebar is collapsed, the Main Label span should not exist.
    // (The Popup 'Dashboard' still exists, but we are ignoring it because it's not a span!)
    expect(mainLabel).not.toBeInTheDocument();
  });
});