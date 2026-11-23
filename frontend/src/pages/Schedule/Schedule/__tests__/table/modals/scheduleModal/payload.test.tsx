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

import { fireEvent, screen, waitFor } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { buildEvent } from '../../../fixtures/event';
import {
  ALWAYS_AND_CUSTOM,
  ALWAYS_ONLY,
  mockDaypartRows,
  mockFetchEventById,
  setupScheduleModalMocks,
} from '../../../mocks/api';

import { renderScheduleModal } from './helpers/renderScheduleModal';

import { fetchCommands } from '@/services/commandApi';
import { fetchDaypart } from '@/services/daypartApi';
import { createEvent, updateEvent } from '@/services/eventApi';
import { fetchLayoutCodes } from '@/services/layoutsApi';
import { fetchSyncGroupDisplays } from '@/services/syncGroupApi';
import { testQueryClient } from '@/setupTests';
import { knownFailure } from '@/testUtils/knownFailure';
import { EventTypeId } from '@/types/event';
import type { SyncGroupDisplay } from '@/types/syncGroup';

// ─── Mocks ────────────────────────────────────────────────────────────────────

vi.mock('@/components/ui/modals/Modal');

vi.mock('@/services/eventApi');
vi.mock('@/services/daypartApi');
vi.mock('@/services/resolutionApi');
vi.mock('@/services/layoutsApi');
vi.mock('@/services/campaignApi');
vi.mock('@/services/commandApi');
vi.mock('@/services/mediaApi');
vi.mock('@/services/playlistApi');
vi.mock('@/services/syncGroupApi');
vi.mock('@/services/datasetApi');
vi.mock('@/services/scheduleCriteriaApi');

// A plain input standing in for the date picker, so a test can enter a date.
vi.mock('@/components/ui/forms/DatePickerInput', () => ({
  default: ({
    label,
    value,
    onChange,
  }: {
    label?: string;
    value?: string;
    onChange: (value: string) => void;
  }) => <input aria-label={label} value={value ?? ''} onChange={(e) => onChange(e.target.value)} />,
}));
vi.mock('@/components/ui/GeoScheduleMap', () => ({
  default: () => <div role="img" aria-label="Geo schedule map" />,
}));
vi.mock('@/components/ui/table/DataTable', () => ({
  DataTable: () => <table aria-label="Sync display table" />,
}));
vi.mock('@/pages/Schedule/Schedule/components/DisplayGroupMultiSelect', () => ({
  DisplayGroupMultiSelect: ({
    onChange,
  }: {
    onChange: (v: { displaySpecificGroupIds: number[]; displayGroupIds: number[] }) => void;
  }) => (
    <button onClick={() => onChange({ displaySpecificGroupIds: [10], displayGroupIds: [] })}>
      Pick a display group
    </button>
  ),
}));
vi.mock('@/components/ui/Notification', () => ({
  notify: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
}));
vi.mock('@/utils/permissions', () => ({
  hasFeature: vi.fn().mockReturnValue(true),
}));

// =============================================================================
// Fixtures
// =============================================================================

// The members of a Sync Group, as GET /syncgroup/{id}/displays returns them.
// `displayGroupId` is the field that matters here: it's the display-specific
// display group the CMS keeps for each individual display, and it's what a Sync
// Event has to be scheduled against for the event to reach that player at all.
const SYNC_GROUP_ID = 7;

const SYNC_MEMBERS: SyncGroupDisplay[] = [
  {
    displayId: 501,
    display: 'Foyer Screen',
    syncGroupId: SYNC_GROUP_ID,
    leadDisplayId: 501,
    displayGroupId: 101,
    layoutId: null,
  },
  {
    displayId: 502,
    display: 'Atrium Screen',
    syncGroupId: SYNC_GROUP_ID,
    leadDisplayId: 501,
    displayGroupId: 102,
    layoutId: null,
  },
];

