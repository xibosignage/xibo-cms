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

import { screen, waitFor } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeEach, afterEach, describe, test, expect } from 'vitest';

import {
  AGENDA_DAY_START,
  ATRIUM_GROUP,
  buildAgendaEvent,
  buildAgendaLayout,
  buildAgendaResponse,
  FOYER_GROUP,
  GEO_DAY,
  IPSWICH_FENCE_AS_FEATURE_COLLECTION,
  POINT_IN_BERLIN,
  POINT_IN_IPSWICH,
  SINGLE_AGENDA_EVENT,
  TWO_DISPLAY_GROUPS,
} from '../../../fixtures/agenda';

import { renderAgendaModal } from './helpers/renderAgendaModal';

import { fetchAgendaEvents } from '@/services/eventApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
//
// The real Modal is used deliberately here (no vi.mock): AgendaModal passes no
// footer actions, so its only close control is the header X that comes from
// `showCloseButton` — which the manual Modal mock does not implement.
//
// Leaflet is NOT stubbed either: the real map mounts cleanly in JSDOM (verified),
// so the map toggle is exercised for real.
// =============================================================================

vi.mock('@/services/eventApi');

// =============================================================================
// Helpers
// =============================================================================

/** The parameters of the most recent agenda request. */
const capturedParams = () => {
  const calls = vi.mocked(fetchAgendaEvents).mock.calls;
  return calls[calls.length - 1]![0];
};

/** Open the agenda on a day with one layout and wait for the row to arrive. */
const renderWithOneEvent = async () => {
  renderAgendaModal();
  await screen.findByRole('row', { name: /Morning Welcome/ });
};

/** The layout names currently shown, in render order (the Name column). */
const rowLayoutNames = () =>
  screen
    .getAllByRole('row')
    .slice(1)
    .map((row) => (row as HTMLTableRowElement).cells[1]?.textContent?.trim() ?? '');

/**
 * The sort control lives in a bare <div onClick> inside the <th> (AgendaModal.tsx
 * :893) — no role and no accessible name, so it cannot be reached by role. Scoped
 * to the column header rather than queried globally. Remove this helper once the
 * control becomes a real <button>.
 */
const sortControlFor = (columnName: RegExp) =>
  screen.getByRole('columnheader', { name: columnName }).querySelector('div.cursor-pointer')!;

// =============================================================================
// Tests
// =============================================================================

