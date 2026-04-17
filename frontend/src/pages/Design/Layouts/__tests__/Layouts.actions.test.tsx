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
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { useLayoutActions } from '../hooks/useLayoutActions';

import {
  SINGLE_DRAFT_LAYOUT,
  SINGLE_LAYOUT,
  defaultLayoutActions,
  mockDraftLayout,
  mockFetchLayouts,
  mockLayout,
  renderLayoutsPage,
} from './layoutTestUtils';

import { saveLayoutAsTemplate, updateLayout } from '@/services/layoutsApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key: string) => key, i18n: { changeLanguage: vi.fn() } }),
  Trans: ({ children }: { children: React.ReactNode }) => children,
}));

vi.mock('@/services/folderApi');
vi.mock('@/services/layoutsApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));

vi.mock('../hooks/useLayoutActions', () => ({ useLayoutActions: vi.fn() }));
vi.mock('../hooks/useLayoutFilterOptions', () => ({
  useLayoutFilterOptions: vi.fn(() => ({ filterOptions: [], isLoading: false })),
}));
vi.mock('@/hooks/useOwner', () => ({
  useOwner: vi.fn().mockReturnValue({ owner: null, loading: false }),
}));

vi.mock('@/components/ui/FolderActionModals', () => ({ default: () => null }));
vi.mock('@/components/ui/forms/SelectFolder', () => ({ default: () => null }));
vi.mock('@/components/ui/forms/TagInput', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Helpers
// =============================================================================

// Opens the "More actions" dropdown on the first row and clicks the named action.
// rowText defaults to the Published mockLayout — pass mockDraftLayout.layout for Draft tests.
const openDropdownAction = async (label: string, rowText = mockLayout.layout) => {
  await screen.findByText(rowText);
  fireEvent.click(screen.getByRole('button', { name: 'More actions' }));
  fireEvent.click(await screen.findByRole('button', { name: label }));
};

// =============================================================================
// Tests
// =============================================================================

describe('Layouts page - row actions', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(useLayoutActions).mockReturnValue(defaultLayoutActions());
    mockFetchLayouts(SINGLE_LAYOUT);
  });

  // ---------------------------------------------------------------------------
  // Copy (Make a Copy)
  // Available for all layouts. CopyLayoutModal does client-side validation and
  // passes (name, description, copyMediaFiles) to handleConfirmClone.
  // ---------------------------------------------------------------------------
  describe('Copy (Make a Copy)', () => {
    // Name field is auto-filled with an incremented version of the layout name.
    test('modal opens with name field pre-filled from the layout name', async () => {
      renderLayoutsPage();
      await openDropdownAction('Make a Copy');

      const dialog = await screen.findByRole('dialog', { name: 'Copy Layout' });
      // incrementName('My Layout') → 'My Layout (1)'
      expect(within(dialog).getByLabelText('New name')).toHaveValue('My Layout (1)');
    });

    // Empty name must be rejected before calling the action handler.
    test('empty name shows "Name is required" and does not call handleConfirmClone', async () => {
      const handleConfirmClone = vi.fn();
      vi.mocked(useLayoutActions).mockReturnValue(defaultLayoutActions({ handleConfirmClone }));

      renderLayoutsPage();
      await openDropdownAction('Make a Copy');

      const dialog = await screen.findByRole('dialog', { name: 'Copy Layout' });
      fireEvent.change(within(dialog).getByLabelText('New name'), { target: { value: '' } });
      fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }));

      expect(await screen.findByText('Name is required')).toBeInTheDocument();
      expect(handleConfirmClone).not.toHaveBeenCalled();
    });

    // The name 'My Layout' already exists in the table (the one rendered row).
    test('duplicate name shows validation error and does not call handleConfirmClone', async () => {
      const handleConfirmClone = vi.fn();
      vi.mocked(useLayoutActions).mockReturnValue(defaultLayoutActions({ handleConfirmClone }));

      renderLayoutsPage();
      await openDropdownAction('Make a Copy');

      const dialog = await screen.findByRole('dialog', { name: 'Copy Layout' });
      fireEvent.change(within(dialog).getByLabelText('New name'), {
        target: { value: 'My Layout' },
      });
      fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }));

      expect(await screen.findByText('A layout with this name already exists')).toBeInTheDocument();
      expect(handleConfirmClone).not.toHaveBeenCalled();
    });

    // Happy path: valid unique name calls handleConfirmClone with correct args.
    test('Save calls handleConfirmClone with name, empty description, copyMediaFiles=false', async () => {
      const handleConfirmClone = vi.fn();
      vi.mocked(useLayoutActions).mockReturnValue(defaultLayoutActions({ handleConfirmClone }));

      renderLayoutsPage();
      await openDropdownAction('Make a Copy');

      const dialog = await screen.findByRole('dialog', { name: 'Copy Layout' });
      fireEvent.change(within(dialog).getByLabelText('New name'), {
        target: { value: 'My Layout Copy' },
      });
      fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }));

      // Layouts.tsx wraps handleConfirmClone as: (name, desc, copy) => handleConfirmClone(selectedLayout, name, desc, copy)
      expect(handleConfirmClone).toHaveBeenCalledWith(mockLayout, 'My Layout Copy', '', false);
    });

    // Ticking "Make new copies of all media?" flips copyMediaFiles to true.
    test('ticking the media copies checkbox passes copyMediaFiles=true', async () => {
      const handleConfirmClone = vi.fn();
      vi.mocked(useLayoutActions).mockReturnValue(defaultLayoutActions({ handleConfirmClone }));

      renderLayoutsPage();
      await openDropdownAction('Make a Copy');

      const dialog = await screen.findByRole('dialog', { name: 'Copy Layout' });
      fireEvent.change(within(dialog).getByLabelText('New name'), {
        target: { value: 'My Layout Copy' },
      });
      fireEvent.click(
        within(dialog).getByRole('checkbox', { name: 'Make new copies of all media?' }),
      );
      fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }));

      expect(handleConfirmClone).toHaveBeenCalledWith(mockLayout, 'My Layout Copy', '', true);
    });

    // Cancelling must close the modal without calling the action handler.
    test('Cancel closes the modal', async () => {
      renderLayoutsPage();
      await openDropdownAction('Make a Copy');

      const dialog = await screen.findByRole('dialog', { name: 'Copy Layout' });
      fireEvent.click(within(dialog).getByRole('button', { name: 'Cancel' }));

      await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    });
  });

  // ---------------------------------------------------------------------------
  // Publish
  // Only available when publishedStatus !== 'Published' (i.e. Draft layouts).
  // The modal defaults to "Publish Now" so no interaction with PublishDateSelect needed.
  // PublishModal does NOT pass a title to Modal, so the dialog has no aria-label.
  // ---------------------------------------------------------------------------
  describe('Publish', () => {
    beforeEach(() => {
      mockFetchLayouts(SINGLE_DRAFT_LAYOUT);
    });

    // Publish must be absent for already-published layouts.
    test('Publish action is absent for Published layouts', async () => {
      mockFetchLayouts(SINGLE_LAYOUT);
      renderLayoutsPage();
      await screen.findByText(mockLayout.layout);
      fireEvent.click(screen.getByRole('button', { name: 'More actions' }));
      // Wait for the dropdown to open by asserting a known always-present action.
      await screen.findByRole('button', { name: 'Export' });
      expect(screen.queryByRole('button', { name: 'Publish' })).not.toBeInTheDocument();
    });

    // Clicking Publish with the default "Publish Now" calls confirmPublish correctly.
    test('Publish calls confirmPublish with layoutId and { type: "now" }', async () => {
      const confirmPublish = vi.fn();
      vi.mocked(useLayoutActions).mockReturnValue(defaultLayoutActions({ confirmPublish }));

      renderLayoutsPage();
      await openDropdownAction('Publish', mockDraftLayout.layout);

      const dialog = await screen.findByRole('dialog');
      fireEvent.click(within(dialog).getByRole('button', { name: 'Publish' }));

      expect(confirmPublish).toHaveBeenCalledWith(mockDraftLayout.layoutId, { type: 'now' });
    });

    // Cancelling must close the modal.
    test('Cancel closes the modal', async () => {
      renderLayoutsPage();
      await openDropdownAction('Publish', mockDraftLayout.layout);

      const dialog = await screen.findByRole('dialog');
      fireEvent.click(within(dialog).getByRole('button', { name: 'Cancel' }));

      await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    });
  });

  // ---------------------------------------------------------------------------
  // Discard
  // Only available when publishedStatus !== 'Published' (i.e. Draft layouts).
  // DiscardLayoutModal does NOT pass a title to Modal, so the dialog has no aria-label.
  // ---------------------------------------------------------------------------
  describe('Discard', () => {
    beforeEach(() => {
      mockFetchLayouts(SINGLE_DRAFT_LAYOUT);
    });

    // Discard must be absent for already-published layouts.
    test('Discard action is absent for Published layouts', async () => {
      mockFetchLayouts(SINGLE_LAYOUT);
      renderLayoutsPage();
      await screen.findByText(mockLayout.layout);
      fireEvent.click(screen.getByRole('button', { name: 'More actions' }));
      await screen.findByRole('button', { name: 'Export' });
      expect(screen.queryByRole('button', { name: 'Discard' })).not.toBeInTheDocument();
    });

    // Modal shows the layout name so the user knows what they are discarding.
    test('modal opens showing the draft layout name', async () => {
      renderLayoutsPage();
      await openDropdownAction('Discard', mockDraftLayout.layout);

      const dialog = await screen.findByRole('dialog');
      expect(within(dialog).getByText(mockDraftLayout.layout)).toBeInTheDocument();
    });

    // Clicking Discard calls handleConfirmDiscard with the layout's id.
    test('Discard calls handleConfirmDiscard with the layoutId', async () => {
      const handleConfirmDiscard = vi.fn();
      vi.mocked(useLayoutActions).mockReturnValue(defaultLayoutActions({ handleConfirmDiscard }));

      renderLayoutsPage();
      await openDropdownAction('Discard', mockDraftLayout.layout);

      const dialog = await screen.findByRole('dialog');
      fireEvent.click(within(dialog).getByRole('button', { name: 'Discard' }));

      expect(handleConfirmDiscard).toHaveBeenCalledWith(mockDraftLayout.layoutId);
    });

    // Cancelling must close the modal.
    test('Cancel closes the modal', async () => {
      renderLayoutsPage();
      await openDropdownAction('Discard', mockDraftLayout.layout);

      const dialog = await screen.findByRole('dialog');
      fireEvent.click(within(dialog).getByRole('button', { name: 'Cancel' }));

      await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    });
  });

  // ---------------------------------------------------------------------------
  // Export
  // Available for all layouts. No modal title, so the dialog has no aria-label.
  // Download logic lives in useLayoutActions (mocked), no window stubbing needed.
  // ---------------------------------------------------------------------------
  describe('Export', () => {
    // Filename field is pre-filled as "export_<layoutName>".
    test('modal opens with filename pre-filled as "export_My Layout"', async () => {
      renderLayoutsPage();
      await openDropdownAction('Export');

      const dialog = await screen.findByRole('dialog');
      expect(within(dialog).getByDisplayValue(`export_${mockLayout.layout}`)).toBeInTheDocument();
    });

    // Export button calls handleExportLayout with the current filename and default options.
    test('Export calls handleExportLayout with filename and default options', async () => {
      const handleExportLayout = vi.fn();
      vi.mocked(useLayoutActions).mockReturnValue(defaultLayoutActions({ handleExportLayout }));

      renderLayoutsPage();
      await openDropdownAction('Export');

      const dialog = await screen.findByRole('dialog');
      fireEvent.click(within(dialog).getByRole('button', { name: 'Export' }));

      expect(handleExportLayout).toHaveBeenCalledWith(mockLayout.layoutId, {
        includeData: false,
        includeFallback: false,
        fileName: `export_${mockLayout.layout}`,
      });
    });

    // The two checkboxes are independent — each only sets its own value.
    // Ticking "Datasets Data" passes includeData: true.
    test('ticking Datasets Data passes includeData=true', async () => {
      const handleExportLayout = vi.fn();
      vi.mocked(useLayoutActions).mockReturnValue(defaultLayoutActions({ handleExportLayout }));

      renderLayoutsPage();
      await openDropdownAction('Export');

      const dialog = await screen.findByRole('dialog');
      fireEvent.click(within(dialog).getByRole('checkbox', { name: 'Datasets Data' }));
      fireEvent.click(within(dialog).getByRole('button', { name: 'Export' }));

      expect(handleExportLayout).toHaveBeenCalledWith(mockLayout.layoutId, {
        includeData: true,
        includeFallback: false,
        fileName: `export_${mockLayout.layout}`,
      });
    });

    // Ticking "Widget Fallback Data" passes includeFallback: true.
    test('ticking Widget Fallback Data passes includeFallback=true', async () => {
      const handleExportLayout = vi.fn();
      vi.mocked(useLayoutActions).mockReturnValue(defaultLayoutActions({ handleExportLayout }));

      renderLayoutsPage();
      await openDropdownAction('Export');

      const dialog = await screen.findByRole('dialog');
      fireEvent.click(within(dialog).getByRole('checkbox', { name: 'Widget Fallback Data' }));
      fireEvent.click(within(dialog).getByRole('button', { name: 'Export' }));

      expect(handleExportLayout).toHaveBeenCalledWith(mockLayout.layoutId, {
        includeData: false,
        includeFallback: true,
        fileName: `export_${mockLayout.layout}`,
      });
    });

    // Ticking both checkboxes passes includeData: true and includeFallback: true.
    test('ticking both checkboxes passes includeData=true and includeFallback=true', async () => {
      const handleExportLayout = vi.fn();
      vi.mocked(useLayoutActions).mockReturnValue(defaultLayoutActions({ handleExportLayout }));

      renderLayoutsPage();
      await openDropdownAction('Export');

      const dialog = await screen.findByRole('dialog');
      fireEvent.click(within(dialog).getByRole('checkbox', { name: 'Datasets Data' }));
      fireEvent.click(within(dialog).getByRole('checkbox', { name: 'Widget Fallback Data' }));
      fireEvent.click(within(dialog).getByRole('button', { name: 'Export' }));

      expect(handleExportLayout).toHaveBeenCalledWith(mockLayout.layoutId, {
        includeData: true,
        includeFallback: true,
        fileName: `export_${mockLayout.layout}`,
      });
    });

    // Cancelling must close the modal.
    test('Cancel closes the modal', async () => {
      renderLayoutsPage();
      await openDropdownAction('Export');

      const dialog = await screen.findByRole('dialog');
      fireEvent.click(within(dialog).getByRole('button', { name: 'Cancel' }));

      await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    });
  });

  // ---------------------------------------------------------------------------
  // Save as Template, Published layouts only.
  // ---------------------------------------------------------------------------
  describe('Save as Template', () => {
    // Name field is pre-filled as "<layout.layout> Template".
    test('modal opens with name pre-filled as "My Layout Template"', async () => {
      renderLayoutsPage();
      await openDropdownAction('Save as Template');

      const dialog = await screen.findByRole('dialog', { name: 'Save as Template' });
      expect(within(dialog).getByLabelText('Template Name')).toHaveValue(
        `${mockLayout.layout} Template`,
      );
    });

    // Save is disabled when name is empty — the button greys out instead of showing an error.
    test('Save button is disabled when name is empty', async () => {
      renderLayoutsPage();
      await openDropdownAction('Save as Template');

      const dialog = await screen.findByRole('dialog', { name: 'Save as Template' });
      fireEvent.change(within(dialog).getByLabelText('Template Name'), {
        target: { value: '' },
      });

      expect(within(dialog).getByRole('button', { name: 'Save' })).toBeDisabled();
      expect(saveLayoutAsTemplate).not.toHaveBeenCalled();
    });

    // Happy path: valid name calls saveLayoutAsTemplate and closes the modal.
    test('Save calls saveLayoutAsTemplate with correct payload and closes modal', async () => {
      vi.mocked(saveLayoutAsTemplate).mockResolvedValueOnce(undefined as never);

      renderLayoutsPage();
      await openDropdownAction('Save as Template');

      await screen.findByRole('dialog', { name: 'Save as Template' });
      fireEvent.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
      expect(saveLayoutAsTemplate).toHaveBeenCalledWith(
        mockLayout.layoutId,
        expect.objectContaining({
          name: `${mockLayout.layout} Template`,
          includeWidgets: false,
          folderId: mockLayout.folderId,
        }),
      );
    });

    // Cancelling must close the modal.
    test('Cancel closes the modal', async () => {
      renderLayoutsPage();
      await openDropdownAction('Save as Template');

      const dialog = await screen.findByRole('dialog', { name: 'Save as Template' });
      fireEvent.click(within(dialog).getByRole('button', { name: 'Cancel' }));

      await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    });
  });

  // ---------------------------------------------------------------------------
  // Retire
  // Available for all layouts. The Retire button is gated behind a checkbox so
  // the user must explicitly confirm before the API call is made.
  // RetireLayoutModal calls updateLayout directly (not via useLayoutActions).
  // ---------------------------------------------------------------------------
  describe('Retire', () => {
    // Modal has a proper title that can be used to scope assertions.
    test('modal opens titled "Retire Layout"', async () => {
      renderLayoutsPage();
      await openDropdownAction('Retire');

      expect(await screen.findByRole('dialog', { name: 'Retire Layout' })).toBeInTheDocument();
    });

    // The Retire button is disabled until the user ticks the confirmation checkbox.
    test('Retire button is disabled until the checkbox is checked', async () => {
      renderLayoutsPage();
      await openDropdownAction('Retire');

      const dialog = await screen.findByRole('dialog', { name: 'Retire Layout' });
      const retireBtn = within(dialog).getByRole('button', { name: 'Retire' });

      expect(retireBtn).toBeDisabled();

      fireEvent.click(within(dialog).getByRole('checkbox', { name: 'Retire Layout' }));
      expect(retireBtn).toBeEnabled();
    });

    // After confirming, updateLayout is called with the retired flag and the modal closes.
    test('confirming calls updateLayout with { retired: 1 } and closes modal', async () => {
      vi.mocked(updateLayout).mockResolvedValueOnce(mockLayout);

      renderLayoutsPage();
      await openDropdownAction('Retire');

      const dialog = await screen.findByRole('dialog', { name: 'Retire Layout' });
      fireEvent.click(within(dialog).getByRole('checkbox', { name: 'Retire Layout' }));
      fireEvent.click(within(dialog).getByRole('button', { name: 'Retire' }));

      await waitFor(() =>
        expect(updateLayout).toHaveBeenCalledWith(mockLayout.layoutId, { retired: 1 }),
      );
      await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    });

    // Cancelling must close the modal without calling updateLayout.
    test('Cancel closes the modal', async () => {
      renderLayoutsPage();
      await openDropdownAction('Retire');

      const dialog = await screen.findByRole('dialog', { name: 'Retire Layout' });
      fireEvent.click(within(dialog).getByRole('button', { name: 'Cancel' }));

      await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    });
  });

  // ---------------------------------------------------------------------------
  // Enable Stats Collection
  // Available for all layouts. EnableStatsLayoutModal calls updateLayout directly.
  // ---------------------------------------------------------------------------
  describe('Enable Stats Collection', () => {
    // Checkbox initialises from layout.enableStat — mockLayout has enableStat: true.
    test('checkbox initialises as checked when layout.enableStat is true', async () => {
      renderLayoutsPage();
      await openDropdownAction('Enable Stats Collection');

      const dialog = await screen.findByRole('dialog', { name: 'Enable Stats Collection' });
      expect(
        within(dialog).getByRole('checkbox', { name: 'Enable Stats Collections' }),
      ).toBeChecked();
    });

    // Save calls updateLayout with enableStat: 1 (checkbox was already checked).
    test('Save calls updateLayout with { enableStat: 1 }', async () => {
      vi.mocked(updateLayout).mockResolvedValueOnce(mockLayout);

      renderLayoutsPage();
      await openDropdownAction('Enable Stats Collection');

      const dialog = await screen.findByRole('dialog', { name: 'Enable Stats Collection' });
      fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }));

      await waitFor(() =>
        expect(updateLayout).toHaveBeenCalledWith(mockLayout.layoutId, { enableStat: 1 }),
      );
    });

    // Cancelling must close the modal.
    test('Cancel closes the modal', async () => {
      renderLayoutsPage();
      await openDropdownAction('Enable Stats Collection');

      const dialog = await screen.findByRole('dialog', { name: 'Enable Stats Collection' });
      fireEvent.click(within(dialog).getByRole('button', { name: 'Cancel' }));

      await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    });
  });
});
