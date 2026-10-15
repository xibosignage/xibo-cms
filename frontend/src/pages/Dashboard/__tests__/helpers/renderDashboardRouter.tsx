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

import { render } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';

import DashboardRouter from '../../DashboardRouter';

import { UserProvider } from '@/context/UserContext';
import type { User } from '@/types/user';

// Renders the dashboard router for a user whose home page is `homePageId`. Pass undefined
// to leave the home page unset. The four dashboards are stubbed in the test file, so this
// shows which one was chosen.
export const renderDashboardRouter = (base: User, homePageId?: string) =>
  render(
    <UserProvider initialUser={{ ...base, homePageId }}>
      <MemoryRouter>
        <DashboardRouter />
      </MemoryRouter>
    </UserProvider>,
  );
