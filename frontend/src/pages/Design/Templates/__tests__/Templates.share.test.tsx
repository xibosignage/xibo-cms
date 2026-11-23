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

// =============================================================================
// Test type: Page test
// A template is shared through its campaign, like a layout, so every Share on
// the Templates page must hand the Share modal campaign ids, never layout ids.
// This is the same shape as xibo#3996 on the Layouts page, which shipped
// with no test; Templates had no Share test at all.
//
// The fixtures keep the two ids far apart (template 861001 is campaign 10), so
// a wrong field can't pass by coincidence. ShareModal is stubbed to show what
// it was given; its own behaviour is tested elsewhere.
// =============================================================================

import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { useTemplateActions } from '../hooks/useTemplateActions';

import {
  defaultTemplateActions,
  mockFetchTemplates,
  mockTemplate,
  renderTemplatesPage,
} from './templateTestUtils';

import { testQueryClient } from '@/setupTests';
import { knownFailure } from '@/testUtils/knownFailure';
import { selectAllRowsAndWait } from '@/testUtils/rtl';
import { sharedWith } from '@/testUtils/shareModalStub';
import type { Template } from '@/types/templates';

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

// Shows what the Share modal was opened for.
vi.mock('@/components/ui/modals/ShareModal', async () => ({
  default: (await import('@/testUtils/shareModalStub')).ShareModalStub,
}));

const secondTemplate: Template = {
  ...mockTemplate,
  layoutId: 861002,
  campaignId: 20,
  layout: 'Second Template',
};

/** The same template, but the user may not change who it is shared with. */
const notShareable = (item: Template): Template => ({
  ...item,
  userPermissions: { ...item.userPermissions!, modifyPermissions: 0 },
});

beforeEach(() => {
  testQueryClient.clear();
  vi.clearAllMocks();
  vi.mocked(useTemplateActions).mockReturnValue(defaultTemplateActions());
  mockFetchTemplates({ rows: [mockTemplate, secondTemplate], totalCount: 2 });
});

describe('Templates page - Share sends campaign ids', () => {
  test('row Share opens Share for the template’s campaign', async () => {
    const user = userEvent.setup();
    renderTemplatesPage();

    await screen.findByText(mockTemplate.layout);
    await user.click(screen.getAllByRole('button', { name: 'More actions' })[0]!);
    await user.click(await screen.findByRole('button', { name: 'Share' }));

    expect(await sharedWith()).toEqual({
      ids: mockTemplate.campaignId,
      type: 'campaign',
      title: 'Share Template',
    });
  });

  test('Share Selected opens Share for every selected template’s campaign', async () => {
    const user = userEvent.setup();
    renderTemplatesPage();

    await selectAllRowsAndWait(user, mockTemplate.layout);
    await user.click(screen.getByRole('button', { name: 'Share' }));

    expect(await sharedWith()).toEqual({
      ids: [mockTemplate.campaignId, secondTemplate.campaignId],
      type: 'campaign',
      title: 'Share Template',
    });
  });

  // https://github.com/xibosignage/xibo/issues/4117 (open). Share Selected sends every selected
  // template's campaign, including ones the user can't share (the row menu hides Share for those),
  // and the CMS then refuses the whole selection. Displays leaves them out and says how many.
  knownFailure(
    'Share Selected leaves out templates the user cannot share',
    async () => {
      mockFetchTemplates({ rows: [mockTemplate, notShareable(secondTemplate)], totalCount: 2 });
      const user = userEvent.setup();
      renderTemplatesPage();
      await selectAllRowsAndWait(user, mockTemplate.layout);
      await user.click(screen.getByRole('button', { name: 'Share' }));
    },
    async () => expect((await sharedWith()).ids).toEqual([mockTemplate.campaignId]),
  );
});
