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
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  mockDisplayManagerUser,
  mockFetchLayouts,
  mockLayout,
  renderLayoutsPage,
  SINGLE_LAYOUT,
} from './layoutTestUtils';

import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/folderApi');
vi.mock('@/services/layoutsApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
  fetchUsers: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
}));
vi.mock('@/hooks/useOwner', () => ({
  useOwner: vi.fn().mockReturnValue({ owner: null, loading: false }),
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('../components/LayoutPreviewer', () => ({ default: () => null }));

// =============================================================================
// Part 1 — column picker visibility
// =============================================================================

describe('Layouts page - column visibility', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchLayouts(SINGLE_LAYOUT);
  });

  // ---------------------------------------------------------------------------
  // "Code" is off by default in the columnVisibility config in Layouts.tsx.
  // ---------------------------------------------------------------------------
  test('"Code" column header is not visible by default', async () => {
    renderLayoutsPage();

    await screen.findByText(mockLayout.layout);

    expect(screen.queryByRole('columnheader', { name: /^code$/i })).not.toBeInTheDocument();
  });

  test('clicking the Columns button opens the column picker dropdown', async () => {
    const user = userEvent.setup();
    renderLayoutsPage();
    await screen.findByText(mockLayout.layout);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));

    expect(screen.getByRole('checkbox', { name: /^code$/i })).toBeInTheDocument();
  });

  test('checking a hidden column checkbox shows that column in the table', async () => {
    const user = userEvent.setup();
    renderLayoutsPage();
    await screen.findByText(mockLayout.layout);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));

    const checkbox = screen.getByRole('checkbox', { name: /^code$/i });
    expect(checkbox).not.toBeChecked();

    await user.click(checkbox);

    expect(await screen.findByRole('columnheader', { name: /^code$/i })).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // "Owner" is visible by default and is hideable (no enableHiding: false).
  // ---------------------------------------------------------------------------
  test('unchecking a visible column checkbox hides that column from the table', async () => {
    const user = userEvent.setup();
    renderLayoutsPage();
    await screen.findByRole('columnheader', { name: /^owner$/i });

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));

    const ownerCheckbox = screen.getByRole('checkbox', { name: /^owner$/i });
    expect(ownerCheckbox).toBeChecked();

    await user.click(ownerCheckbox);

    expect(screen.queryByRole('columnheader', { name: /^owner$/i })).not.toBeInTheDocument();
  });
});

// =============================================================================
// Part 2 — permission-gated actions
//
// The "content manager permission gap": Layouts.tsx only computes
// canAddToFolder from hasFeature(user, 'layout.add'), and LayoutConfig.tsx's
// getLayoutItemActions only adds the Edit action when
// hasFeature(user, 'layout.modify') AND the row's userPermissions.edit are
// both true. Neither gate had a test before this file. mockUser (superAdmin)
// bypasses hasFeature entirely; mockDisplayManagerUser is a real persona
// capture with no layout.add / layout.modify feature, so it exercises the
// actual denial path.
// =============================================================================

describe('Layouts page — Add Layout permission (layout.add)', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchLayouts(SINGLE_LAYOUT);
  });

  test('Add Layout is enabled for a user with layout.add', async () => {
    renderLayoutsPage();
    await screen.findByText(mockLayout.layout);

    expect(screen.getByRole('button', { name: /^add layout$/i })).toBeEnabled();
  });

  test('Add Layout is disabled for a user without layout.add', async () => {
    renderLayoutsPage(mockDisplayManagerUser);
    await screen.findByText(mockLayout.layout);

    expect(screen.getByRole('button', { name: /^add layout$/i })).toBeDisabled();
  });
});

describe('Layouts page — Edit row action permission (layout.modify)', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchLayouts(SINGLE_LAYOUT);
  });

  test('Edit quick action is visible for a user with layout.modify', async () => {
    renderLayoutsPage();
    await screen.findByText(mockLayout.layout);

    expect(screen.getByRole('button', { name: /^edit$/i })).toBeInTheDocument();
  });

  // getLayoutItemActions adds "Edit" both as a quick action and as a menu
  // item when canModify && canEdit, so with the dropdown open there are two
  // "Edit" buttons — assert the count grows from 1 (quick action only) to 2
  // (quick action + menu item) rather than querying a single match.
  test('Edit action is visible in the more-actions dropdown for a user with layout.modify', async () => {
    const user = userEvent.setup();
    renderLayoutsPage();
    await screen.findByText(mockLayout.layout);

    expect(screen.getAllByRole('button', { name: /^edit$/i })).toHaveLength(1);

    await user.click(screen.getByRole('button', { name: 'More actions' }));

    await waitFor(() => {
      expect(screen.getAllByRole('button', { name: /^edit$/i })).toHaveLength(2);
    });
  });

  test('Edit quick action is hidden for a user without layout.modify', async () => {
    renderLayoutsPage(mockDisplayManagerUser);
    await screen.findByText(mockLayout.layout);

    expect(screen.queryByRole('button', { name: /^edit$/i })).not.toBeInTheDocument();
  });

  test('Edit action is hidden in the more-actions dropdown for a user without layout.modify — Details still visible', async () => {
    const user = userEvent.setup();
    renderLayoutsPage(mockDisplayManagerUser);
    await screen.findByText(mockLayout.layout);

    await user.click(screen.getByRole('button', { name: 'More actions' }));

    // Anchor on an action that is always present, to confirm the dropdown is
    // fully open before asserting Edit's absence.
    await screen.findByRole('button', { name: /^details$/i });
    expect(screen.queryByRole('button', { name: /^edit$/i })).not.toBeInTheDocument();
  });
});
