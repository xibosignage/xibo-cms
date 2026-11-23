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

import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ComponentProps } from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import LayoutPreviewer from '../../components/LayoutPreviewer';
import { mockLayout, mockUser } from '../layoutTestUtils';

import { fetchUsers } from '@/services/userApi';
import type { FetchUsersResponse } from '@/services/userApi';
import { knownFailure } from '@/testUtils/knownFailure';
import type { User } from '@/types/user';

// ─── Mocks ────────────────────────────────────────────────────────────────────

// useOwner is NOT mocked here, unlike every page-level Layouts test. The owner
// lookup it performs is exactly what the knownFailure at the bottom exercises.
vi.mock('@/services/userApi', () => ({
  fetchUsers: vi.fn(),
}));

// ─── Fixtures ─────────────────────────────────────────────────────────────────

const LAYOUT_ID = mockLayout.layoutId;

const buildUser = (userId: number): User => ({
  ...mockUser,
  userId,
  userName: `user${userId}`,
  email: `user${userId}@example.com`,
});

// What GET /user returns for the first page when the CMS has 25 users:
// ten rows (users 1–10) and a total of 25.
const FIRST_PAGE_OF_25_USERS = {
  rows: Array.from({ length: 10 }, (_, i) => buildUser(i + 1)),
  totalCount: 25,
} as unknown as FetchUsersResponse;

// ─── Helpers ──────────────────────────────────────────────────────────────────

const renderPreviewer = (props: Partial<ComponentProps<typeof LayoutPreviewer>> = {}) => {
  const onClose = vi.fn();
  const utils = render(
    <LayoutPreviewer
      layoutId={LAYOUT_ID}
      name={mockLayout.layout}
      layoutData={mockLayout}
      folderName="Marketing"
      onClose={onClose}
      {...props}
    />,
  );
  return { onClose, ...utils };
};

