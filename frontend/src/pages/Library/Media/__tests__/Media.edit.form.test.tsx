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

import { screen, fireEvent, waitFor, within } from '@testing-library/react';
import type React from 'react';
import { test, vi, beforeEach } from 'vitest';

import { mockEditMedia, mockMediaData, openEditModal, renderMediaPage } from './mediaTestUtils';

import { updateMedia } from '@/services/mediaApi';
import { testQueryClient } from '@/setupTests';

// -----------------------------------------------------------------------------
// Module mocks
// -----------------------------------------------------------------------------

vi.mock('@/pages/Library/Media/hooks/useMediaFilterOptions', () => ({
  useMediaFilterOptions: vi.fn().mockReturnValue({ filterOptions: [], isLoading: false }),
}));
vi.mock('../hooks/useMediaData');
vi.mock('@/components/ui/modals/Modal');
vi.mock('@/services/mediaApi', () => ({
  uploadMedia: vi.fn(),
  uploadMediaFromUrl: vi.fn(),
  updateMedia: vi.fn(),
  uploadThumbnail: vi.fn(),
  deleteMedia: vi.fn(),
}));
vi.mock('@/services/folderApi', () => ({
  fetchFolderById: vi.fn().mockResolvedValue({ id: 1, text: 'Root' }),
  fetchFolderTree: vi.fn().mockResolvedValue([]),
  searchFolders: vi.fn().mockResolvedValue([]),
  fetchContextButtons: vi.fn().mockResolvedValue({ create: true }),
  selectFolder: vi.fn(),
}));
vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key: string) => key, i18n: { changeLanguage: vi.fn() } }),
  Trans: ({ children }: { children: React.ReactNode }) => children,
}));
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));

// =============================================================================
// Tests
// =============================================================================