const SYNC_MEMBER_DISPLAY_GROUP_IDS = [101, 102];

// =============================================================================
// Helpers
// =============================================================================

/**
 * Open the modal on a Sync event with the sync group already chosen, and wait
 * until the modal has loaded the group's members. Sync events have no Displays
 * step, so passing contentId lands us straight on the Time step with Finish
 * already available.
 */
const renderSyncEventReadyToSave = async () => {
  renderScheduleModal({
    mode: 'add',
    eventTypeId: EventTypeId.Sync,
    contentId: SYNC_GROUP_ID,
  });

  await screen.findByRole('combobox', { name: 'Dayparting' });
  await waitFor(() => {
    expect(fetchSyncGroupDisplays).toHaveBeenCalled();
  });
};

/** Open the modal on a plain Layout event and pick a display, ready to save. */
const renderLayoutEventReadyToSave = async (user: UserEvent) => {
  renderScheduleModal({
    mode: 'add',
    eventTypeId: EventTypeId.Layout,
    contentId: 42,
  });

  await user.click(await screen.findByRole('button', { name: 'Pick a display group' }));
};

/** The payload object the modal handed to createEvent. */
const capturedPayload = () => {
  const calls = vi.mocked(createEvent).mock.calls;
  return calls[calls.length - 1]![0];
};

// =============================================================================
// Tests
// =============================================================================

// Regression cover for xibosignage/xibo#3927.
//
// Every Schedule Event is delivered to players via the rows in
// `lkscheduledisplaygroup`, which the CMS writes from the `displayGroupIds` the
// save request carries. In 4.5.1 the modal hardcoded that array to [] for Sync
// events, so a Sync Event was saved with no display groups at all -
// ScheduleFactory::getForXmds() then never returned it and the event reached no
// player. The event looked perfectly fine in the Events grid, which is why this
// shipped: nothing in the UI showed the problem, and no test in this suite
// asserted what the modal actually sends.
describe('ScheduleEventModal - what the save request carries', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();

    setupScheduleModalMocks();

    mockDaypartRows(ALWAYS_ONLY);
    vi.mocked(fetchSyncGroupDisplays).mockResolvedValue(SYNC_MEMBERS);
  });

  // The specific fix: a Sync event is scheduled against the display group of
  // each display in the sync group, so the event is delivered to every screen
  // in the group.
  test('saving a Sync event sends the display group of every display in the sync group', async () => {
    const user = userEvent.setup();
    await renderSyncEventReadyToSave();

    await user.click(screen.getByRole('button', { name: 'Finish' }));

    await waitFor(() => {
      expect(createEvent).toHaveBeenCalledWith(
        expect.objectContaining({
          eventTypeId: EventTypeId.Sync,
          syncGroupId: SYNC_GROUP_ID,
          displayGroupIds: SYNC_MEMBER_DISPLAY_GROUP_IDS,
        }),
        expect.any(String),
      );
    });
  });

  // The same requirement stated as an invariant rather than as an exact list:
  // whatever the modal decides to send, a Sync event must never be saved with
  // an empty display group list, because that is silently undeliverable.
  test('a Sync event is never saved with an empty list of display groups', async () => {
    const user = userEvent.setup();
    await renderSyncEventReadyToSave();

    await user.click(screen.getByRole('button', { name: 'Finish' }));

    await waitFor(() => {
      expect(createEvent).toHaveBeenCalledTimes(1);
    });

    const payload = capturedPayload();
    expect(payload.displayGroupIds).not.toHaveLength(0);
  });

  // The Sync branch and the normal branch are two arms of the same ternary, so
  // guard the other arm too - a fix on one side must not break the other. A
  // Layout event has to send the displays and groups the user actually picked,
  // NOT the sync group's members.
  test(
    'saving a Layout event sends the displays and groups the user picked',
    { tags: ['smoke'] },
    async () => {
      const user = userEvent.setup();
      await renderLayoutEventReadyToSave(user);

      await user.click(screen.getByRole('button', { name: 'Finish' }));

      await waitFor(() => {
        expect(createEvent).toHaveBeenCalledWith(
          expect.objectContaining({
            eventTypeId: EventTypeId.Layout,
            displayGroupIds: [10],
          }),
          expect.any(String),
        );
      });
    },
  );
});