describe('AgendaModal - acting on the agenda', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(fetchAgendaEvents).mockResolvedValue(SINGLE_AGENDA_EVENT);
  });

  // ---------------------------------------------------------------------------
  // Switching display group
  // ---------------------------------------------------------------------------

  // The tab strip is how one agenda covers several groups of screens. Picking a
  // tab has to re-ask the API for that group — the day stays the same.
  test('choosing another display group asks for the same day against that group', async () => {
    const user = userEvent.setup();
    const atrium = buildAgendaResponse(
      [buildAgendaEvent({ eventId: 950, layoutId: 200, displayGroupId: ATRIUM_GROUP.id })],
      [buildAgendaLayout({ layoutId: 200, layout: 'Atrium Loop' })],
    );
    vi.mocked(fetchAgendaEvents).mockImplementation(({ displayGroupId }) =>
      Promise.resolve(displayGroupId === ATRIUM_GROUP.id ? atrium : SINGLE_AGENDA_EVENT),
    );

    renderAgendaModal({ displayGroups: TWO_DISPLAY_GROUPS });
    await screen.findByRole('row', { name: /Morning Welcome/ });

    await user.click(screen.getByRole('button', { name: ATRIUM_GROUP.name }));

    expect(await screen.findByRole('row', { name: /Atrium Loop/ })).toBeInTheDocument();
    expect(fetchAgendaEvents).toHaveBeenCalledWith(
      expect.objectContaining({
        displayGroupId: ATRIUM_GROUP.id,
        startDate: AGENDA_DAY_START,
      }),
    );
  });

  // ---------------------------------------------------------------------------
  // Point-in-time mode
  // ---------------------------------------------------------------------------

  test('turning on "Specific point in time" reveals the time slider', async () => {
    const user = userEvent.setup();
    await renderWithOneEvent();

    await user.click(screen.getByRole('button', { name: 'Specific point in time' }));

    // The slider labels every two hours through to the end of the day; 23:59 is
    // the last of those and appears only when the slider is showing.
    expect(screen.getByText('23:59')).toBeInTheDocument();
  });

  // The two request shapes come off one ternary (AgendaModal.tsx:1253): a whole
  // day range, or one moment. Asking for a moment must replace the range, not
  // add to it — a request carrying both renders identically here and behaves
  // differently on the server.
  test('asking for a specific point in time replaces the whole-day request', async () => {
    const user = userEvent.setup();
    await renderWithOneEvent();

    await user.click(screen.getByRole('button', { name: 'Specific point in time' }));

    await waitFor(() => {
      expect(capturedParams()).toEqual(
        expect.objectContaining({
          displayGroupId: FOYER_GROUP.id,
          singlePointInTime: 1,
          date: AGENDA_DAY_START,
        }),
      );
    });
    expect(capturedParams()).not.toHaveProperty('startDate');
    expect(capturedParams()).not.toHaveProperty('endDate');
  });

  // The opposite arm of the same ternary, so the test above is shown to
  // discriminate rather than just matching whatever was sent.
  test('a whole-day request does not also name a single point in time', async () => {
    await renderWithOneEvent();

    expect(capturedParams()).not.toHaveProperty('date');
  });

  // ---------------------------------------------------------------------------
  // Selecting a row
  // ---------------------------------------------------------------------------

  test('clicking a scheduled layout reveals the breadcrumb for that event', async () => {
    const user = userEvent.setup();
    await renderWithOneEvent();

    await user.click(screen.getByRole('row', { name: /Morning Welcome/ }));

    expect(screen.getByRole('button', { name: 'Schedule' })).toBeInTheDocument();
  });

  test('clicking the same row a second time hides the breadcrumb again', async () => {
    const user = userEvent.setup();
    await renderWithOneEvent();

    await user.click(screen.getByRole('row', { name: /Morning Welcome/ }));
    await user.click(screen.getByRole('row', { name: /Morning Welcome/ }));

    expect(screen.queryByRole('button', { name: 'Schedule' })).not.toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Sorting
  // ---------------------------------------------------------------------------

  const TWO_LAYOUTS = buildAgendaResponse(
    [
      buildAgendaEvent({ eventId: 1, layoutId: 200 }),
      buildAgendaEvent({ eventId: 2, layoutId: 100 }),
    ],
    [
      buildAgendaLayout({ layoutId: 100, layout: 'Bravo' }),
      buildAgendaLayout({ layoutId: 200, layout: 'Alpha' }),
    ],
  );

  test('sorting by a column reorders the rows by that column', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchAgendaEvents).mockResolvedValue(TWO_LAYOUTS);
    renderAgendaModal();
    await screen.findByRole('row', { name: /Alpha/ });

    await user.click(sortControlFor(/^id$/i));

    expect(rowLayoutNames()).toEqual(['Bravo', 'Alpha']);
  });

  test('clicking the same column again reverses the order', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchAgendaEvents).mockResolvedValue(TWO_LAYOUTS);
    renderAgendaModal();
    await screen.findByRole('row', { name: /Alpha/ });

    await user.click(sortControlFor(/^id$/i));
    await user.click(sortControlFor(/^id$/i));

    expect(rowLayoutNames()).toEqual(['Alpha', 'Bravo']);
  });

  // ---------------------------------------------------------------------------
  // Previewing
  // ---------------------------------------------------------------------------

  test('the preview button opens the layout preview', async () => {
    const user = userEvent.setup();
    await renderWithOneEvent();

    await user.click(screen.getByRole('button', { name: 'Preview Layout' }));

    expect(await screen.findByRole('button', { name: 'Close Preview' })).toBeInTheDocument();
  });

  // The preview button sits inside the row, so without stopPropagation
  // (AgendaModal.tsx:1076) previewing would also select the row behind it.
  test('previewing a layout does not also select its row', async () => {
    const user = userEvent.setup();
    await renderWithOneEvent();

    await user.click(screen.getByRole('button', { name: 'Preview Layout' }));

    expect(screen.queryByRole('button', { name: 'Schedule' })).not.toBeInTheDocument();
  });

  // A layout with no preview token cannot be previewed — the signed URL is built
  // from that token, so offering the button would only produce a broken preview.
  test('a layout with no preview token offers no preview button', async () => {
    const noJwt = buildAgendaResponse(
      [buildAgendaEvent({ eventId: 1, layoutId: 100 })],
      [buildAgendaLayout({ layoutId: 100, previewJwt: undefined })],
    );
    vi.mocked(fetchAgendaEvents).mockResolvedValue(noJwt);
    renderAgendaModal();
    await screen.findByRole('row', { name: /Morning Welcome/ });

    expect(screen.queryByRole('button', { name: 'Preview Layout' })).not.toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Closing
  // ---------------------------------------------------------------------------

  test('the close button closes the agenda', async () => {
    const user = userEvent.setup();
    const onClose = vi.fn();
    renderAgendaModal({ onClose });
    await screen.findByRole('row', { name: /Morning Welcome/ });

    await user.click(screen.getByRole('button', { name: 'Close' }));

    expect(onClose).toHaveBeenCalledTimes(1);
  });

  test('pressing Escape closes the agenda', async () => {
    const user = userEvent.setup();
    const onClose = vi.fn();
    renderAgendaModal({ onClose });
    await screen.findByRole('row', { name: /Morning Welcome/ });

    await user.keyboard('{Escape}');

    expect(onClose).toHaveBeenCalledTimes(1);
  });
});

