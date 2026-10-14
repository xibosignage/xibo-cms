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

import { renderDashboardRouter } from './helpers/renderDashboardRouter';

import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';

// =============================================================================
// Module mocks
// =============================================================================

// Each dashboard is a stub with its own heading, so a test sees which one was chosen
// without rendering (or mocking the data of) the real pages.
vi.mock('@/pages/Dashboard/StatusDashboard/StatusDashboard', () => ({
  default: () => <h1>Status Dashboard stub</h1>,
}));
vi.mock('@/pages/Dashboard/IconDashboard/IconDashboard', () => ({
  default: () => <h1>Icon Dashboard stub</h1>,
}));
vi.mock('@/pages/Dashboard/MediaDashboard/MediaDashboard', () => ({
  default: () => <h1>Media Dashboard stub</h1>,
}));
vi.mock('@/pages/Dashboard/PlaylistDashboard/PlaylistDashboard', () => ({
  default: () => <h1>Playlist Dashboard stub</h1>,
}));

// =============================================================================
// Helpers local to this file
// =============================================================================

const ALL = ['Status', 'Icon', 'Media', 'Playlist'] as const;
type Dashboard = (typeof ALL)[number];

// The dashboards are lazy-loaded, so the first check waits for the chosen one to appear;
// then the other three must be absent.
const expectOnly = async (shown: Dashboard) => {
  await screen.findByRole('heading', { name: `${shown} Dashboard stub` });
  for (const other of ALL.filter((d) => d !== shown)) {
    expect(
      screen.queryByRole('heading', { name: `${other} Dashboard stub` }),
    ).not.toBeInTheDocument();
  }
};

// =============================================================================
// Tests
// =============================================================================

// Personas: displayManager has dashboard.status, playlistManager has dashboard.playlist, and
// no non-admin persona has dashboard.media.manager, so that one is added. "Lacks the feature"
// cases never use Super Admin, who passes every feature check.
describe('Dashboard router - choosing the home dashboard', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  test('Status home page with the Status permission shows the Status Dashboard', async () => {
    renderDashboardRouter(PERSONAS.displayManager, 'statusdashboard.view');

    await expectOnly('Status');
  });

  test('Status home page without the permission falls back to the Icon Dashboard', async () => {
    renderDashboardRouter(PERSONAS.user, 'statusdashboard.view');

    await expectOnly('Icon');
  });

  test('Media Manager home page with the permission shows the Media Manager Dashboard', async () => {
    renderDashboardRouter(
      buildCurrentUser(PERSONAS.contentManager, {
        features: { 'dashboard.media.manager': true },
      }),
      'mediamanager.view',
    );

    await expectOnly('Media');
  });

  test('Media Manager home page without the permission falls back to the Icon Dashboard', async () => {
    renderDashboardRouter(PERSONAS.contentManager, 'mediamanager.view');

    await expectOnly('Icon');
  });

  test('Playlist home page with the permission shows the Playlist Dashboard', async () => {
    renderDashboardRouter(PERSONAS.playlistManager, 'playlistdashboard.view');

    await expectOnly('Playlist');
  });

  test('Playlist home page without the permission falls back to the Icon Dashboard', async () => {
    renderDashboardRouter(PERSONAS.user, 'playlistdashboard.view');

    await expectOnly('Icon');
  });

  test('Icon home page shows the Icon Dashboard', async () => {
    renderDashboardRouter(PERSONAS.superAdmin, 'icondashboard.view');

    await expectOnly('Icon');
  });

  // '' is what a user saved without a home page carries; `??` alone wouldn't catch it (K2).
  test.each([
    ['not set', undefined],
    ['blank', ''],
    ['unrecognised', 'reports.view'],
  ])('a home page that is %s shows the Icon Dashboard', async (_label, homePageId) => {
    renderDashboardRouter(PERSONAS.superAdmin, homePageId);

    await expectOnly('Icon');
  });

  test('a Super Admin whose home page is Status sees the Status Dashboard', async () => {
    renderDashboardRouter(PERSONAS.superAdmin, 'statusdashboard.view');

    await expectOnly('Status');
  });
});