// =============================================================================
// Every event type
// =============================================================================

// handleFinish builds one payload for all ten event types, and each type adds
// its own fields from a long chain of branches. Sync and Layout are covered
// above; these cover the rest, each with the field that says what the event
// plays.

const CONTENT_ID = 42;

/** The payload fields that name what an event plays. An event carries exactly one. */
const CONTENT_FIELDS = [
  'campaignId',
  'commandId',
  'mediaId',
  'playlistId',
  'syncGroupId',
  'dataSetId',
] as const;

const contentFieldsOf = (payload: object) =>
  CONTENT_FIELDS.filter((field) => (payload as Record<string, unknown>)[field] != null);

/**
 * The modal looks up the Always and Custom dayparts with their own requests.
 * Command events are forced onto Custom and have no Dayparting dropdown to
 * wait on, so Finish must wait until that lookup has come back.
 */
const waitForDaypartLookup = async () => {
  await waitFor(() => {
    expect(fetchDaypart).toHaveBeenCalledWith(expect.objectContaining({ isCustom: 1 }));
  });
};

/** When a Command event runs. */
const COMMAND_START = '2027-03-01T09:00:00.000Z';

/**
 * Open the modal with the content already chosen, pick a display, and save.
 * Other types save from the Displays step on the Always daypart. A Command
 * can't be Always, so it goes on to the Time step and is given a start.
 */
const saveWithContent = async (user: UserEvent, eventTypeId: EventTypeId) => {
  renderScheduleModal({ mode: 'add', eventTypeId, contentId: CONTENT_ID });

  await user.click(await screen.findByRole('button', { name: 'Pick a display group' }));
  await waitForDaypartLookup();
  if (eventTypeId === EventTypeId.Command) {
    await user.click(screen.getByRole('button', { name: 'Next' }));
    fireEvent.change(screen.getByLabelText('Start Time'), { target: { value: COMMAND_START } });
  }
  await user.click(screen.getByRole('button', { name: 'Finish' }));

  await waitFor(() => {
    expect(createEvent).toHaveBeenCalledTimes(1);
  });
};

/** Choose an option in one of the modal's (real) dropdowns. */
const choose = async (user: UserEvent, dropdown: RegExp, option: RegExp | string) => {
  await user.click(await screen.findByRole('combobox', { name: dropdown }));
  await user.click(await screen.findByRole('option', { name: option }));
};

/** Fill in an Action event's first step, then pick a display and save. */
const saveActionEvent = async (user: UserEvent, actionType: 'navLayout' | 'command') => {
  renderScheduleModal({ mode: 'add', eventTypeId: EventTypeId.Action });

  await choose(user, /Action Type/, actionType === 'navLayout' ? 'Navigate to Layout' : 'Command');
  await user.type(screen.getByRole('textbox', { name: /Trigger Code/ }), 'go-lobby');
  if (actionType === 'navLayout') {
    await choose(user, /Layout Code/, /Lobby Welcome/);
  } else {
    await choose(user, /^Command/, 'Reboot');
  }

  await user.click(screen.getByRole('button', { name: 'Next' }));
  await user.click(await screen.findByRole('button', { name: 'Pick a display group' }));
  await waitForDaypartLookup();
  await user.click(screen.getByRole('button', { name: 'Finish' }));

  await waitFor(() => {
    expect(createEvent).toHaveBeenCalledTimes(1);
  });
};

