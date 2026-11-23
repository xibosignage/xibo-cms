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
import { MemoryRouter } from 'react-router-dom';

import { mockUser } from '../../../../fixtures/user';

import ScheduleEventModal from '@/components/ui/modals/ScheduleEventModal';
import { UserProvider } from '@/context/UserContext';
import { testQueryClient } from '@/setupTests';
import type { Event, EventTypeId } from '@/types/event';
import type { User } from '@/types/user';

export interface RenderScheduleModalProps {
  isOpen?: boolean;
  onClose?: () => void;
  onSaved?: () => void;
  mode?: 'add' | 'schedule' | 'edit';
  eventTypeId?: EventTypeId;
  contentId?: number;
  contentName?: string;
  event?: Event;
  displaySpecificGroupIds?: number[];
  displayGroupIds?: number[];
}

export interface RenderScheduleModalOptions {
  user?: User;
}

export const renderScheduleModal = (
  props: RenderScheduleModalProps = {},
  { user = mockUser }: RenderScheduleModalOptions = {},
) => {
  const {
    isOpen = true,
    onClose = () => {},
    onSaved,
    mode,
    eventTypeId,
    contentId,
    contentName,
    event,
    displaySpecificGroupIds,
    displayGroupIds,
  } = props;

  return render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={user}>
        <MemoryRouter>
          <ScheduleEventModal
            isOpen={isOpen}
            onClose={onClose}
            onSaved={onSaved}
            mode={mode}
            eventTypeId={eventTypeId}
            contentId={contentId}
            contentName={contentName}
            event={event}
            displaySpecificGroupIds={displaySpecificGroupIds}
            displayGroupIds={displayGroupIds}
          />
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );
};
