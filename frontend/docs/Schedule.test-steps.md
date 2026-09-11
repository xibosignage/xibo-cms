# Schedule (Events) Tests — Steps

All tests live under `frontend/src/pages/Schedule/Schedule/__tests__/`.

Each numbered item is the literal `test('…')` description in the suite — this file and the test names
match **one-for-one**, and that is the contract. Because the suite is large, the *load* half of
"load → act → assert" is stated once per file in the italic preamble rather than repeated on every line;
each numbered item is the act-and-assert.

Counts in the headings are live as of 11 September 2026. `[test.fails]` marks a test that documents an
open bug and is expected to fail until it is fixed (see the execution plan's Known issues).


---

# Events page — table view

## `table/page/actions.test.tsx` (7)

*Load the Events page with the modal bodies stubbed; use a row action; assert which modal opens and for which event.*

1. the Add Event button is greyed out while saved preferences are still loading
2. the Add Event button becomes clickable once preferences have loaded
3. clicking Add Event opens the Schedule modal blank, ready to create a new event
4. clicking Edit on a row opens the Schedule modal pre-filled with that event
5. clicking Make a Copy opens the Copy modal with the chosen event
6. clicking Delete on a row opens the Delete modal for that one event
7. clicking Delete on one row only targets that row, not the whole table


## `table/page/bulkDelete.test.tsx` (4)

*Load the Events page, tick rows via the header/row checkboxes; assert the bulk control and what is sent.*

1. the "Delete Selected" button is hidden until the user ticks at least one row
2. the "Delete Selected" button appears once a row is ticked
3. selecting two rows and clicking "Delete Selected" opens the modal showing 2 events
4. confirming the bulk delete sends all ticked events for deletion at once


## `table/page/columns.test.tsx` (4)

*Load the Events page with a given saved column preference; assert which columns render and what is saved back.*

1. uses the default column visibility when the user has no saved preferences
2. a column that was turned ON in saved preferences shows up after the page reloads
3. a column that was turned OFF in saved preferences stays hidden after the page reloads
4. toggling a column off triggers a debounced save of the new column visibility


## `table/page/filters.test.tsx` (10)

*Load the Events page; open the filter panel and change a filter; assert the refetch and the page reset.*

1. the filter panel is closed when the page first loads
2. clicking the Filters button opens the panel, clicking again closes it
3. picking a date range re-fetches events using the new from/to dates
4. if the user had a saved date range from a previous session, the page restores it on load
5. changing the date range jumps back to page 1
6. picking a display group re-fetches events filtered to that group
7. clearing the display group selection removes the filter so all events show again
8. picking a display group jumps back to page 1
9. typing in a filter field jumps the table back to page 1
10. clicking Reset clears the other filters but keeps the date range


## `table/page/render.test.tsx` (7)

*Load the Events page with canned event data; assert the default state with no interaction.*

1. shows a loading message and hides the table while saved preferences are loading
2. the page opens in table view by default, not calendar view
3. the page starts on page 1 with 10 events per page
4. the table shows the default set of columns when the page first opens
5. columns that are hidden by default do not appear when the page first opens
6. an event returned by the API shows up as a row in the table
7. the table still loads cleanly when there are no events to show


---

# Calendar view

## `calendar/contextmenu.test.tsx` (10)

*Load the calendar with the given events (`renderCalendar`), right-click an event icon, assert on the menu.*

1. right-clicking an event icon opens the context menu
2. context menu shows the Delete button
3. clicking Delete in the context menu triggers onDeleteEvent
4. clicking outside dismisses the context menu
5. right-clicking the same icon twice repositions the menu
6. context menu shows event name
7. context menu shows time range for a timed event
8. context menu for an always event shows no time label
9. context menu shows display group name
10. right-clicking on a different icon closes first menu and opens a new one


## `calendar/events/date-clamping.test.tsx` (4)

*Load the calendar for a month with a monthly recurring event; assert which day the icon lands on.*

1. monthly event on the 31st in a 30-day month shows on the last day instead
2. monthly event set to repeat on the 5th Monday clamps to the last Monday in months with only 4
3. monthly event on the 29th in a non-leap year February clamps to the 28th
4. monthly event on the 29th in a leap year February shows on the 29th


## `calendar/events/recurrence-edge.test.tsx` (10)

*Load the calendar with a degenerate or partially-deleted series; assert how many icons appear and where.*

1. interval set to zero generates no additional occurrences
2. missing recurringEvent flag silently skips expansion
3. weekly repeat with no days selected falls back to once per week on original day
4. very frequent event (every 30 minutes) collapses to one icon per day
5. same event occurring multiple times on same day deduplicates to one icon
6. one occurrence deleted from a series does not appear
7. the very first occurrence deleted hides the original but future repeats still show
8. recurring series stopping mid-month only shows occurrences before stop date
9. recurring event with future start date shows nothing in current month
10. recurring event with no end date continues across the whole month


## `calendar/events/recurrence.test.tsx` (6)

*Load the calendar with a recurring event; assert an icon appears on each occurrence day.*

1. weekly recurring event appears on each occurrence day in the month
2. recurring expansion is correct across month boundaries
3. always event appears on every day of the month
4. daily recurring event appears every day
5. recurring event with no end date shows across the whole visible month
6. two different recurring events both appear on their respective days


## `calendar/grid/cells.test.tsx` (5)

*Load the calendar; assert on the day cells themselves rather than their contents.*

1. today is highlighted with a blue circle on the day number
2. days outside the current month are rendered in grey
3. days with no events are not clickable
4. loading overlay is shown while fetching
5. legend shows all event type groups


## `calendar/grid/icons.test.tsx` (9)

*Load the calendar with events of varying type and duration; assert which icons render on which day.*

1. event icons are shown per event type
2. priority badge is shown on the icon
3. event with no end time is silently skipped
4. event spanning the whole month appears on every day
5. events on overflow days show their icons
6. event ending exactly at midnight appears on both days
7. zero duration event renders on the calendar day
8. no events renders all cells without icons
9. all events filtered out produces blank calendar


## `calendar/grid/timezone.test.tsx` (7)

*Load the calendar with the user context set to a given timezone; assert which calendar day the event falls on.*

1. UTC+ shift: event at 11pm UTC appears on next calendar day for UTC+2 user
2. UTC- shift: event at 1am UTC appears on previous calendar day for UTC-5 user
3. fractional offset: event at 10:30pm UTC appears on next day for UTC+5:30 user
4. timezone month boundary: event on last day of March 11pm UTC disappears for UTC+2 user
5. DST spring forward: event at 2:30am on spring-forward night renders without crashing
6. DST fall back: event at 2:30am on fall-back night renders without crashing
7. recurring event crossing DST boundary shows correct wall-clock day


## `calendar/panel/content.test.tsx` (10)

*Open the day detail panel for a day with events; assert on the time label and display-group badges of a row.*

1. same-day event shows full time range in panel
2. multi-day event on first day shows start time with trailing dash
3. multi-day event on last day shows end time with leading dash
4. multi-day event on a middle day shows no time label
5. midnight boundary: second day shows "– 12:00am" time label
6. fractional timezone time label formats correctly
7. event with no name and no content renders without crashing
8. event with one display group shows no +N badge
9. event with three display groups shows first group and +2 badge
10. event with no display groups renders row without badge and without crashing


## `calendar/panel/open-close.test.tsx` (8)

*Load the calendar, click a day cell to open the panel; assert what opens, closes, or is reported back to the page.*

1. clicking a day with events opens the detail panel
2. clicking an event in the panel triggers onEditEvent
3. clicking outside the panel closes it
4. clicking the same day twice closes the panel
5. clicking a different day moves the panel to show new events
6. clicking a day with no events does nothing
7. the Agenda button hands back the panel's day and that day's events
8. there is no Agenda button when the agenda feature is off


## `calendar/view.test.tsx` (6)

*Load the Events page; toggle between table and calendar view; assert which view and chrome is showing.*

1. initial render shows table view, not calendar heading
2. clicking Calendar View toggle switches to calendar view
3. clicking Table View toggle switches back from calendar view
4. loading overlay appears while calendar data is fetching
5. date range view-mode selector is hidden in calendar mode
6. date range view-mode selector reappears after switching back to table


---

# Schedule Event modal

## `table/modals/scheduleModal/addMode.test.tsx` (8)

*Open the Schedule modal in add mode with content prefilled; click Finish; assert the create path.*

1. clicking Finish with valid fields calls the createEvent API
2. clicking Finish in add mode never calls the updateEvent API
3. shows the 'Added Event' success notification after a successful save
4. calls onSaved so the parent table can refresh after a successful save
5. closes the modal after a successful save by calling onClose
6. Cancel does not fire the createEvent API call
7. pressing Escape closes the modal without saving
8. re-opening the modal after Cancel shows the original empty form, not the user's discarded edits


## `table/modals/scheduleModal/editMode.test.tsx` (9)

*Open the Schedule modal in edit mode for an event; assert the prefill and the update path.*

1. fetches the full event details from the API on open
2. edit mode unlocks all steps so the user can jump straight to Optional
3. the footer button reads "Save" (not "Finish") in edit mode
4. pre-fills the Name field on the Optional step with the event name
5. uses values fetched from the API for fields not in the table row
6. clicking Save calls updateEvent with the event's id
7. clicking Save in edit mode never calls the createEvent API
8. shows the 'Updated Event' success notification after a successful save
9. calls onSaved and onClose after a successful update


## `table/modals/scheduleModal/navigation.test.tsx` (6)

*Open the Schedule modal in add mode; assert stepper navigation and which steps are reachable.*

1. the Next button is disabled when no content has been chosen yet
2. the Back button is not shown on the first step
3. clicking Cancel calls the parent onClose callback
4. completing Step 0 by choosing content enables Next to advance to Step 1
5. clicking Back from Step 1 returns the user to Step 0
6. already-visited steps in the stepper are clickable; not-yet-reached steps are not


## `table/modals/scheduleModal/payload.test.tsx` (3)

*Open the Schedule modal, reach Finish by the shortest route, and assert what `createEvent` was sent. Regression cover for xibosignage/xibo#3927.*

1. saving a Sync event sends the display group of every display in the sync group
2. a Sync event is never saved with an empty list of display groups
3. saving a Layout event sends the displays and groups the user picked


## `table/modals/scheduleModal/steps/content.test.tsx` (7)

*Open the Schedule modal on the Content step; change the event type or content; assert the fields and fetches.*

1. the Event Type dropdown lists all the supported event types
2. opening the modal in add mode pre-fetches layouts (the default event type)
3. changing the event type to Command triggers a fetch for commands
4. changing the event type clears the previously selected content
5. a Preview button appears when a campaign is selected for a Layout-type event
6. switching to the Action event type reveals the Action Type, Trigger Code and Layout Code fields
7. selecting a dataset for a Data Connector event reveals the Parameters field


## `table/modals/scheduleModal/steps/displays.test.tsx` (5)

*Open the Schedule modal and reach the Displays step; assert when it is shown and when Finish appears.*

1. the Finish button is hidden until a display is selected
2. selecting a display reveals the Finish button
3. Sync event type skips the Displays step entirely
4. picking a display reveals the "you're all set" info banner
5. opening the modal with displayGroupIds pre-filled (e.g. from the Displays page) skips the manual display-picking step


## `table/modals/scheduleModal/steps/optional.test.tsx` (11)

*Open the Schedule modal on the Optional step; assert sub-tab visibility and the field set per event type.*

1. Repeats and Reminder tabs are hidden when the daypart is Always
2. Geo Location and Criteria tabs are hidden when their feature flags are off
3. the General sub-tab body shows the Name input by default
4. General sub-tab renders the full default field set (Display Order, Priority, Max plays per hour, Sync Timezone)
5. Media-type events show Duration in loop, Resolution and Background Colour on the General tab
6. Playlist-type events show Resolution and Background Colour on the General tab
7. Action-type events hide Display Order and Max plays per hour on the General tab
8. selecting "Week" as the repeat type reveals the weekday toggle buttons
9. selecting "Month" as the repeat type reveals the day-of-month / day-of-week radios
10. an Until date picker appears on the Repeats tab once a repeat type is chosen
11. enabling the Geo Location checkbox reveals the map; unchecking it hides the map


## `table/modals/scheduleModal/steps/optionalTabPersistence.test.tsx` (6)

*Configure a repeat or reminder on the Custom daypart, switch back to Always, and assert the stale state is gone from both UI and payload.*

1. the Repeats tab button disappears once the daypart is switched back to Always
2. the Repeats tab body should not remain rendered once its tab button is hidden
3. a repeat configured before switching back to Always must not be sent to createEvent
4. the Reminder tab button disappears once the daypart is switched back to Always
5. **[test.fails]** the Reminder tab body should not remain rendered once its tab button is hidden
6. **[test.fails]** a reminder configured before switching back to Always must not be sent to createEvent


## `table/modals/scheduleModal/steps/time.test.tsx` (7)

*Open the Schedule modal on the Time step; change the daypart or event type; assert which time fields are shown.*

1. with the Always daypart selected, no date or time fields are shown
2. switching to the Custom daypart reveals both Start Time and End Time fields
3. Command event type hides the daypart selector entirely
4. Command event type shows only a Start Time field, no End Time
5. selecting a named (non-Always, non-Custom) daypart shows only a Start Time field
6. switching on "Use Relative Time" for a Custom daypart reveals Hours/Minutes/Seconds inputs and the live preview
7. Interrupt events show a Share of Voice field with an "As a percentage" sibling


## `table/modals/scheduleModal/validation.test.tsx` (4)

*Open the Schedule modal, submit with something missing; assert the error, and that nothing was sent and nothing closed.*

1. clicking Finish with missing required fields surfaces a validation error
2. failed validation does not fire the createEvent API call
3. failed validation keeps the modal open (does not call onClose)
4. saving with a repeat type set and recurrenceDetail = 0 surfaces the "Repeat every must be at least 1" error


## `table/modals/scheduleModal/validationRouting.test.tsx` (3)

*Submit with a missing field owned by an earlier step; assert the modal jumps back to that step.*

1. clicking Save in edit mode with a missing campaign jumps the user back to the Content step
2. clicking Finish in add mode with a missing daypart jumps the user to the Time step
3. the field-level error message is exposed as the accessible description of the content input


---

# Agenda

## `table/modals/agendaModal/interaction.test.tsx` (21)

*Open the agenda (`renderAgendaModal`, real `Modal`, real leaflet), act, assert. Requests are asserted via `capturedParams()`.*

1. choosing another display group asks for the same day against that group
2. turning on "Specific point in time" reveals the time slider
3. asking for a specific point in time replaces the whole-day request
4. a whole-day request does not also name a single point in time
5. clicking a scheduled layout reveals the breadcrumb for that event
6. clicking the same row a second time hides the breadcrumb again
7. sorting by a column reorders the rows by that column
8. clicking the same column again reverses the order
9. the preview button opens the layout preview
10. previewing a layout does not also select its row
11. a layout with no preview token offers no preview button
12. the close button closes the agenda
13. pressing Escape closes the agenda
14. the map is hidden until the user asks for it
15. the map button shows the map
16. choosing a point drops the events fenced somewhere else
17. choosing a point in the other fence keeps the other event instead
18. the clear button appears only once a full point has been given
19. clearing the location brings the filtered-out events back
20. the browser location button fills in the coordinates the browser reports
21. **[test.fails]** an event fenced with a FeatureCollection survives a point inside that fence


## `table/modals/agendaModal/nested-edit.test.tsx` (5)

*Open the agenda with `ScheduleEventModal` stubbed; select a row and use the breadcrumb; assert which event was handed over.*

1. the breadcrumb opens the editor for the event that was clicked
2. saving the edit re-reads the day's events
3. closing the editor without saving leaves the day's events alone
4. a viewer who cannot modify schedules gets no way in from the breadcrumb
5. an event the server marks as not editable cannot be opened either


## `table/modals/agendaModal/render.test.tsx` (18)

*Open the agenda for a canned day (`renderAgendaModal`); assert what is on screen and what was requested. No interaction.*

1. the heading names the day the agenda is for
2. the day's scheduled layouts are listed under their event type
3. the event type heading is accompanied by a count of its events
4. events of different types are split into separate tables
5. the table offers the agenda columns
6. a layout the user has no permission to see is labelled "Private Item"
7. from and to are shown in the viewer's own timezone and date format
8. an always-on event shows "Always" in place of a start time
9. the display groups the day's events belong to are listed in the sidebar
10. campaigns are listed in the sidebar when the day has campaign events
11. the campaigns sidebar section is absent when no event belongs to a campaign
12. a tab is offered for each display group the page passed in
13. no display group tabs are shown when there is only one group
14. a day with nothing scheduled says so
15. a failed fetch shows an error message instead of the agenda
16. opening the agenda asks the API for the whole of that day's events
17. the agenda opens on the first display group when several are available
18. no agenda is requested when the page passed no display groups at all


---

# Hooks

## `table/hooks/useAgendaData.test.tsx` (4)

*`renderHook` the hook with params and an enabled flag; assert what reached `fetchAgendaEvents` and what came back.*

1. fetches the agenda with exactly the parameters it was given
2. asks for nothing at all while it is disabled
3. a different display group is fetched afresh rather than served from cache
4. surfaces a failure to the caller instead of hanging


## `table/hooks/useEventActions.test.tsx` (16)

*`renderHook` the hook with mocked APIs; act on it; assert the API calls, toasts, refresh, modal close and in-progress flags.*

1. starts off in a clean state - nothing in progress and no errors
2. does nothing when the user somehow tries to copy without an event selected
3. when the copy succeeds: the event is copied, a green success toast appears, the table refreshes, and the modal closes
4. when the copy fails: a red error toast appears, the table does not refresh, and the modal stays open so the user can retry
5. after a successful copy, the "is currently copying" flag is reset to false
6. after a failed copy, the "is currently copying" flag is also reset to false (so Save is not stuck)
7. does nothing when the list of events to delete is empty
8. when deleting a single event succeeds: calls the delete API, clears the row selection, refreshes the table, and closes the modal
9. when deleting multiple events succeeds: fires one API call per event, clears the row selection, refreshes the table, and closes the modal
10. when a delete fails: shows an error message, refreshes the table to reflect any partial success, but keeps the modal open so the user sees the error
11. after a successful delete, the "is currently deleting" flag is reset to false
12. after a failed delete, the "is currently deleting" flag is also reset to false (so the confirm button is not stuck)
13. on success: calls the API with the event id and the date range, refreshes the table, and closes the modal
14. on failure: shows an error message, does not refresh the table, and keeps the modal open
15. after a successful occurrence delete, the "is currently deleting" flag is reset to false
16. after a failed occurrence delete, the "is currently deleting" flag is also reset to false


---

# End to end

## `table/scenarios/createEditDelete.test.tsx` (1)

*Drive the whole page end to end through one event lifecycle.*

1. a user creates a layout event, renames it, then deletes it — each step reflects in the table


---

# Copy, Delete and modal routing

## `table/modals/CopyEventModal.test.tsx` (8)

*Render `CopyEventModal` directly for an event; act on the name field; assert validation and what is saved.*

1. pre-fills the name field with the event name plus " (1)"
2. shows "Name is required" when the name is empty
3. will not save a name that already exists, even with different capital letters
4. the duplicate-name error goes away as soon as the user starts typing again
5. Save trims any extra spaces from the name before saving
6. clicking Cancel just closes the modal without copying anything
7. while the copy is being saved, the buttons are greyed out and Save shows "Saving…"
8. shows nothing on screen when the modal is told it is closed


## `table/modals/DeleteEventModal.test.tsx` (13)

*Render `DeleteEventModal` directly in single / recurring / bulk configuration; assert the copy shown and which callback fires.*

1. shows the event name and a single confirmation message
2. clicking Yes, delete calls onDelete
3. clicking Cancel calls onClose
4. shows "this event" fallback when no eventName is provided
5. shows both delete-this-occurrence and delete-series radios
6. confirming with default scope calls onDeleteOccurrence (not onDelete)
7. selecting the series radio and confirming calls onDelete
8. does not show radios when onDeleteOccurrence is not provided
9. shows the bulk confirmation message with the item count
10. does not show the occurrence radio for a bulk delete
11. confirming calls onDelete
12. confirm button is disabled and shows "Deleting…" while isLoading
13. renders the error message inside the dialog


## `table/modals/routing.test.tsx` (13)

*Render `EventModals` with every child modal stubbed; assert which one opens for a given `activeModal` and what props it received.*

1. renders nothing when activeModal is null
2. opens DeleteEventModal with the single event details
3. opens DeleteEventModal with isRecurring=true and onDeleteOccurrence when occurrence delete is allowed
4. does not forward onDeleteOccurrence for a recurring event when occurrence delete is not allowed
5. opens DeleteEventModal in bulk mode when more than one item is selected
6. forwards deleteError and isDeleting to the modal
7. opens CopyEventModal with the selected event and existingNames
8. forwards onConfirm to the page-level handleConfirmClone handler
9. opens ScheduleEventModal in add mode for activeModal="schedule"
10. opens ScheduleEventModal in edit mode with the selectedEvent
11. does not render in edit mode when selectedEvent is null
12. opens AgendaModal for the chosen day and its display groups
13. does not open the agenda before a day has been chosen