describe('ScheduleEventModal - what each event type sends', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();

    setupScheduleModalMocks();

    // With Always only, a Command event has no Custom daypart to land on and
    // can't be saved at all.
    mockDaypartRows(ALWAYS_AND_CUSTOM);
  });

  const WITH_CONTENT = [
    ['Command', EventTypeId.Command, 'commandId'],
    ['Campaign', EventTypeId.Campaign, 'campaignId'],
    ['Overlay', EventTypeId.Overlay, 'campaignId'],
    ['Media', EventTypeId.Media, 'mediaId'],
    ['Playlist', EventTypeId.Playlist, 'playlistId'],
  ] as const;

  test.each(WITH_CONTENT)('a %s event sends the content chosen for it', async (_, type, field) => {
    const user = userEvent.setup();
    await saveWithContent(user, type);

    expect(createEvent).toHaveBeenCalledWith(
      expect.objectContaining({ eventTypeId: type, [field]: CONTENT_ID, displayGroupIds: [10] }),
      expect.any(String),
    );
  });

  test.each(WITH_CONTENT)('a %s event carries no other kind of content', async (_, type, field) => {
    const user = userEvent.setup();
    await saveWithContent(user, type);

    expect(contentFieldsOf(capturedPayload())).toEqual([field]);
  });

  // Command events have no Dayparting step: the modal puts them on the Custom
  // daypart, and a command runs once, so it has a start but never an end.
  test('a Command event is saved on the Custom daypart, with a start and no end', async () => {
    const user = userEvent.setup();
    await saveWithContent(user, EventTypeId.Command);

    const payload = capturedPayload();
    expect(payload.dayPartId).toBe(2);
    expect(payload.fromDt).toBe(COMMAND_START);
    expect(payload).not.toHaveProperty('toDt');
  });

  // Report 281, open. Once a display is picked, Finish is offered on the
  // Displays step, under a banner saying it creates an "Always" schedule. A
  // Command can't be Always: the modal sends it with no start date and the
  // server refuses it ("Please enter a from date"). On the Time step the same
  // event is blocked, so the Displays-step Finish is the gap.
  // https://github.com/xibosignage/xibo/issues/4053
  knownFailure(
    'a Command event cannot be saved from the Displays step without a start date',
    async () => {
      const user = userEvent.setup();
      renderScheduleModal({
        mode: 'add',
        eventTypeId: EventTypeId.Command,
        contentId: CONTENT_ID,
      });
      await user.click(await screen.findByRole('button', { name: 'Pick a display group' }));
      await waitForDaypartLookup();

      // A save is sent within the click itself (measured), so the count is final here.
      await user.click(screen.getByRole('button', { name: 'Finish' }));
      return vi.mocked(createEvent).mock.calls.length;
    },
    (saves) => expect(saves).toBe(0),
  );

  describe('Interrupt', () => {
    // Share of Voice defaults to 0, which the modal omits from the payload and
    // the schema rejects for Interrupt, so the test must type a value.
    const saveInterrupt = async (user: UserEvent) => {
      renderScheduleModal({
        mode: 'add',
        eventTypeId: EventTypeId.Interrupt,
        contentId: CONTENT_ID,
      });
      await user.click(await screen.findByRole('button', { name: 'Pick a display group' }));
      await waitForDaypartLookup();
      await user.click(screen.getByRole('button', { name: 'Next' }));

      const shareOfVoice = await screen.findByRole('spinbutton', { name: /Share of Voice/ });
      await user.clear(shareOfVoice);
      await user.type(shareOfVoice, '30');

      await user.click(screen.getByRole('button', { name: 'Finish' }));
      await waitFor(() => {
        expect(createEvent).toHaveBeenCalledTimes(1);
      });
    };

    test('an Interrupt event sends its layout and the share of voice entered', async () => {
      const user = userEvent.setup();
      await saveInterrupt(user);

      expect(createEvent).toHaveBeenCalledWith(
        expect.objectContaining({
          eventTypeId: EventTypeId.Interrupt,
          campaignId: CONTENT_ID,
          shareOfVoice: 30,
        }),
        expect.any(String),
      );
    });

    test('an Interrupt event is never saved without a share of voice', async () => {
      const user = userEvent.setup();
      await saveInterrupt(user);

      expect(capturedPayload().shareOfVoice).toBeGreaterThan(0);
    });

    // Overlay shares the campaign branch with Interrupt, but share of voice is
    // Interrupt's alone.
    test('an Overlay event sends no share of voice', async () => {
      const user = userEvent.setup();
      await saveWithContent(user, EventTypeId.Overlay);

      expect(capturedPayload()).not.toHaveProperty('shareOfVoice');
    });
  });

  describe('Data Connector', () => {
    // contentId lands on step 1, but Parameters is on step 0.
    const saveDataConnector = async (user: UserEvent, parameters?: string) => {
      renderScheduleModal({
        mode: 'add',
        eventTypeId: EventTypeId.DataConnector,
        contentId: CONTENT_ID,
      });

      if (parameters) {
        await user.click(await screen.findByRole('button', { name: 'Back' }));
        await user.type(await screen.findByRole('textbox', { name: /Parameters/ }), parameters);
        await user.click(screen.getByRole('button', { name: 'Next' }));
      }

      await user.click(await screen.findByRole('button', { name: 'Pick a display group' }));
      await waitForDaypartLookup();
      await user.click(screen.getByRole('button', { name: 'Finish' }));
      await waitFor(() => {
        expect(createEvent).toHaveBeenCalledTimes(1);
      });
    };

    test('a Data Connector event sends its dataset and the parameters entered', async () => {
      const user = userEvent.setup();
      await saveDataConnector(user, 'city=London');

      expect(createEvent).toHaveBeenCalledWith(
        expect.objectContaining({
          eventTypeId: EventTypeId.DataConnector,
          dataSetId: CONTENT_ID,
          dataSetParams: 'city=London',
        }),
        expect.any(String),
      );
    });

    test('a Data Connector event carries no other kind of content', async () => {
      const user = userEvent.setup();
      await saveDataConnector(user, 'city=London');

      expect(contentFieldsOf(capturedPayload())).toEqual(['dataSetId']);
    });

    test('a Data Connector event with no parameters sends none', async () => {
      const user = userEvent.setup();
      await saveDataConnector(user);

      expect(capturedPayload()).not.toHaveProperty('dataSetParams');
    });
  });

  describe('Action', () => {
    // The default mocks return no layout codes and no commands, which leaves
    // an Action event with nothing to pick.
    beforeEach(() => {
      vi.mocked(fetchLayoutCodes).mockResolvedValue({
        rows: [{ code: 'lobby', layout: 'Lobby Welcome' }],
        totalCount: 1,
      });
      vi.mocked(fetchCommands).mockResolvedValue({
        rows: [{ commandId: 7, command: 'Reboot' }],
        totalCount: 1,
      } as unknown as Awaited<ReturnType<typeof fetchCommands>>);
    });

    test('a Navigate to Layout action sends its trigger code and layout code', async () => {
      const user = userEvent.setup();
      await saveActionEvent(user, 'navLayout');

      expect(createEvent).toHaveBeenCalledWith(
        expect.objectContaining({
          eventTypeId: EventTypeId.Action,
          actionType: 'navLayout',
          actionTriggerCode: 'go-lobby',
          actionLayoutCode: 'lobby',
        }),
        expect.any(String),
      );
    });

    test('a Navigate to Layout action carries no content and no command', async () => {
      const user = userEvent.setup();
      await saveActionEvent(user, 'navLayout');

      expect(contentFieldsOf(capturedPayload())).toEqual([]);
    });

    test('a Command action sends its command and no layout code', async () => {
      const user = userEvent.setup();
      await saveActionEvent(user, 'command');

      const payload = capturedPayload();
      expect(payload).toEqual(
        expect.objectContaining({
          actionType: 'command',
          actionTriggerCode: 'go-lobby',
          commandId: 7,
        }),
      );
      expect(payload).not.toHaveProperty('actionLayoutCode');
    });
  });
});

