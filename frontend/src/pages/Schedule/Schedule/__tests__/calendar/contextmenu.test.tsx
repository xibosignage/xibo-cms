/* Copyright (C) 2026 Xibo Signage Ltd — AGPL-3.0 */

import { fireEvent, screen, within, waitFor } from '@testing-library/react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { buildEvent } from '../fixtures/event';

import { CALENDAR_DATE, buildAlwaysEvent } from './helpers/buildCalendarEvents';
import { renderCalendar } from './helpers/renderCalendar';

import { testQueryClient } from '@/setupTests';

const t = (key: string) => key;
vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t, i18n: { changeLanguage: vi.fn() } }),
  Trans: ({ children }: { children: React.ReactNode }) => children,
}));

vi.mock('@/context/UserContext', () => ({
  useUserContext: vi.fn(() => ({
    user: { settings: { defaultTimezone: 'UTC' } },
    isAuthenticated: true,
    logout: vi.fn(),
    updateUser: vi.fn(),
  })),
}));

// April 14 2026 00:00 UTC
const APR_14_START = 1776124800;

beforeEach(() => {
  testQueryClient.clear();
  vi.clearAllMocks();
});

describe('EventCalendar – context menu', () => {
  test('right-clicking an event icon opens the context menu', () => {
    const event = buildEvent({
      name: 'Test Event',
      fromDt: APR_14_START,
      toDt: APR_14_START + 3600,
    });
    renderCalendar({ date: CALENDAR_DATE, events: [event], onDeleteEvent: vi.fn() });

    const badge = screen.getByRole('button', { name: 'Test Event' });
    fireEvent.contextMenu(badge);

    expect(screen.getByRole('menu')).toBeInTheDocument();
  });

  test('context menu shows the Delete button', () => {
    const event = buildEvent({
      name: 'Confirm Delete',
      fromDt: APR_14_START,
      toDt: APR_14_START + 3600,
    });
    renderCalendar({ date: CALENDAR_DATE, events: [event], onDeleteEvent: vi.fn() });

    const badge = screen.getByRole('button', { name: 'Confirm Delete' });
    fireEvent.contextMenu(badge);

    expect(screen.getByRole('menuitem', { name: /delete/i })).toBeInTheDocument();
  });

  test('clicking Delete in the context menu triggers onDeleteEvent', () => {
    const onDeleteEvent = vi.fn();
    const event = buildEvent({
      name: 'Deletable Event',
      fromDt: APR_14_START,
      toDt: APR_14_START + 3600,
    });
    renderCalendar({ date: CALENDAR_DATE, events: [event], onDeleteEvent });

    const badge = screen.getByRole('button', { name: 'Deletable Event' });
    fireEvent.contextMenu(badge);

    const deleteButton = screen.getByRole('menuitem', { name: /delete/i });
    fireEvent.click(deleteButton);

    expect(onDeleteEvent).toHaveBeenCalledWith(event);
  });

  test('clicking outside dismisses the context menu', async () => {
    const event = buildEvent({
      name: 'Dismissable Event',
      fromDt: APR_14_START,
      toDt: APR_14_START + 3600,
    });
    renderCalendar({ date: CALENDAR_DATE, events: [event], onDeleteEvent: vi.fn() });

    const badge = screen.getByRole('button', { name: 'Dismissable Event' });
    fireEvent.contextMenu(badge);
    expect(screen.getByRole('menu')).toBeInTheDocument();

    fireEvent.pointerDown(document.body);

    await waitFor(() => {
      expect(screen.queryByRole('menu')).not.toBeInTheDocument();
    });
  });

  test('right-clicking the same icon twice repositions the menu', () => {
    const event = buildEvent({
      name: 'Reposition Event',
      fromDt: APR_14_START,
      toDt: APR_14_START + 3600,
    });
    renderCalendar({ date: CALENDAR_DATE, events: [event], onDeleteEvent: vi.fn() });

    const badge = screen.getByRole('button', { name: 'Reposition Event' });
    fireEvent.contextMenu(badge);
    fireEvent.contextMenu(badge);

    expect(screen.queryAllByRole('menu')).toHaveLength(1);
  });

  test('context menu shows event name', () => {
    const event = buildEvent({
      name: 'Morning Show',
      fromDt: APR_14_START,
      toDt: APR_14_START + 3600,
    });
    renderCalendar({ date: CALENDAR_DATE, events: [event], onDeleteEvent: vi.fn() });

    const badge = screen.getByRole('button', { name: 'Morning Show' });
    fireEvent.contextMenu(badge);

    const menu = screen.getByRole('menu');
    expect(within(menu).getByTitle('Morning Show')).toBeInTheDocument();
  });

  test('context menu shows time range for a timed event', () => {
    // April 14 2026 10:00 UTC to 17:00 UTC
    const fromDt = 1776160800;
    const toDt = 1776186000;
    const event = buildEvent({ name: 'Timed Show', fromDt, toDt });
    renderCalendar({ date: CALENDAR_DATE, events: [event], onDeleteEvent: vi.fn() });

    const badge = screen.getByRole('button', { name: 'Timed Show' });
    fireEvent.contextMenu(badge);

    const menu = screen.getByRole('menu');
    expect(within(menu).getByText(/10:00am/i)).toBeInTheDocument();
  });

  test('context menu for an always event shows no time label', () => {
    const event = buildAlwaysEvent({ name: 'Always On' });
    renderCalendar({ date: CALENDAR_DATE, events: [event], onDeleteEvent: vi.fn() });

    // Always events appear on every day cell — take any one of them to open the context menu
    const badge = screen.getAllByRole('button', { name: 'Always On' })[0]!;
    fireEvent.contextMenu(badge);

    const menu = screen.getByRole('menu');
    expect(within(menu).queryByText(/am –/i)).not.toBeInTheDocument();
  });

  test('context menu shows display group name', () => {
    const event = buildEvent({
      name: 'Lobby Event',
      fromDt: APR_14_START,
      toDt: APR_14_START + 3600,
      displayGroups: [{ displayGroupId: 1, displayGroup: 'Lobby' }] as never,
    });
    renderCalendar({ date: CALENDAR_DATE, events: [event], onDeleteEvent: vi.fn() });

    const badge = screen.getByRole('button', { name: 'Lobby Event' });
    fireEvent.contextMenu(badge);

    const menu = screen.getByRole('menu');
    expect(within(menu).getByText('Lobby')).toBeInTheDocument();
  });

  test('right-clicking on a different icon closes first menu and opens a new one', () => {
    const event1 = buildEvent({
      name: 'First Event',
      fromDt: APR_14_START,
      toDt: APR_14_START + 3600,
    });
    const event2 = buildEvent({
      name: 'Second Event',
      fromDt: APR_14_START + 7200,
      toDt: APR_14_START + 10800,
    });
    renderCalendar({ date: CALENDAR_DATE, events: [event1, event2], onDeleteEvent: vi.fn() });

    const badge1 = screen.getByRole('button', { name: 'First Event' });
    fireEvent.contextMenu(badge1);
    expect(screen.getByRole('menu')).toBeInTheDocument();

    const badge2 = screen.getByRole('button', { name: 'Second Event' });
    fireEvent.contextMenu(badge2);

    expect(screen.queryAllByRole('menu')).toHaveLength(1);
  });
});
