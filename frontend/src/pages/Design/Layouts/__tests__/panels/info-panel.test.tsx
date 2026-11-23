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

import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ComponentProps } from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { LayoutInfoPanel } from '../../components/LayoutInfoPannel';
import { mockDraftLayout, mockLayout, mockUser } from '../layoutTestUtils';

import { knownFailure } from '@/testUtils/knownFailure';
import type { Layout } from '@/types/layout';

// LayoutInfoPanel is purely presentational: everything it shows arrives as props.
// It is shared by the full-screen LayoutPreviewer (dark) and the Layouts grid's
// side panel (light, applyVersionTwo). The two themes differ only in CSS classes,
// which these tests deliberately do not assert on.

// ─── Helpers ──────────────────────────────────────────────────────────────────

const renderPanel = (props: Partial<ComponentProps<typeof LayoutInfoPanel>> = {}) => {
  const onClose = vi.fn();
  render(
    <LayoutInfoPanel
      onClose={onClose}
      layoutData={mockLayout}
      owner={mockUser}
      folderName="Marketing"
      loading={false}
      {...props}
    />,
  );
  return { onClose };
};

const withLayout = (overrides: Partial<Layout>): Layout => ({ ...mockLayout, ...overrides });

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('LayoutInfoPanel', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('owner', () => {
    test('shows the owner name and email once they have loaded', () => {
      renderPanel({ owner: { ...mockUser, userName: 'alice', email: 'alice@example.com' } });

      expect(screen.getByText('alice')).toBeInTheDocument();
      expect(screen.getByText('alice@example.com')).toBeInTheDocument();
    });

    test('hides the owner name while the owner is still loading', () => {
      renderPanel({ owner: { ...mockUser, userName: 'alice' }, loading: true });

      expect(screen.queryByText('alice')).not.toBeInTheDocument();
    });

    test('shows a dash when the owner could not be found', () => {
      renderPanel({ owner: null });

      // Initial avatar letter, name and email all fall back to a dash.
      expect(screen.getAllByText('-').length).toBeGreaterThanOrEqual(3);
    });
  });

  describe('details', () => {
    test('shows the folder the layout lives in', () => {
      renderPanel({ folderName: 'Marketing' });

      expect(screen.getByText('Marketing')).toBeInTheDocument();
    });

    test('a published and a draft layout show different statuses', () => {
      const { unmount } = render(
        <LayoutInfoPanel
          onClose={vi.fn()}
          layoutData={mockLayout}
          owner={null}
          folderName=""
          loading={false}
        />,
      );
      expect(screen.getByText('Published')).toBeInTheDocument();
      unmount();

      renderPanel({ layoutData: mockDraftLayout });
      expect(screen.getByText('Draft')).toBeInTheDocument();
      expect(screen.queryByText('Published')).not.toBeInTheDocument();
    });

    test('lists every tag on the layout', () => {
      renderPanel({
        layoutData: withLayout({
          tags: [
            { tag: 'lobby', tagId: 1, value: '' },
            { tag: 'promo', tagId: 2, value: '' },
          ],
        }),
      });

      expect(screen.getByText('lobby')).toBeInTheDocument();
      expect(screen.getByText('promo')).toBeInTheDocument();
      expect(screen.queryByText('NO TAGS')).not.toBeInTheDocument();
    });

    test('says there are no tags when the layout has none', () => {
      renderPanel({ layoutData: withLayout({ tags: [] }) });

      expect(screen.getByText('NO TAGS')).toBeInTheDocument();
    });

    // The duration arrives in seconds; anything over an hour must carry into hours
    // rather than showing e.g. 62 minutes.
    test('shows the duration as hours, minutes and seconds', () => {
      renderPanel({ layoutData: withLayout({ duration: 3725 }) });

      expect(screen.getByText('01:02:05')).toBeInTheDocument();
    });

    test('a zero-length layout shows a duration of 00:00:00', () => {
      renderPanel({ layoutData: withLayout({ duration: 0 }) });

      expect(screen.getByText('00:00:00')).toBeInTheDocument();
    });

    test('shows the resolution as width x height', () => {
      renderPanel({ layoutData: withLayout({ width: 1080, height: 1920 }) });

      expect(screen.getByText('1080 x 1920')).toBeInTheDocument();
    });

    test('a missing width or height shows as a dash rather than 0', () => {
      renderPanel({ layoutData: withLayout({ width: 0, height: 0 }) });

      expect(screen.getByText('- x -')).toBeInTheDocument();
    });

    test('shows the orientation, name and last modified date', () => {
      renderPanel({
        layoutData: withLayout({
          orientation: 'portrait',
          layout: 'Reception Screen',
          modifiedDt: '2026-03-01 10:00:00',
        }),
      });

      expect(screen.getByText('portrait')).toBeInTheDocument();
      expect(screen.getByText('Reception Screen')).toBeInTheDocument();
      expect(screen.getByText('2026-03-01 10:00:00')).toBeInTheDocument();
    });

    test('shows the description when there is one, and says so when there is not', () => {
      const { unmount } = render(
        <LayoutInfoPanel
          onClose={vi.fn()}
          layoutData={withLayout({ description: 'Shown in the lobby' })}
          owner={null}
          folderName=""
          loading={false}
        />,
      );
      expect(screen.getByText('Shown in the lobby')).toBeInTheDocument();
      unmount();

      renderPanel({ layoutData: withLayout({ description: '' }) });
      expect(screen.getByText('No description')).toBeInTheDocument();
    });

    test('with no layout selected none of the layout details are shown', () => {
      renderPanel({ layoutData: null });

      expect(screen.getByText('Layout Details')).toBeInTheDocument();
      expect(screen.queryByText('OWNER')).not.toBeInTheDocument();
      expect(screen.queryByText(mockLayout.layout)).not.toBeInTheDocument();
    });
  });

  describe('closing', () => {
    test('the panel header button closes the panel', async () => {
      const user = userEvent.setup();
      const { onClose } = renderPanel();

      // The close button is the panel's only button. It has no accessible name
      // (see the knownFailure below), so it can only be selected by exclusion.
      const buttons = screen.getAllByRole('button');
      expect(buttons).toHaveLength(1);
      await user.click(buttons[0]!);

      expect(onClose).toHaveBeenCalledOnce();
    });

    // Open — xibosignage/xibo#4067.
    knownFailure(
      'the panel close button is announced as "Close" to screen readers',
      () => {
        renderPanel();
        return screen.getAllByRole('button');
      },
      () => expect(screen.getByRole('button', { name: 'Close' })).toBeInTheDocument(),
    );
  });
});
