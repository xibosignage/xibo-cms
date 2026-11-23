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
// A layout is shared through its campaign, so every Share on the Layouts page
// must hand the Share modal campaign ids, never layout ids. Before the fix for
// xibo#3996, Share Selected and the previewer's Share sent layout ids, so
// permissions were saved on whichever campaign happened to have that number.
//
// The fixtures keep the two ids far apart (layout 861001 is campaign 10), so a
// wrong field can't pass by coincidence. ShareModal is stubbed to show what it
// was given; its own behaviour is tested elsewhere.
// =============================================================================

import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { useLayoutActions } from '../hooks/useLayoutActions';

import {
  defaultLayoutActions,
  mockFetchLayouts,
  mockLayout,
  renderLayoutsPage,
} from './layoutTestUtils';

import type * as TagInputModule from '@/components/ui/forms/TagInput';
import { testQueryClient } from '@/setupTests';
import { knownFailure } from '@/testUtils/knownFailure';
import { openFirstRowMenu, selectAllRowsAndWait } from '@/testUtils/rtl';
import { sharedWith } from '@/testUtils/shareModalStub';
import type { Layout } from '@/types/layout';

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
vi.mock('@/components/ui/forms/TagInput', async (importOriginal) => {
  const actual = await importOriginal<typeof TagInputModule>();
  return { ...actual, default: () => null };
});
vi.mock('@/components/ui/modals/Modal');

// Shows what the Share modal was opened for.
vi.mock('@/components/ui/modals/ShareModal', async () => ({
  default: (await import('@/testUtils/shareModalStub')).ShareModalStub,
}));

const secondLayout: Layout = {
  ...mockLayout,
  layoutId: 861002,
  campaignId: 20,
  layout: 'Second Layout',
};

/** The same layout, but the user may not change who it is shared with. */
const notShareable = (item: Layout): Layout => ({
  ...item,
  userPermissions: { ...item.userPermissions!, modifyPermissions: 0 },
});

beforeEach(() => {
  testQueryClient.clear();
  vi.clearAllMocks();
  vi.mocked(useLayoutActions).mockReturnValue(defaultLayoutActions());
  mockFetchLayouts({ rows: [mockLayout, secondLayout], totalCount: 2 } as never);
});

describe('Layouts page - Share sends campaign ids', () => {
  test('row Share opens Share for the layout’s campaign', async () => {
    const user = userEvent.setup();
    renderLayoutsPage();

    await openFirstRowMenu(user, mockLayout.layout);
    await user.click(screen.getByRole('button', { name: 'Share' }));

    expect(await sharedWith()).toEqual({
      ids: mockLayout.campaignId,
      type: 'campaign',
      title: 'Share Layout',
    });
  });

  test('Share Selected opens Share for every selected layout’s campaign', async () => {
    const user = userEvent.setup();
    renderLayoutsPage();

    await selectAllRowsAndWait(user, mockLayout.layout);
    await user.click(screen.getByRole('button', { name: 'Share' }));

    expect(await sharedWith()).toEqual({
      ids: [mockLayout.campaignId, secondLayout.campaignId],
      type: 'campaign',
      title: 'Share Layout',
    });
  });

  test('Share from the previewer opens Share for that layout’s campaign, not its layout id', async () => {
    const user = userEvent.setup();
    renderLayoutsPage();
    await screen.findByText(mockLayout.layout);

    await user.click(screen.getAllByRole('button', { name: 'Preview media' })[0]!);
    await screen.findByRole('heading', { name: mockLayout.layout });
    await user.click(screen.getByRole('button', { name: 'Share' }));

    expect(await sharedWith()).toEqual({
      ids: mockLayout.campaignId,
      type: 'campaign',
      title: 'Share Layout',
    });
  });

  // https://github.com/xibosignage/xibo/issues/4117 (open). Share Selected sends every selected
  // layout's campaign, including ones the user can't share (the row menu hides Share for those),
  // and the CMS then refuses the whole selection. Displays leaves them out and says how many.
  knownFailure(
    'Share Selected leaves out layouts the user cannot share',
    async () => {
      mockFetchLayouts({ rows: [mockLayout, notShareable(secondLayout)], totalCount: 2 } as never);
      const user = userEvent.setup();
      renderLayoutsPage();
      await selectAllRowsAndWait(user, mockLayout.layout);
      await user.click(screen.getByRole('button', { name: 'Share' }));
    },
    async () => expect((await sharedWith()).ids).toEqual([mockLayout.campaignId]),
  );
});