const previewFrame = () => screen.getByTitle(`Layout ${LAYOUT_ID}`) as HTMLIFrameElement;

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('LayoutPreviewer', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(fetchUsers).mockResolvedValue(FIRST_PAGE_OF_25_USERS);
  });

  describe('opening', () => {
    test('nothing is shown when no layout has been chosen', () => {
      const { container } = renderPreviewer({ layoutId: null });

      expect(container).toBeEmptyDOMElement();
    });

    test('the header shows the layout name', async () => {
      renderPreviewer({ name: 'Reception Screen' });

      expect(await screen.findByRole('heading', { name: 'Reception Screen' })).toBeInTheDocument();
    });

    test('a layout with no name is titled by its id instead', async () => {
      renderPreviewer({ name: '' });

      expect(
        await screen.findByRole('heading', { name: `Layout ${LAYOUT_ID}` }),
      ).toBeInTheDocument();
    });
  });

  // The preview URL decides what the user sees. The API hands back a signed
  // previewUrl (with a JWT); the page can override it (e.g. to preview a draft);
  // the bare path is only a last resort.
  describe('preview source', () => {
    test('an override URL from the page wins over everything else', async () => {
      renderPreviewer({
        previewUrlOverride: '/preview/override?token=abc',
        layoutData: { ...mockLayout, previewUrl: '/preview/from-api?token=xyz' },
      });

      await waitFor(() => expect(fetchUsers).toHaveBeenCalled());
      expect(previewFrame()).toHaveAttribute('src', '/preview/override?token=abc');
    });

    test('without an override, the signed URL returned by the API is used', async () => {
      renderPreviewer({ layoutData: { ...mockLayout, previewUrl: '/preview/from-api?token=xyz' } });

      await waitFor(() => expect(fetchUsers).toHaveBeenCalled());
      expect(previewFrame()).toHaveAttribute('src', '/preview/from-api?token=xyz');
    });

    test('with neither, the layout preview path for this layout id is used', async () => {
      renderPreviewer({ layoutData: { ...mockLayout, previewUrl: undefined } });

      await waitFor(() => expect(fetchUsers).toHaveBeenCalled());
      expect(previewFrame().getAttribute('src')).toMatch(
        new RegExp(`/layout/preview/${LAYOUT_ID}$`),
      );
    });

    // Security model (CLAUDE.md, "Widget HTML — sandbox model"): layout content can
    // contain arbitrary widget HTML/JS, so the preview must run in a sandbox with
    // no same-origin access to the CMS. This attribute IS the defence.
    test('the preview runs in a sandbox that only allows scripts, never same-origin access', async () => {
      renderPreviewer();

      await waitFor(() => expect(fetchUsers).toHaveBeenCalled());
      expect(previewFrame()).toHaveAttribute('sandbox', 'allow-scripts');
      expect(previewFrame().getAttribute('sandbox')).not.toContain('allow-same-origin');
    });
  });

  describe('header actions', () => {
    test('pressing Escape closes the preview', async () => {
      const user = userEvent.setup();
      const { onClose } = renderPreviewer();

      await user.keyboard('{Escape}');

      expect(onClose).toHaveBeenCalledOnce();
    });

    test('Move is offered only when the page allows moving, and clicking it starts a move', async () => {
      const user = userEvent.setup();
      const onMove = vi.fn();
      const { unmount } = renderPreviewer();
      await waitFor(() => expect(fetchUsers).toHaveBeenCalled());
      expect(screen.queryByRole('button', { name: 'Move' })).not.toBeInTheDocument();
      unmount();

      renderPreviewer({ onMove });
      await user.click(await screen.findByRole('button', { name: 'Move' }));

      expect(onMove).toHaveBeenCalledOnce();
    });

    test('Share is offered only when the page allows sharing, and passes the layout id', async () => {
      const user = userEvent.setup();
      const onShare = vi.fn();
      const { unmount } = renderPreviewer();
      await waitFor(() => expect(fetchUsers).toHaveBeenCalled());
      expect(screen.queryByRole('button', { name: 'Share' })).not.toBeInTheDocument();
      unmount();

      renderPreviewer({ onShare });
      await user.click(await screen.findByRole('button', { name: 'Share' }));

      expect(onShare).toHaveBeenCalledWith(LAYOUT_ID);
    });
  });

  describe('details panel', () => {
    test('the details panel shows the folder name and the owner of the layout', async () => {
      renderPreviewer({ layoutData: { ...mockLayout, ownerId: 3 } });

      expect(await screen.findByText('user3')).toBeInTheDocument();
      expect(screen.getByText('user3@example.com')).toBeInTheDocument();
      expect(screen.getByText('Marketing')).toBeInTheDocument();
    });

    test('a layout with no owner does not look anyone up', async () => {
      renderPreviewer({ layoutData: { ...mockLayout, ownerId: 0 } });

      await screen.findByRole('heading', { name: mockLayout.layout });
      expect(fetchUsers).not.toHaveBeenCalled();
    });

    // Open — xibosignage/xibo#4060.
    knownFailure(
      'the details panel finds the owner even when they are not among the first ten users',
      async () => {
        renderPreviewer({ layoutData: { ...mockLayout, ownerId: 21 } });
        await waitFor(() => expect(fetchUsers).toHaveBeenCalled());
        // Let the lookup settle so the panel is showing its final answer.
        await waitFor(() => expect(screen.getAllByText('-').length).toBeGreaterThan(0));
      },
      async () => expect(await screen.findByText('user21')).toBeInTheDocument(),
    );

    // Open — xibosignage/xibo#4060.
    knownFailure(
      'a normal user sees the owner of a layout someone else shared with them',
      async () => {
        const viewer = buildUser(5);
        vi.mocked(fetchUsers).mockResolvedValue({
          rows: [viewer],
          totalCount: 1,
        } as unknown as FetchUsersResponse);
        renderPreviewer({ layoutData: { ...mockLayout, ownerId: 2, owner: 'user2' } });
        await waitFor(() => expect(fetchUsers).toHaveBeenCalled());
        await waitFor(() => expect(screen.getAllByText('-').length).toBeGreaterThan(0));
      },
      async () => expect(await screen.findByText('user2')).toBeInTheDocument(),
    );

    // Open — xibosignage/xibo#4067.
    knownFailure(
      'the details panel stays out of the accessibility tree until Details is clicked',
      async () => {
        renderPreviewer();
        await screen.findByRole('heading', { name: mockLayout.layout });
      },
      () => expect(screen.queryByRole('complementary')).not.toBeInTheDocument(),
    );
  });
});
