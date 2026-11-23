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

import { lazy, Suspense } from 'react';

import { useUserContext } from '@/context/UserContext';
import { hasFeature } from '@/utils/permissions';

const StatusDashboard = lazy(() => import('./StatusDashboard/StatusDashboard'));
const IconDashboard = lazy(() => import('./IconDashboard/IconDashboard'));
const MediaDashboard = lazy(() => import('./MediaDashboard/MediaDashboard'));
const PlaylistDashboard = lazy(() => import('./PlaylistDashboard/PlaylistDashboard'));

export default function DashboardRouter() {
  const { user } = useUserContext();
  const homePageId = user?.homePageId ?? 'icondashboard.view';

  const Dashboard = (() => {
    switch (homePageId) {
      case 'statusdashboard.view':
        return hasFeature(user, 'dashboard.status') ? StatusDashboard : IconDashboard;
      case 'icondashboard.view':
        return IconDashboard;
      case 'mediamanager.view':
        return hasFeature(user, 'dashboard.media.manager') ? MediaDashboard : IconDashboard;
      case 'playlistdashboard.view':
        return hasFeature(user, 'dashboard.playlist') ? PlaylistDashboard : IconDashboard;
      default:
        return IconDashboard;
    }
  })();

  return (
    <Suspense
      fallback={<div className="flex h-full items-center justify-center text-gray-400">...</div>}
    >
      <Dashboard />
    </Suspense>
  );
}
