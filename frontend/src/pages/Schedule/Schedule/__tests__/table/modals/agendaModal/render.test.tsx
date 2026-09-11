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

import { screen, within } from '@testing-library/react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  AGENDA_DAY_END,
  AGENDA_DAY_START,
  ATRIUM_GROUP,
  buildAgendaCampaign,
  buildAgendaDisplayGroup,
  buildAgendaEvent,
  buildAgendaLayout,
  buildAgendaResponse,
  EMPTY_AGENDA,
  FOYER_GROUP,
  SINGLE_AGENDA_EVENT,
  TWO_DISPLAY_GROUPS,
} from '../../../fixtures/agenda';
import { mockUserInSydney } from '../../../fixtures/user';

import { renderAgendaModal } from './helpers/renderAgendaModal';

import { fetchAgendaEvents } from '@/services/eventApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/components/ui/modals/Modal');
vi.mock('@/services/eventApi');

// =============================================================================
// Tests
// =============================================================================

describe('AgendaModal - what the user sees when it opens', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(fetchAgendaEvents).mockResolvedValue(SINGLE_AGENDA_EVENT);
  });

  // The heading has to name the day, because the agenda is always "one day for
  // one display group" and the user got here by clicking a square on a calendar.
  test('the heading names the day the agenda is for', async () => {
    renderAgendaModal();

    expect(await screen.findByRole('heading', { name: /Agenda View/ })).toHaveTextContent(
      'Tuesday, 17 Mar 2026',
    );
  });

  // The core of the modal: the layouts scheduled that day, under a heading for
  // their event type.
  test("the day's scheduled layouts are listed under their event type", async () => {
    renderAgendaModal();

    expect(await screen.findByRole('heading', { name: 'Layouts', level: 3 })).toBeInTheDocument();
    expect(screen.getByRole('row', { name: /Morning Welcome/ })).toBeInTheDocument();
  });

  // The count badge sits next to the type heading, so a user can see how much is
  // scheduled without counting rows.
  test('the event type heading is accompanied by a count of its events', async () => {
    const twoLayouts = buildAgendaResponse(
      [
        buildAgendaEvent({ eventId: 1, layoutId: 100 }),
        buildAgendaEvent({ eventId: 2, layoutId: 100 }),
      ],
      [buildAgendaLayout({ layoutId: 100 })],
    );
    vi.mocked(fetchAgendaEvents).mockResolvedValue(twoLayouts);
    renderAgendaModal();

    const heading = await screen.findByRole('heading', { name: 'Layouts', level: 3 });
    expect(heading.parentElement).toHaveTextContent('2');
  });

  // Each event type gets its own table rather than one mixed list.
  test('events of different types are split into separate tables', async () => {
    const mixed = buildAgendaResponse(
      [
        buildAgendaEvent({ eventId: 1, layoutId: 100, eventTypeId: 1 }),
        buildAgendaEvent({ eventId: 2, layoutId: 100, eventTypeId: 3 }),
      ],
      [buildAgendaLayout({ layoutId: 100 })],
    );
    vi.mocked(fetchAgendaEvents).mockResolvedValue(mixed);
    renderAgendaModal();

    expect(await screen.findByRole('heading', { name: 'Layouts', level: 3 })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Overlay Layouts', level: 3 })).toBeInTheDocument();
  });

  // The columns the agenda table offers.
  test('the table offers the agenda columns', async () => {
    renderAgendaModal();
    await screen.findByRole('row', { name: /Morning Welcome/ });

    const headers = screen.getAllByRole('columnheader').map((h) => h.textContent?.trim());
    expect(headers).toEqual([
      'ID',
      'Name',
      'Status',
      'From Date',
      'To Date',
      'Duration',
      'Display Order',
      'Priority',
    ]);
  });

  // The API returns a stub ({ layout: name }) instead of a full layout record for
  // layouts this user has no permission to see. Those must still occupy a row -
  // the time is booked either way - but be labelled rather than shown blank.
  test('a layout the user has no permission to see is labelled "Private Item"', async () => {
    const withPrivate = buildAgendaResponse(
      [buildAgendaEvent({ eventId: 1, layoutId: 999 })],
      // layout 999 deliberately absent from the layouts map
      [],
    );
    vi.mocked(fetchAgendaEvents).mockResolvedValue(withPrivate);
    renderAgendaModal();

    expect(await screen.findByText('Private Item')).toBeInTheDocument();
  });

  // The agenda endpoint returns unix seconds, and the cell has to render them in
  // the viewer's own timezone and format. The event below starts at 09:00 UTC,
  // which is 20:00 the same day in Sydney — so a conversion that never happened
  // shows a different time, and a format without a time token would hide it.
  test("from and to are shown in the viewer's own timezone and date format", async () => {
    renderAgendaModal({}, { user: mockUserInSydney });

    const row = await screen.findByRole('row', { name: /Morning Welcome/ });
    expect(row).toHaveTextContent('17/03/2026 20:00');
  });

  // An always-on event has no meaningful start time, so the From Date cell shows
  // a badge instead of a date.
  test('an always-on event shows "Always" in place of a start time', async () => {
    const always = buildAgendaResponse(
      [buildAgendaEvent({ eventId: 1, layoutId: 100, isAlways: 1 })],
      [buildAgendaLayout({ layoutId: 100 })],
    );
    vi.mocked(fetchAgendaEvents).mockResolvedValue(always);
    renderAgendaModal();

    expect(await screen.findByText('Always')).toBeInTheDocument();
  });

  // The right-hand sidebar explains which display groups the day's events reach.
  test("the display groups the day's events belong to are listed in the sidebar", async () => {
    renderAgendaModal();

    const heading = await screen.findByRole('heading', { name: 'Display Groups', level: 3 });
    expect(within(heading.closest('section')!).getByText(FOYER_GROUP.name)).toBeInTheDocument();
  });

  test('campaigns are listed in the sidebar when the day has campaign events', async () => {
    const withCampaign = buildAgendaResponse(
      [buildAgendaEvent({ eventId: 1, layoutId: 100, campaignId: 55 })],
      [buildAgendaLayout({ layoutId: 100 })],
      [buildAgendaDisplayGroup()],
      [buildAgendaCampaign({ campaignId: 55, campaign: 'Spring Campaign' })],
    );
    vi.mocked(fetchAgendaEvents).mockResolvedValue(withCampaign);
    renderAgendaModal();

    const heading = await screen.findByRole('heading', { name: 'Campaigns', level: 3 });
    expect(within(heading.closest('section')!).getByText('Spring Campaign')).toBeInTheDocument();
  });

  // The opposite case, so the test above is shown to discriminate: with no
  // campaigns in the response the sidebar section is absent, not empty.
  test('the campaigns sidebar section is absent when no event belongs to a campaign', async () => {
    renderAgendaModal();
    await screen.findByRole('heading', { name: 'Display Groups', level: 3 });

    expect(screen.queryByRole('heading', { name: 'Campaigns', level: 3 })).not.toBeInTheDocument();
  });

  // The tab strip only earns its space when there is more than one group to
  // switch between.
  test('a tab is offered for each display group the page passed in', async () => {
    renderAgendaModal({ displayGroups: TWO_DISPLAY_GROUPS });
    await screen.findByRole('row', { name: /Morning Welcome/ });

    expect(screen.getByRole('button', { name: FOYER_GROUP.name })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: ATRIUM_GROUP.name })).toBeInTheDocument();
  });

  test('no display group tabs are shown when there is only one group', async () => {
    renderAgendaModal();
    await screen.findByRole('row', { name: /Morning Welcome/ });

    expect(screen.queryByRole('button', { name: FOYER_GROUP.name })).not.toBeInTheDocument();
  });

  test('a day with nothing scheduled says so', async () => {
    vi.mocked(fetchAgendaEvents).mockResolvedValue(EMPTY_AGENDA);
    renderAgendaModal();

    expect(await screen.findByText('No events for this day.')).toBeInTheDocument();
  });

  test('a failed fetch shows an error message instead of the agenda', async () => {
    vi.mocked(fetchAgendaEvents).mockRejectedValue(new Error('Network Error'));
    renderAgendaModal();

    expect(await screen.findByText('Failed to load agenda events.')).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // What the modal asks the API for. This is the boundary that decides whether
  // the agenda shows the right day for the right screens at all.
  // ---------------------------------------------------------------------------

  // The whole of the chosen day, for the first display group, as a date range.
  test("opening the agenda asks the API for the whole of that day's events", async () => {
    renderAgendaModal();
    await screen.findByRole('row', { name: /Morning Welcome/ });

    expect(fetchAgendaEvents).toHaveBeenCalledWith(
      expect.objectContaining({
        displayGroupId: FOYER_GROUP.id,
        singlePointInTime: 0,
        startDate: AGENDA_DAY_START,
        endDate: AGENDA_DAY_END,
      }),
    );
  });

  // With several groups available the modal has to pick one to open on, and it
  // opens on the first - not on no group, which would fetch nothing.
  test('the agenda opens on the first display group when several are available', async () => {
    renderAgendaModal({ displayGroups: TWO_DISPLAY_GROUPS });
    await screen.findByRole('row', { name: /Morning Welcome/ });

    expect(fetchAgendaEvents).toHaveBeenCalledWith(
      expect.objectContaining({ displayGroupId: FOYER_GROUP.id }),
    );
  });

  // Nothing to fetch for: the modal must not ask the API for display group 0.
  test('no agenda is requested when the page passed no display groups at all', async () => {
    renderAgendaModal({ displayGroups: [] });

    expect(await screen.findByText('No display group selected.')).toBeInTheDocument();
    expect(fetchAgendaEvents).not.toHaveBeenCalled();
  });
});