// =============================================================================
// Editing an event with reminders - xibosignage/xibo#3941
// =============================================================================

// Before #3941 the modal sent no reminder ids at all, so every save deleted the
// event's reminders and created new ones. The ids come from the event the
// modal loads when it opens (fetchEventById), not from the grid row; the
// default mocks return an event with no reminders, which is the broken state.

/** Open an existing Layout event whose loaded copy carries `reminders`, and save it. */
const editEventWithReminders = async (user: UserEvent) => {
  const event = buildEvent({
    eventId: 77,
    campaignId: CONTENT_ID,
    dayPartId: 2,
    isCustom: 1,
    scheduleReminders: [
      {
        scheduleReminderId: 55,
        eventId: 77,
        value: 15,
        type: 1,
        option: 1,
        isEmail: 0,
        reminderDt: 0,
        lastReminderDt: 0,
      },
    ],
  });
  mockFetchEventById(event);
  renderScheduleModal({ mode: 'edit', event });

  // Save renders disabled until the full event has loaded.
  const save = await screen.findByRole('button', { name: 'Save' });
  await waitFor(() => {
    expect(save).toBeEnabled();
  });
  await user.click(save);
  await waitFor(() => {
    expect(updateEvent).toHaveBeenCalledTimes(1);
  });
};

