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
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  buildAgendaEvent,
  buildAgendaLayout,
  buildAgendaResponse,
  SINGLE_AGENDA_EVENT,
} from '../../../fixtures/agenda';

import { renderAgendaModal } from './helpers/renderAgendaModal';

import { fetchAgendaEvents } from '@/services/eventApi';
import { testQueryClient } from '@/setupTests';
import { hasFeature } from '@/utils/permissions';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/components/ui/modals/Modal');
vi.mock('@/services/eventApi');
vi.mock('@/utils/permissions', () => ({ hasFeature: vi.fn().mockReturnValue(true) }));

// The event editor is a four-step wizard with its own suite. All that matters
// here is which event the agenda handed it, and what the agenda does when the
// editor reports back. The real one would also fire a dozen unmocked requests.
vi.mock('@/components/ui/modals/ScheduleEventModal', () => ({
  default: ({
    mode,
    event,
    onClose,
    onSaved,
  }: {
    mode?: string;
    event?: { eventId: number };
    onClose: () => void;
    onSaved?: () => void;
  }) => (
    <div role="dialog" aria-label="Schedule event editor">
      <p>{`Editing event ${event?.eventId} in ${mode} mode`}</p>
      <button type="button" onClick={() => onSaved?.()}>
        Save the event
      </button>
      <button type="button" onClick={onClose}>
        Cancel the edit
      </button>
    </div>
  ),
}));

// =============================================================================
// Helpers
// =============================================================================

/** Select a scheduled layout and open the event editor from its breadcrumb. */
const openEditorFor = async (
  user: ReturnType<typeof userEvent.setup>,
  layoutName: RegExp,
): Promise<void> => {
  await user.click(await screen.findByRole('row', { name: layoutName }));
  await user.click(screen.getByRole('button', { name: 'Schedule' }));
};

// =============================================================================
// Tests
// =============================================================================

describe('AgendaModal - editing an event from the agenda', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(hasFeature).mockReturnValue(true);
    vi.mocked(fetchAgendaEvents).mockResolvedValue(SINGLE_AGENDA_EVENT);
  });

  // The agenda fabricates the event it hands the editor from an id alone
  // (AgendaModal.tsx:1443). If the wrong id goes through, a perfectly healthy
  // edit form opens for the wrong event and nothing on screen says so.
  test('the breadcrumb opens the editor for the event that was clicked', async () => {
    const user = userEvent.setup();
    const twoEvents = buildAgendaResponse(
      [
        buildAgendaEvent({ eventId: 901, layoutId: 100 }),
        buildAgendaEvent({ eventId: 902, layoutId: 200 }),
      ],
      [
        buildAgendaLayout({ layoutId: 100, layout: 'Morning Welcome' }),
        buildAgendaLayout({ layoutId: 200, layout: 'Afternoon Loop' }),
      ],
    );
    vi.mocked(fetchAgendaEvents).mockResolvedValue(twoEvents);
    renderAgendaModal();

    await openEditorFor(user, /Afternoon Loop/);

    expect(await screen.findByRole('dialog', { name: 'Schedule event editor' })).toHaveTextContent(
      'Editing event 902 in edit mode',
    );
  });

  // After a save the agenda has to re-read the day, or the user is looking at
  // the schedule as it was before their own edit. The agenda invalidates by
  // query key, so this only passes if that key actually matches this query.
  test("saving the edit re-reads the day's events", async () => {
    const user = userEvent.setup();
    vi.mocked(fetchAgendaEvents)
      .mockResolvedValueOnce(SINGLE_AGENDA_EVENT)
      .mockResolvedValue(
        buildAgendaResponse(
          [buildAgendaEvent({ eventId: 901, layoutId: 100 })],
          [buildAgendaLayout({ layoutId: 100, layout: 'Renamed After Save' })],
        ),
      );
    renderAgendaModal();

    await openEditorFor(user, /Morning Welcome/);
    await user.click(screen.getByRole('button', { name: 'Save the event' }));

    expect(await screen.findByRole('row', { name: /Renamed After Save/ })).toBeInTheDocument();
    expect(screen.queryByRole('dialog', { name: 'Schedule event editor' })).not.toBeInTheDocument();
  });

  test("closing the editor without saving leaves the day's events alone", async () => {
    const user = userEvent.setup();
    renderAgendaModal();

    await openEditorFor(user, /Morning Welcome/);
    const callsBeforeCancel = vi.mocked(fetchAgendaEvents).mock.calls.length;

    await user.click(screen.getByRole('button', { name: 'Cancel the edit' }));

    expect(screen.queryByRole('dialog', { name: 'Schedule event editor' })).not.toBeInTheDocument();
    expect(vi.mocked(fetchAgendaEvents).mock.calls).toHaveLength(callsBeforeCancel);
  });

  // Two separate gates gutter the edit link. First: the viewer's permission.
  test('a viewer who cannot modify schedules gets no way in from the breadcrumb', async () => {
    const user = userEvent.setup();
    vi.mocked(hasFeature).mockReturnValue(false);
    renderAgendaModal();

    await user.click(await screen.findByRole('row', { name: /Morning Welcome/ }));

    expect(screen.getByText('Schedule')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Schedule' })).not.toBeInTheDocument();
  });

  // Second, and independently: the server's own verdict on this one event.
  test('an event the server marks as not editable cannot be opened either', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchAgendaEvents).mockResolvedValue(
      buildAgendaResponse(
        [buildAgendaEvent({ eventId: 901, layoutId: 100, isEditable: false })],
        [buildAgendaLayout({ layoutId: 100 })],
      ),
    );
    renderAgendaModal();

    await user.click(await screen.findByRole('row', { name: /Morning Welcome/ }));

    expect(screen.getByText('Schedule')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Schedule' })).not.toBeInTheDocument();
  });
});
