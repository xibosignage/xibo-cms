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

import { QueryClientProvider } from '@tanstack/react-query';
import { render } from '@testing-library/react';
import type { DateTime } from 'luxon';
import { MemoryRouter } from 'react-router-dom';

import { AGENDA_DATE, ONE_DISPLAY_GROUP } from '../../../../fixtures/agenda';
import { mockUser } from '../../../../fixtures/user';

import { UserProvider } from '@/context/UserContext';
import { AgendaModal } from '@/pages/Schedule/Schedule/components/AgendaModal';
import { testQueryClient } from '@/setupTests';
import type { User } from '@/types/user';

export interface RenderAgendaModalProps {
  date?: DateTime;
  displayGroups?: { id: number; name: string }[];
  onClose?: () => void;
}

export interface RenderAgendaModalOptions {
  user?: User;
}

/**
 * Render AgendaModal on its own, the way EventModals renders it: always open,
 * for one day, against the display groups the Events page passed down.
 *
 * MemoryRouter is required — the breadcrumb's layout link uses useNavigate and
 * useLocation.
 */
export const renderAgendaModal = (
  props: RenderAgendaModalProps = {},
  { user = mockUser }: RenderAgendaModalOptions = {},
) => {
  const { date = AGENDA_DATE, displayGroups = ONE_DISPLAY_GROUP, onClose = () => {} } = props;

  return {
    onClose,
    ...render(
      <QueryClientProvider client={testQueryClient}>
        <UserProvider initialUser={user}>
          <MemoryRouter>
            <AgendaModal date={date} displayGroups={displayGroups} onClose={onClose} />
          </MemoryRouter>
        </UserProvider>
      </QueryClientProvider>,
    ),
  };
};
