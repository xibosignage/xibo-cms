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

import { useTemplateActions } from '../hooks/useTemplateActions';

import {
  SINGLE_DRAFT_TEMPLATE_ROWS,
  SINGLE_TEMPLATE_ROWS,
  defaultTemplateActions,
  mockDraftTemplate,
  mockFetchTemplates,
  mockTemplate,
  renderTemplatesPage,
} from './templateTestUtils';
import type { UseTemplateActionsReturn } from './templateTestUtils';

import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key: string) => key, i18n: { changeLanguage: vi.fn() } }),
  Trans: ({ children }: { children: React.ReactNode }) => children,
}));

vi.mock('@/services/folderApi');
vi.mock('@/services/templatesApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));

vi.mock('../hooks/useTemplateActions', () => ({ useTemplateActions: vi.fn() }));
vi.mock('../hooks/useTemplateFilterOptions', () => ({
  useTemplateFilterOptions: vi.fn(() => ({ filterOptions: [], isLoading: false })),
}));

vi.mock('@/components/ui/FolderActionModals', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Helpers
// =============================================================================

// Opens the "More actions" dropdown on the first row and clicks the named action.
// rowText defaults to the Published mockTemplate — pass mockDraftTemplate.layout for Draft tests.
const openDropdownAction = async (label: string, rowText = mockTemplate.layout) => {
  await screen.findByText(rowText);
  fireEvent.click(screen.getByRole('button', { name: 'More actions' }));
  fireEvent.click(await screen.findByRole('button', { name: label }));
};

// =============================================================================
// Tests
// =============================================================================

describe('Templates page - row actions', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(useTemplateActions).mockReturnValue(defaultTemplateActions());
    mockFetchTemplates(SINGLE_TEMPLATE_ROWS);
  });

  // ---------------------------------------------------------------------------
  // Alter Template
  // Opens the template designer in a new tab. Fully implemented via
  // handleAlterTemplate in useTemplateActions.
  // ---------------------------------------------------------------------------
  describe('Alter Template', () => {
    test('clicking Alter Template calls handleAlterTemplate with the layoutId', async () => {
      const handleAlterTemplate = vi.fn();
      vi.mocked(useTemplateActions).mockReturnValue(
        defaultTemplateActions({ handleAlterTemplate }),
      );

      renderTemplatesPage();
      await openDropdownAction('Alter Template');

      expect(handleAlterTemplate).toHaveBeenCalledWith(mockTemplate.layoutId);
    });
  });

  // ---------------------------------------------------------------------------
  // Copy (Make a Copy)
  // Available for all templates (Draft and Published).
  // The modal validates the form and calls handleConfirmClone.
  // ---------------------------------------------------------------------------
  describe('Copy (Make a Copy)', () => {
    // Name field is auto-filled with an incremented version of the template name.
    test('modal opens with name field pre-filled from the template name', async () => {
      renderTemplatesPage();
      await openDropdownAction('Make a Copy');

      const dialog = await screen.findByRole('dialog', { name: 'Copy Template' });
      // incrementName('Splash Screen Template') → 'Splash Screen Template (1)'
      expect(within(dialog).getByLabelText('New name')).toHaveValue('Splash Screen Template (1)');
    });

    // Empty name must be rejected before calling the action handler.
    test('empty name shows "Name is required" and does not call handleConfirmClone', async () => {
      const handleConfirmClone = vi.fn();
      vi.mocked(useTemplateActions).mockReturnValue(defaultTemplateActions({ handleConfirmClone }));

      renderTemplatesPage();
      await openDropdownAction('Make a Copy');

      const dialog = await screen.findByRole('dialog', { name: 'Copy Template' });
      fireEvent.change(within(dialog).getByLabelText('New name'), { target: { value: '' } });
      fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }));

      expect(await screen.findByText('Name is required')).toBeInTheDocument();
      expect(handleConfirmClone).not.toHaveBeenCalled();
    });

    // The name 'Splash Screen Template' already exists in the table.
    test('duplicate name shows "A template with this name already exists"', async () => {
      const handleConfirmClone = vi.fn();
      vi.mocked(useTemplateActions).mockReturnValue(defaultTemplateActions({ handleConfirmClone }));

      renderTemplatesPage();
      await openDropdownAction('Make a Copy');

      const dialog = await screen.findByRole('dialog', { name: 'Copy Template' });
      fireEvent.change(within(dialog).getByLabelText('New name'), {
        target: { value: mockTemplate.layout },
      });
      fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }));

      expect(
        await screen.findByText('A template with this name already exists'),
      ).toBeInTheDocument();
      expect(handleConfirmClone).not.toHaveBeenCalled();
    });

    // Happy path: valid unique name calls handleConfirmClone with correct args.
    test('Save calls handleConfirmClone with selectedTemplate, name, empty description, and copyMediaFiles=false', async () => {
      const handleConfirmClone = vi.fn();
      vi.mocked(useTemplateActions).mockReturnValue(defaultTemplateActions({ handleConfirmClone }));

      renderTemplatesPage();
      await openDropdownAction('Make a Copy');

      const dialog = await screen.findByRole('dialog', { name: 'Copy Template' });
      fireEvent.change(within(dialog).getByLabelText('New name'), {
        target: { value: 'Splash Screen Template Copy' },
      });
      fireEvent.change(within(dialog).getByLabelText('Description'), { target: { value: '' } });
      fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }));

      expect(handleConfirmClone).toHaveBeenCalledWith(
        mockTemplate,
        'Splash Screen Template Copy',
        '',
        false,
      );
    });

    // Ticking "Make new copies of all media?" flips copyMediaFiles to true.
    test('ticking the media copies checkbox passes copyMediaFiles=true', async () => {
      const handleConfirmClone = vi.fn();
      vi.mocked(useTemplateActions).mockReturnValue(defaultTemplateActions({ handleConfirmClone }));

      renderTemplatesPage();
      await openDropdownAction('Make a Copy');

      const dialog = await screen.findByRole('dialog', { name: 'Copy Template' });
      fireEvent.change(within(dialog).getByLabelText('New name'), {
        target: { value: 'Splash Screen Template Copy' },
      });
      fireEvent.change(within(dialog).getByLabelText('Description'), { target: { value: '' } });
      fireEvent.click(
        within(dialog).getByRole('checkbox', { name: 'Make new copies of all media?' }),
      );
      fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }));

      expect(handleConfirmClone).toHaveBeenCalledWith(
        mockTemplate,
        'Splash Screen Template Copy',
        '',
        true,
      );
    });

    // Cancelling must close the modal without calling the action handler.
    test('Cancel closes the modal', async () => {
      renderTemplatesPage();
      await openDropdownAction('Make a Copy');

      const dialog = await screen.findByRole('dialog', { name: 'Copy Template' });
      fireEvent.click(within(dialog).getByRole('button', { name: 'Cancel' }));

      await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    });
  });

  // ---------------------------------------------------------------------------
  // Publish
  // TDD: Publish should appear only for Draft templates and open a confirmation
  // dialog that calls confirmPublish on the hook.
  //
  // All tests use test.fails to document the unimplemented contract.
  // ---------------------------------------------------------------------------
  describe('Publish', () => {
    beforeEach(() => {
      mockFetchTemplates(SINGLE_DRAFT_TEMPLATE_ROWS);
    });

    // When implemented: Publish must be absent for already-published templates.
    test.fails('Publish action is absent for Published templates', async () => {
      mockFetchTemplates(SINGLE_TEMPLATE_ROWS);
      renderTemplatesPage();
      await screen.findByText(mockTemplate.layout);
      fireEvent.click(screen.getByRole('button', { name: 'More actions' }));
      // Wait for a known always-present action to confirm the dropdown is open.
      await screen.findByRole('button', { name: 'Make a Copy' });
      expect(screen.queryByRole('button', { name: 'Publish' })).not.toBeInTheDocument();
    });

    // When implemented: clicking Publish on a Draft template should open a dialog.
    test.fails('Publish modal opens for a Draft template', async () => {
      renderTemplatesPage();
      await openDropdownAction('Publish', mockDraftTemplate.layout);

      expect(await screen.findByRole('dialog')).toBeInTheDocument();
    });

    // When implemented: confirming should call confirmPublish with the layoutId and publish options.
    test.fails('Publish calls confirmPublish with layoutId and { type: "now" }', async () => {
      const confirmPublish = vi.fn();
      vi.mocked(useTemplateActions).mockReturnValue({
        ...defaultTemplateActions(),
        confirmPublish,
      } as unknown as UseTemplateActionsReturn);

      renderTemplatesPage();
      await openDropdownAction('Publish', mockDraftTemplate.layout);

      const dialog = await screen.findByRole('dialog');
      fireEvent.click(within(dialog).getByRole('button', { name: 'Publish' }));

      expect(confirmPublish).toHaveBeenCalledWith(mockDraftTemplate.layoutId, { type: 'now' });
    });

    // When implemented: Cancel must close the dialog without publishing.
    test.fails('Cancel closes the Publish modal', async () => {
      renderTemplatesPage();
      await openDropdownAction('Publish', mockDraftTemplate.layout);

      const dialog = await screen.findByRole('dialog');
      fireEvent.click(within(dialog).getByRole('button', { name: 'Cancel' }));

      await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    });
  });

  // ---------------------------------------------------------------------------
  // Discard
  // TDD: Discard should appear only for Draft templates and open a confirmation
  // dialog that calls handleConfirmDiscard on the hook.
  //
  // All tests use test.fails to document the unimplemented contract.
  // ---------------------------------------------------------------------------
  describe('Discard', () => {
    beforeEach(() => {
      mockFetchTemplates(SINGLE_DRAFT_TEMPLATE_ROWS);
    });

    // When implemented: Discard must be absent for already-published templates.
    test.fails('Discard action is absent for Published templates', async () => {
      mockFetchTemplates(SINGLE_TEMPLATE_ROWS);
      renderTemplatesPage();
      await screen.findByText(mockTemplate.layout);
      fireEvent.click(screen.getByRole('button', { name: 'More actions' }));
      // Wait for a known always-present action to confirm the dropdown is open.
      await screen.findByRole('button', { name: 'Make a Copy' });
      expect(screen.queryByRole('button', { name: 'Discard' })).not.toBeInTheDocument();
    });

    // When implemented: the dialog should show the draft template name so the
    // user knows what they are about to discard.
    test.fails('Discard modal shows the draft template name', async () => {
      renderTemplatesPage();
      await openDropdownAction('Discard', mockDraftTemplate.layout);

      const dialog = await screen.findByRole('dialog');
      expect(within(dialog).getByText(mockDraftTemplate.layout)).toBeInTheDocument();
    });

    // When implemented: confirming should call handleConfirmDiscard with the layoutId.
    test.fails('Discard calls handleConfirmDiscard with the layoutId', async () => {
      const handleConfirmDiscard = vi.fn();
      vi.mocked(useTemplateActions).mockReturnValue({
        ...defaultTemplateActions(),
        handleConfirmDiscard,
      } as unknown as UseTemplateActionsReturn);

      renderTemplatesPage();
      await openDropdownAction('Discard', mockDraftTemplate.layout);

      const dialog = await screen.findByRole('dialog');
      fireEvent.click(within(dialog).getByRole('button', { name: 'Discard' }));

      expect(handleConfirmDiscard).toHaveBeenCalledWith(mockDraftTemplate.layoutId);
    });

    // When implemented: Cancel must close the dialog without discarding.
    test.fails('Cancel closes the Discard modal', async () => {
      renderTemplatesPage();
      await openDropdownAction('Discard', mockDraftTemplate.layout);

      const dialog = await screen.findByRole('dialog');
      fireEvent.click(within(dialog).getByRole('button', { name: 'Cancel' }));

      await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    });
  });

  // ---------------------------------------------------------------------------
  // Checkout
  // TDD: Checkout should appear for Published templates only, allowing the user
  // to check out a published template for editing (creating a Draft).
  // ---------------------------------------------------------------------------
  describe('Checkout', () => {
    // Checkout should only appear for Published templates.
    test('Checkout action is absent for Draft templates', async () => {
      mockFetchTemplates(SINGLE_DRAFT_TEMPLATE_ROWS);
      renderTemplatesPage();
      await screen.findByText(mockDraftTemplate.layout);
      fireEvent.click(screen.getByRole('button', { name: 'More actions' }));
      // Wait for a known always-present action to confirm the dropdown is open.
      await screen.findByRole('button', { name: 'Make a Copy' });
      expect(screen.queryByRole('button', { name: 'Checkout' })).not.toBeInTheDocument();
    });

    // Checkout must appear in the dropdown for Published templates.
    test.fails('Checkout action appears for Published templates', async () => {
      renderTemplatesPage();
      await screen.findByText(mockTemplate.layout);
      fireEvent.click(screen.getByRole('button', { name: 'More actions' }));

      expect(await screen.findByRole('button', { name: 'Checkout' })).toBeInTheDocument();
    });

    // When implemented: clicking Checkout should call handleCheckoutTemplate with the layoutId.
    test.fails('Checkout calls handleCheckoutTemplate with the layoutId', async () => {
      const handleCheckoutTemplate = vi.fn();
      vi.mocked(useTemplateActions).mockReturnValue({
        ...defaultTemplateActions(),
        handleCheckoutTemplate,
      } as unknown as UseTemplateActionsReturn);

      renderTemplatesPage();
      await openDropdownAction('Checkout');

      expect(handleCheckoutTemplate).toHaveBeenCalledWith(mockTemplate.layoutId);
    });

    // When implemented: Cancel must close the Checkout dialog.
    test.fails('Cancel closes the Checkout modal', async () => {
      renderTemplatesPage();
      await openDropdownAction('Checkout');

      const dialog = await screen.findByRole('dialog');
      fireEvent.click(within(dialog).getByRole('button', { name: 'Cancel' }));

      await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    });
  });

  // Export tests will be added when the Template export modal is implemented.
  // The backend uses the same endpoint as Layout export (POST /layout/export/{id}).
  // Use Layouts.actions.test.tsx Export tests as the reference for what to cover.
});
