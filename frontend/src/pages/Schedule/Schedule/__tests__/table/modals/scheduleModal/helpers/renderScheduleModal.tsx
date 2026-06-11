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