// =============================================================================
// Filtering the day by location
// =============================================================================

describe('AgendaModal - filtering the day by location', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(fetchAgendaEvents).mockResolvedValue(GEO_DAY);

    // JSDOM has no geolocation implementation at all.
    Object.defineProperty(navigator, 'geolocation', {
      configurable: true,
      value: { getCurrentPosition: vi.fn() },
    });
  });

  afterEach(() => {
    Reflect.deleteProperty(navigator, 'geolocation');
  });

  /** Put a point into the latitude/longitude fields. */
  const enterLocation = async (user: UserEvent, lat: string, lng: string) => {
    await user.type(screen.getByLabelText('Latitude'), lat);
    await user.type(screen.getByLabelText('Longitude'), lng);
  };

  test('the map is hidden until the user asks for it', async () => {
    renderAgendaModal();
    await screen.findByRole('row', { name: /Everywhere/ });

    expect(document.querySelector('.leaflet-container')).toBeNull();
  });

  test('the map button shows the map', async () => {
    const user = userEvent.setup();
    renderAgendaModal();
    await screen.findByRole('row', { name: /Everywhere/ });

    await user.click(screen.getByRole('button', { name: 'Toggle map' }));

    await waitFor(() => {
      expect(document.querySelector('.leaflet-container')).not.toBeNull();
    });
  });

  // The filter is applied in the browser, against each event's stored fence -
  // it never reaches the API. An event fenced somewhere else drops out of the
  // day; an event with no fence always stays.
  test('choosing a point drops the events fenced somewhere else', async () => {
    const user = userEvent.setup();
    renderAgendaModal();
    await screen.findByRole('row', { name: /Everywhere/ });

    await enterLocation(user, POINT_IN_IPSWICH.lat, POINT_IN_IPSWICH.lng);

    await waitFor(() => {
      expect(screen.queryByRole('row', { name: /Berlin Only/ })).not.toBeInTheDocument();
    });
    expect(screen.getByRole('row', { name: /Ipswich Only/ })).toBeInTheDocument();
    expect(screen.getByRole('row', { name: /Everywhere/ })).toBeInTheDocument();
  });

  // The opposite point keeps the opposite event, which proves the filter is
  // actually reading the fences rather than dropping a fixed row.
  test('choosing a point in the other fence keeps the other event instead', async () => {
    const user = userEvent.setup();
    renderAgendaModal();
    await screen.findByRole('row', { name: /Everywhere/ });

    await enterLocation(user, POINT_IN_BERLIN.lat, POINT_IN_BERLIN.lng);

    await waitFor(() => {
      expect(screen.queryByRole('row', { name: /Ipswich Only/ })).not.toBeInTheDocument();
    });
    expect(screen.getByRole('row', { name: /Berlin Only/ })).toBeInTheDocument();
  });

  test('the clear button appears only once a full point has been given', async () => {
    const user = userEvent.setup();
    renderAgendaModal();
    await screen.findByRole('row', { name: /Everywhere/ });

    expect(screen.queryByRole('button', { name: 'Clear location' })).not.toBeInTheDocument();

    await user.type(screen.getByLabelText('Latitude'), POINT_IN_IPSWICH.lat);
    expect(screen.queryByRole('button', { name: 'Clear location' })).not.toBeInTheDocument();

    await user.type(screen.getByLabelText('Longitude'), POINT_IN_IPSWICH.lng);
    expect(await screen.findByRole('button', { name: 'Clear location' })).toBeInTheDocument();
  });

  test('clearing the location brings the filtered-out events back', async () => {
    const user = userEvent.setup();
    renderAgendaModal();
    await screen.findByRole('row', { name: /Everywhere/ });

    await enterLocation(user, POINT_IN_IPSWICH.lat, POINT_IN_IPSWICH.lng);
    await waitFor(() => {
      expect(screen.queryByRole('row', { name: /Berlin Only/ })).not.toBeInTheDocument();
    });

    await user.click(screen.getByRole('button', { name: 'Clear location' }));

    expect(await screen.findByRole('row', { name: /Berlin Only/ })).toBeInTheDocument();
  });

  test('the browser location button fills in the coordinates the browser reports', async () => {
    const user = userEvent.setup();
    vi.mocked(navigator.geolocation.getCurrentPosition).mockImplementation((success) =>
      success({ coords: { latitude: 52.2, longitude: 1.1 } } as GeolocationPosition),
    );
    renderAgendaModal();
    await screen.findByRole('row', { name: /Everywhere/ });

    await user.click(screen.getByRole('button', { name: 'Get browser location' }));

    expect(screen.getByLabelText('Latitude')).toHaveValue(52.2);
    expect(screen.getByLabelText('Longitude')).toHaveValue(1.1);
  });

  // A fence drawn in the CMS map editor is exported as a GeoJSON
  // FeatureCollection, but isPointInGeoJSON reads `geoJSON.coordinates` for that
  // type (AgendaModal.tsx:156) where a FeatureCollection keeps its members in
  // `.features`. The lookup yields undefined, `[].some(...)` is false, and the
  // event silently disappears from the agenda whenever any location is set.
  // Kept as test.fails (not test.skip) so a real fix surfaces here immediately.
  test.fails(
    'an event fenced with a FeatureCollection survives a point inside that fence',
    async () => {
      const user = userEvent.setup();
      vi.mocked(fetchAgendaEvents).mockResolvedValue(
        buildAgendaResponse(
          [
            buildAgendaEvent({
              eventId: 1,
              layoutId: 100,
              geoLocation: IPSWICH_FENCE_AS_FEATURE_COLLECTION,
            }),
          ],
          [buildAgendaLayout({ layoutId: 100, layout: 'Drawn Fence' })],
        ),
      );
      renderAgendaModal();
      await screen.findByRole('row', { name: /Drawn Fence/ });

      await enterLocation(user, POINT_IN_IPSWICH.lat, POINT_IN_IPSWICH.lng);

      await waitFor(() => {
        expect(screen.getByRole('row', { name: /Drawn Fence/ })).toBeInTheDocument();
      });
    },
  );
});