const updatedPayload = () => {
  const calls = vi.mocked(updateEvent).mock.calls;
  return calls[calls.length - 1]![1];
};

describe('ScheduleEventModal - editing an event keeps its reminders', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();

    setupScheduleModalMocks();
    mockDaypartRows(ALWAYS_AND_CUSTOM);
  });

  test("saving an edited event sends each reminder's existing id", async () => {
    const user = userEvent.setup();
    await editEventWithReminders(user);

    expect(updateEvent).toHaveBeenCalledWith(
      77,
      expect.objectContaining({
        scheduleReminders: [
          expect.objectContaining({ reminder_scheduleReminderId: 55, reminder_value: 15 }),
        ],
      }),
      expect.any(String),
    );
  });

  // 0 or a missing id makes the server delete the reminder and create a new one.
  test('every reminder on an edited event is sent with a real id', async () => {
    const user = userEvent.setup();
    await editEventWithReminders(user);

    const reminders = updatedPayload().scheduleReminders ?? [];
    expect(reminders).toHaveLength(1);
    for (const reminder of reminders) {
      expect(reminder.reminder_scheduleReminderId).toBeGreaterThan(0);
    }
  });

  // A reminder the user has just added has no id yet, and must not borrow one.
  test('a reminder added in the modal is sent with no id', async () => {
    const user = userEvent.setup();
    renderScheduleModal({ mode: 'add', eventTypeId: EventTypeId.Layout, contentId: CONTENT_ID });

    await user.click(await screen.findByRole('button', { name: 'Pick a display group' }));
    await user.click(screen.getByRole('button', { name: 'Next' }));

    // The Reminder tab only exists away from Always.
    await choose(user, /Dayparting/, 'Custom');
    await user.click(screen.getByRole('checkbox', { name: 'Use Relative Time' }));
    const hours = screen.getByRole('spinbutton', { name: 'Hours' });
    await user.clear(hours);
    await user.type(hours, '1');
    await user.click(screen.getByRole('button', { name: 'Next' }));

    // The reminder value has no accessible label; it's the only spinbutton on the tab.
    await user.click(screen.getByRole('button', { name: 'Reminder' }));
    const reminderValue = screen.getByRole('spinbutton');
    await user.clear(reminderValue);
    await user.type(reminderValue, '15');

    await user.click(screen.getByRole('button', { name: 'Finish' }));
    await waitFor(() => {
      expect(createEvent).toHaveBeenCalledTimes(1);
    });

    expect(capturedPayload().scheduleReminders).toEqual([
      expect.objectContaining({ reminder_scheduleReminderId: null, reminder_value: 15 }),
    ]);
  }, 30_000); // ~10 user actions through the full modal; 15 s is too tight on a 2-vCPU runner
});

