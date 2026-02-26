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

import { render, screen } from '@testing-library/react';
import { createElement } from 'react';
import { MemoryRouter } from 'react-router-dom';

import SidebarMenu from '../SideBar';

import superAdminData from './__fixtures__/xibo_admin.json';
import displayManagerData from './__fixtures__/xibo_display_manager.json';
import groupAdminData from './__fixtures__/xibo_group_admin.json';
import contentManagerData from './__fixtures__/xibo_content_manager.json';
import playlistManagerData from './__fixtures__/xibo_playlist_manager.json';
import scheduleManagerData from './__fixtures__/xibo_schedule_manager.json';
import regularUserData from './__fixtures__/xibo_user.json';

import { UserProvider } from '@/context/UserContext';
import { UserType } from '@/types/user';
import type { User, UserFeatures } from '@/types/user';

export const mockSettings = {
  defaultTimezone: 'Europe/London',
  defaultLanguage: 'en_GB',
  DATE_FORMAT_JS: 'YYYY-MM-DD HH:mm',
  TIME_FORMAT_JS: 'HH:mm',
};

/**
 * Generic factory — build any User with optional overrides.
 */
export function createUser(
  overrides: Partial<Omit<User, 'features'>> & { features?: Partial<UserFeatures> } = {},
): User {
  const { features, ...rest } = overrides;
  return {
    userId: 1,
    userName: 'test_user',
    userTypeId: UserType.User,
    groupId: 1,
    settings: mockSettings,
    features: (features ?? {}) as UserFeatures,
    ...rest,
  };
}

/** Creates a SuperAdmin user from API /user/me */
export function createSuperAdmin(featureOverrides: Partial<UserFeatures> = {}): User {
  return {
    userId: superAdminData.userId,
    userName: superAdminData.userName,
    userTypeId: superAdminData.userTypeId as UserType,
    groupId: superAdminData.groupId,
    settings: mockSettings,
    features: { ...superAdminData.features, ...featureOverrides },
  };
}

/** Creates a GroupAdmin user from API /user/me */
export function createGroupAdmin(featureOverrides: Partial<UserFeatures> = {}): User {
  return {
    userId: groupAdminData.userId,
    userName: groupAdminData.userName,
    userTypeId: groupAdminData.userTypeId as UserType,
    groupId: groupAdminData.groupId,
    settings: mockSettings,
    features: { ...groupAdminData.features, ...featureOverrides },
  };
}

/** Creates a Regular User from API /user/me */
export function createRegularUser(featureOverrides: Partial<UserFeatures> = {}): User {
  return {
    userId: regularUserData.userId,
    userName: regularUserData.userName,
    userTypeId: regularUserData.userTypeId as UserType,
    groupId: regularUserData.groupId,
    settings: mockSettings,
    features: { ...regularUserData.features, ...featureOverrides },
  };
}

/** Creates a Content Manager user from API /user/me */
export function createContentManager(featureOverrides: Partial<UserFeatures> = {}): User {
  return {
    userId: contentManagerData.userId,
    userName: contentManagerData.userName,
    userTypeId: contentManagerData.userTypeId as UserType,
    groupId: contentManagerData.groupId,
    settings: mockSettings,
    features: { ...contentManagerData.features, ...featureOverrides },
  };
}

/** Creates a Playlist Manager user from API /user/me */
export function createPlaylistManager(featureOverrides: Partial<UserFeatures> = {}): User {
  return {
    userId: playlistManagerData.userId,
    userName: playlistManagerData.userName,
    userTypeId: playlistManagerData.userTypeId as UserType,
    groupId: playlistManagerData.groupId,
    settings: mockSettings,
    features: { ...playlistManagerData.features, ...featureOverrides },
  };
}

/** Creates a Schedule Manager user from API /user/me */
export function createScheduleManager(featureOverrides: Partial<UserFeatures> = {}): User {
  return {
    userId: scheduleManagerData.userId,
    userName: scheduleManagerData.userName,
    userTypeId: scheduleManagerData.userTypeId as UserType,
    groupId: scheduleManagerData.groupId,
    settings: mockSettings,
    features: { ...scheduleManagerData.features, ...featureOverrides },
  };
}

/** Creates a Display Manager user from API /user/me */
export function createDisplayManager(featureOverrides: Partial<UserFeatures> = {}): User {
  return {
    userId: displayManagerData.userId,
    userName: displayManagerData.userName,
    userTypeId: displayManagerData.userTypeId as UserType,
    groupId: displayManagerData.groupId,
    settings: mockSettings,
    features: { ...displayManagerData.features, ...featureOverrides },
  };
}

// Pre-built users
export const superAdminUser = createSuperAdmin();
export const groupAdminUser = createGroupAdmin();
export const regularUser = createRegularUser();
export const contentManagerUser = createContentManager();
export const playlistManagerUser = createPlaylistManager();
export const scheduleManagerUser = createScheduleManager();
export const displayManagerUser = createDisplayManager();

export function renderSidebar({
  isCollapsed = false,
  toggleSidebar = vi.fn(),
  closeMobileDrawer = vi.fn(),
  initialRoute = '/',
  user = superAdminUser,
}: {
  isCollapsed?: boolean;
  toggleSidebar?: () => void;
  closeMobileDrawer?: () => void;
  initialRoute?: string;
  user?: User;
} = {}) {
  return render(
    createElement(
      UserProvider,
      { initialUser: user, children: undefined },
      createElement(
        MemoryRouter,
        { initialEntries: [initialRoute] },
        createElement(SidebarMenu, {
          isCollapsed,
          toggleSidebar,
          closeMobileDrawer,
        }),
      ),
    ),
  );
}

export function getChevronButton(labelText: string) {
  const label = screen.getByText(labelText);
  const sidebarItemDiv = label.closest('.flex.cursor-pointer')!;
  return sidebarItemDiv.querySelector('button')!;
}

export function getVisibleByText(text: string) {
  return screen.queryAllByText(text).filter((el) => !el.closest('.pointer-events-none'));
}
