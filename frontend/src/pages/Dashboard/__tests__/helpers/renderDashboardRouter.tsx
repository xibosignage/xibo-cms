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
