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

import { lazy, Suspense } from 'react';

import { useUserContext } from '@/context/UserContext';

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
        return StatusDashboard;
      case 'icondashboard.view':
        return IconDashboard;
      case 'mediamanager.view':
        return MediaDashboard;
      case 'playlistdashboard.view':
        return PlaylistDashboard;
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