describe('Edit Media — form fields', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(updateMedia).mockResolvedValue({ ...mockEditMedia });
    mockMediaData({
      data: { rows: [mockEditMedia], totalCount: 1 },
      isFetching: false,
      isError: false,
      error: null,
    });
  });

  // ---------------------------------------------------------------------------
  // Name field — editable text input connected to the draft via onChange.
  // The label "Name" is linked to the input via htmlFor="name".
  // ---------------------------------------------------------------------------
  test('Name field is editable and reflects typed input', async () => {
    renderMediaPage();
    await openEditModal();

    const nameInput = screen.getByLabelText('Name');
    fireEvent.change(nameInput, { target: { value: 'new-name.png' } });

    expect(nameInput).toHaveValue('new-name.png');
  });

  // ---------------------------------------------------------------------------
  // Tag input — add a new tag in tag|value format.
  //
  // Start with a media item that has no tags. Open the edit modal. Find the tag
  // input box. Type season|summer into it and press Enter. Check that a pill with
  // the text season appears.
  // BUG: pill only shows 'season' — the |value part is stripped from display.
  // Expected: the full 'season|summer' string should appear in the pill.
  // ---------------------------------------------------------------------------
  test.fails('Tag input accepts new tags in tag|value format', async () => {
    mockMediaData({
      data: { rows: [{ ...mockEditMedia, tags: [] }], totalCount: 1 },
      isFetching: false,
      isError: false,
      error: null,
    });

    renderMediaPage();
    await openEditModal();

    const tagInput = screen.getByPlaceholderText('Add tags');
    fireEvent.change(tagInput, { target: { value: 'season|summer' } });
    fireEvent.keyDown(tagInput, { key: 'Enter' });

    // Expected: full tag|value string is shown in the pill
    expect(screen.getByText('season|summer')).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // BUG: tag typed into the input but not committed with Enter is silently
  // dropped on save. Expected: the uncommitted input value should be included
  // in the saved payload.
  // ---------------------------------------------------------------------------
  test.fails('Tag typed without pressing Enter is included when form is saved', async () => {
    mockMediaData({
      data: { rows: [{ ...mockEditMedia, tags: [] }], totalCount: 1 },
      isFetching: false,
      isError: false,
      error: null,
    });

    renderMediaPage();
    await openEditModal();

    const tagInput = screen.getByPlaceholderText('Add tags');
    fireEvent.change(tagInput, { target: { value: 'season|summer' } });
    // No Enter pressed — tag is still in the input field, not yet a pill

    fireEvent.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => {
      expect(updateMedia).toHaveBeenCalledWith(
        mockEditMedia.mediaId,
        expect.objectContaining({ tags: expect.stringContaining('season') }),
      );
    });
  });

  // ---------------------------------------------------------------------------
  // Tags whose name starts with "Tag" should display with the original
  // capitalisation.
  // ---------------------------------------------------------------------------
  test('Tag name starting with "Tag" is displayed with correct capitalisation', async () => {
    mockMediaData({
      data: { rows: [{ ...mockEditMedia, tags: [] }], totalCount: 1 },
      isFetching: false,
      isError: false,
      error: null,
    });

    renderMediaPage();
    const dialog = await openEditModal();

    const tagInput = screen.getByPlaceholderText('Add tags');
    fireEvent.change(tagInput, { target: { value: 'Tag|value' } });
    fireEvent.keyDown(tagInput, { key: 'Enter' });

    // Expected: pill shows 'Tag', not 'TAg'
    expect(within(dialog).getByText('Tag')).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // BUG: tags with Season|summer only saves Season.
  // ---------------------------------------------------------------------------
  test('Tag name preserves original capitalisation when added', async () => {
    mockMediaData({
      data: { rows: [{ ...mockEditMedia, tags: [] }], totalCount: 1 },
      isFetching: false,
      isError: false,
      error: null,
    });

    renderMediaPage();
    const dialog = await openEditModal();

    const tagInput = screen.getByPlaceholderText('Add tags');
    fireEvent.change(tagInput, { target: { value: 'Season|summer' } });
    fireEvent.keyDown(tagInput, { key: 'Enter' });

    // Expected: pill shows 'Season', not 'season'
    expect(within(dialog).getByText('Season')).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Tag input — remove an existing tag.
  //
  // Each tag pill renders a small X button. We scope the query to the pill
  // element with within() to find the remove button unambiguously.
  // ---------------------------------------------------------------------------
  test('Tag input allows removing an existing tag', async () => {
    renderMediaPage();
    const dialog = await openEditModal();

    // 'nature' also appears as a tag badge in the table row behind the modal,
    // so scope all queries to the dialog to avoid ambiguity.
    expect(within(dialog).getByText('nature')).toBeInTheDocument();

    // Find the tag pill span inside the dialog and click its remove button
    const tagPill = within(dialog).getByText('nature').closest('span')!;
    fireEvent.click(within(tagPill).getByRole('button'));

    expect(within(dialog).queryByText('nature')).not.toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Orientation dropdown — SelectDropdown.
  //
  // The toggle is a <div onClick>, not a <button>. Clicking the visible label
  // text ("Landscape") opens the dropdown. The option buttons are rendered in
  // CSS-hidden list (opacity-0), so getAllByText returns [toggleSpan, optionBtn]
  // — we click index [0] (the toggle span) to open.
  // ---------------------------------------------------------------------------
  test('Orientation dropdown changes between portrait and landscape', async () => {
    renderMediaPage();
    await openEditModal();

    // Click the toggle span showing the current value
    fireEvent.click(screen.getAllByText('Landscape')[0]!);

    // Portrait option button appears in the open dropdown
    const portraitBtn = await screen.findByRole('button', { name: 'Portrait' });
    fireEvent.click(portraitBtn);

    // Toggle now shows "Portrait"
    expect(screen.getAllByText('Portrait')[0]).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Duration input — DurationInput formats seconds as HH:MM:SS.
  //
  // mockEditMedia.duration = 10 → pre-filled as "00:00:10".
  // Find the duration input. Type 00:00:30 into it and move focus away.
  // Check that the input shows 00:00:30
  // ---------------------------------------------------------------------------
  test('Duration input accepts a numeric value', async () => {
    renderMediaPage();
    await openEditModal();

    const durationInput = screen.getByPlaceholderText('00:00:00');
    fireEvent.change(durationInput, { target: { value: '00:00:30' } });
    fireEvent.blur(durationInput);

    expect(durationInput).toHaveValue('00:00:30');
  });

  // ---------------------------------------------------------------------------
  // Expiry date dropdown — shows all preset option buttons when opened.
  // ---------------------------------------------------------------------------
  test('Expiry date dropdown shows all preset options', async () => {
    renderMediaPage();
    await openEditModal();

    // Click the toggle div — the visible text "Never Expire" is inside a span
    // that bubbles the click up to its parent div's onClick handler
    fireEvent.click(screen.getByText('Never Expire'));

    expect(await screen.findByRole('button', { name: 'Never Expire' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'End of Today' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'In 7 Days' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'In 14 Days' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'In 30 Days' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Choose Date' })).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Selecting a preset expiry option updates the toggle display.
  // ---------------------------------------------------------------------------
  test('Selecting a preset expiry option updates the displayed value', async () => {
    renderMediaPage();
    await openEditModal();

    fireEvent.click(screen.getByText('Never Expire'));
    fireEvent.click(await screen.findByRole('button', { name: 'In 7 Days' }));

    // Toggle area now shows the selected preset
    expect(screen.getByText('In 7 Days')).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Enable Stats dropdown.
  //
  // mockEditMedia.enableStat = 'Inherit' (capital I from API) does not match
  // the option value 'inherit' (lowercase), so selectedLabel is empty and the
  // placeholder "Inherit" is shown. getAllByText handles the hidden option
  // button — index [0] is the visible toggle span.
  // ---------------------------------------------------------------------------
  test('Enable Stats dropdown changes between Inherit / On / Off', async () => {
    renderMediaPage();
    await openEditModal();

    // Click the toggle showing "Inherit" placeholder
    fireEvent.click(screen.getAllByText('Inherit')[0]!);

    // Click "On" from the dropdown options
    fireEvent.click(await screen.findByRole('button', { name: 'On' }));

    // Toggle now reflects "On"
    expect(screen.getAllByText('On')[0]).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Retired checkbox — toggling updates the draft.retired boolean.
  //
  // Find the Retired checkbox — it starts unchecked.
  // Click it and check it is now checked.
  // Click it again and check it is unchecked.
  // ---------------------------------------------------------------------------
  test('Retired checkbox toggles on and off', async () => {
    renderMediaPage();
    await openEditModal();

    const retiredCheckbox = screen.getByRole('checkbox', {
      name: /Retired media remains/i,
    });
    expect(retiredCheckbox).not.toBeChecked();

    fireEvent.click(retiredCheckbox);
    expect(retiredCheckbox).toBeChecked();

    fireEvent.click(retiredCheckbox);
    expect(retiredCheckbox).not.toBeChecked();
  });

  // ---------------------------------------------------------------------------
  // Update in Layouts checkbox — accessible name from <label htmlFor="update">:
  // Find the Update in Layouts checkbox — it starts unchecked. Click it and
  // check it is now checked. Click it again and check it is unchecked.
  // ---------------------------------------------------------------------------
  test('Update in Layouts checkbox toggles on and off', async () => {
    renderMediaPage();
    await openEditModal();

    const updateCheckbox = screen.getByRole('checkbox', { name: /only be updated/i });
    expect(updateCheckbox).not.toBeChecked();

    fireEvent.click(updateCheckbox);
    expect(updateCheckbox).toBeChecked();

    fireEvent.click(updateCheckbox);
    expect(updateCheckbox).not.toBeChecked();
  });

  // ---------------------------------------------------------------------------
  // BUG: clearing the name field and clicking Save still calls updateMedia.
  // Expected: the form should block submission client-side when the name is
  // empty — either by disabling the Save button or showing an inline error.
  // ---------------------------------------------------------------------------
  test('Save with name cleared — no client-side validation prevents submission', async () => {
    renderMediaPage();
    await openEditModal();

    fireEvent.change(screen.getByLabelText('Name'), { target: { value: '' } });
    fireEvent.click(screen.getByRole('button', { name: 'Save' }));

    expect(await screen.findByText('Name is required')).toBeInTheDocument();
    expect(updateMedia).not.toHaveBeenCalled();
  });
});