// =============================================================================
// A Custom daypart that isn't on the first page - xibosignage/xibo#3936
// =============================================================================

// The Dayparting dropdown loads 10 dayparts at a time. Before #3936 the modal
// found the Custom daypart by searching that first page, so on a CMS with more
// than 10 dayparts it didn't know which one was Custom. An event on Custom was
// then treated as a named daypart, and saved without its end date. The shared
// daypart fixtures have three rows, so the problem can't arise with them.

const CUSTOM_ON_PAGE_TWO = 99;
const NAMED_ON_PAGE_TWO = 120;

const MANY_DAYPARTS = [
  { dayPartId: 1, name: 'Always', isAlways: 1, isCustom: 0 },
  ...Array.from({ length: 10 }, (_, i) => ({
    dayPartId: 10 + i,
    name: `Slot ${i + 1}`,
    isAlways: 0,
    isCustom: 0,
  })),
  { dayPartId: CUSTOM_ON_PAGE_TWO, name: 'Custom', isAlways: 0, isCustom: 1 },
  { dayPartId: NAMED_ON_PAGE_TWO, name: 'Late Slot', isAlways: 0, isCustom: 0 },
];

/** Open an existing event on `dayPartId` and save it unchanged. */
const editEventOnDaypart = async (user: UserEvent, dayPartId: number, isCustom: 0 | 1) => {
  const event = buildEvent({ eventId: 78, campaignId: CONTENT_ID, dayPartId, isCustom });
  mockFetchEventById(event);
  renderScheduleModal({ mode: 'edit', event });

  const save = await screen.findByRole('button', { name: 'Save' });
  await waitFor(() => {
    expect(save).toBeEnabled();
  });
  await waitForDaypartLookup();
  await user.click(save);
  await waitFor(() => {
    expect(updateEvent).toHaveBeenCalledTimes(1);
  });
  return event;
};

describe('ScheduleEventModal - a Custom daypart past the first page', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();

    setupScheduleModalMocks();
    mockDaypartRows(MANY_DAYPARTS);
  });

  test('an event on Custom keeps its daypart, start and end when saved', async () => {
    const user = userEvent.setup();
    const event = await editEventOnDaypart(user, CUSTOM_ON_PAGE_TWO, 1);

    expect(updateEvent).toHaveBeenCalledWith(
      78,
      expect.objectContaining({
        dayPartId: CUSTOM_ON_PAGE_TWO,
        fromDt: new Date(event.fromDt * 1000).toISOString(),
        toDt: new Date(event.toDt! * 1000).toISOString(),
      }),
      expect.any(String),
    );
  });

  test('an event on Custom is never saved ending before it starts', async () => {
    const user = userEvent.setup();
    await editEventOnDaypart(user, CUSTOM_ON_PAGE_TWO, 1);

    const { fromDt, toDt } = updatedPayload();
    expect(toDt).toBeDefined();
    expect(new Date(toDt!).getTime()).toBeGreaterThan(new Date(fromDt!).getTime());
  });

  // A named daypart supplies its own times, so the event sends a start date only.
  test('an event on a named daypart past the first page sends no end date', async () => {
    const user = userEvent.setup();
    await editEventOnDaypart(user, NAMED_ON_PAGE_TWO, 0);

    const payload = updatedPayload();
    expect(payload.dayPartId).toBe(NAMED_ON_PAGE_TWO);
    expect(payload).not.toHaveProperty('toDt');
  });
});
