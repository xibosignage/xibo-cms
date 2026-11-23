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

import { QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ComponentProps } from 'react';
import { vi, beforeEach, afterEach, describe, test, expect } from 'vitest';

import DisplayScreenshotPreviewer from '../../components/DisplayScreenshotPreviewer';
import { buildDisplay, mockUser } from '../fixtures/display';

import { UserProvider } from '@/context/UserContext';
import { fetchDisplayScreenshotBlob, fetchDisplayStatusWindow } from '@/services/displaysApi';
import { testQueryClient } from '@/setupTests';
import { knownFailure } from '@/testUtils/knownFailure';

// The full-screen screenshot viewer opened from a display's thumbnail. It hosts
// DisplayInfoPanel (covered in info-panel.test.tsx); here the panel is real so the
// Details toggle can be followed through to the request it causes.

// ─── Mocks ────────────────────────────────────────────────────────────────────

vi.mock('@/services/displaysApi', () => ({
  fetchDisplayScreenshotBlob: vi.fn(),
  fetchDisplayStatusWindow: vi.fn(),
}));

// ─── Fixtures ─────────────────────────────────────────────────────────────────

const DISPLAY = buildDisplay({ displayId: 42, display: 'Reception', loggedIn: 1 });
const SCREENSHOT = new Blob(['png'], { type: 'image/png' });

// ─── Helpers ──────────────────────────────────────────────────────────────────

const createObjectURL = vi.fn();
const revokeObjectURL = vi.fn();

const renderPreviewer = (
  props: Partial<ComponentProps<typeof DisplayScreenshotPreviewer>> = {},
) => {
  const onClose = vi.fn();
  const utils = render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={mockUser}>
        <DisplayScreenshotPreviewer display={DISPLAY} onClose={onClose} {...props} />
      </UserProvider>
    </QueryClientProvider>,
  );
  return { onClose, ...utils };
};

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('DisplayScreenshotPreviewer', () => {
  const originalCreate = window.URL.createObjectURL;
  const originalRevoke = window.URL.revokeObjectURL;

  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    createObjectURL.mockReturnValue('blob:screenshot-42');
    Object.assign(window.URL, { createObjectURL, revokeObjectURL });
    vi.mocked(fetchDisplayScreenshotBlob).mockResolvedValue(SCREENSHOT);
    vi.mocked(fetchDisplayStatusWindow).mockResolvedValue({ storage: '4.2 GB free' });
  });

  afterEach(() => {
    Object.assign(window.URL, { createObjectURL: originalCreate, revokeObjectURL: originalRevoke });
  });

  describe('screenshot', () => {
    test('nothing is shown when no display has been chosen', () => {
      renderPreviewer({ display: null });

      expect(screen.queryByRole('heading')).not.toBeInTheDocument();
      expect(fetchDisplayScreenshotBlob).not.toHaveBeenCalled();
    });

    test("shows the display's latest screenshot under the display name", async () => {
      renderPreviewer();

      expect(screen.getByRole('heading', { name: 'Reception' })).toBeInTheDocument();
      const image = await screen.findByRole('img', { name: 'Reception' });
      expect(image).toHaveAttribute('src', 'blob:screenshot-42');
      expect(vi.mocked(fetchDisplayScreenshotBlob).mock.calls[0]![0]).toBe(42);
      expect(createObjectURL).toHaveBeenCalledWith(SCREENSHOT);
    });

    test('says it is loading until the screenshot arrives', async () => {
      let respond!: (blob: Blob) => void;
      vi.mocked(fetchDisplayScreenshotBlob).mockReturnValue(new Promise((res) => (respond = res)));
      renderPreviewer();

      expect(await screen.findByText('Loading screenshot...')).toBeInTheDocument();
      expect(screen.queryByRole('img', { name: 'Reception' })).not.toBeInTheDocument();

      respond(SCREENSHOT);
      expect(await screen.findByRole('img', { name: 'Reception' })).toBeInTheDocument();
    });

    test('a display with no screenshot says so instead of showing a broken image', async () => {
      vi.mocked(fetchDisplayScreenshotBlob).mockRejectedValue(new Error('404'));
      renderPreviewer();

      expect(await screen.findByText('No screenshot available')).toBeInTheDocument();
      expect(screen.queryByRole('img', { name: 'Reception' })).not.toBeInTheDocument();
    });

    // Each screenshot is held in memory behind a blob: URL until it is revoked.
    test('closing the viewer releases the screenshot from memory', async () => {
      const { unmount } = renderPreviewer();
      await screen.findByRole('img', { name: 'Reception' });
      expect(revokeObjectURL).not.toHaveBeenCalledWith('blob:screenshot-42');

      unmount();

      expect(revokeObjectURL).toHaveBeenCalledWith('blob:screenshot-42');
    });
  });

  describe('header actions', () => {
    test('the Close button closes the viewer', async () => {
      const user = userEvent.setup();
      const { onClose } = renderPreviewer();
      await screen.findByRole('img', { name: 'Reception' });

      // The info panel has its own "Close" button; the viewer's is the first one.
      await user.click(screen.getAllByRole('button', { name: 'Close' })[0]!);

      expect(onClose).toHaveBeenCalledOnce();
    });

    test('pressing Escape closes the viewer', async () => {
      const user = userEvent.setup();
      const { onClose } = renderPreviewer();
      await screen.findByRole('img', { name: 'Reception' });

      await user.keyboard('{Escape}');

      expect(onClose).toHaveBeenCalledOnce();
    });

    test('Request Screenshot is offered only when the page allows it, and asks for this display', async () => {
      const user = userEvent.setup();
      const onRequestScreenshot = vi.fn();
      const { unmount } = renderPreviewer();
      await screen.findByRole('img', { name: 'Reception' });
      expect(screen.queryByRole('button', { name: 'Request Screenshot' })).not.toBeInTheDocument();
      unmount();

      renderPreviewer({ onRequestScreenshot });
      await user.click(await screen.findByRole('button', { name: 'Request Screenshot' }));

      expect(onRequestScreenshot).toHaveBeenCalledWith(DISPLAY);
    });
  });

  describe('details panel', () => {
    // The panel only asks the player for its status once it is opened, so the
    // status request is what proves Details really opened it.
    test('clicking Details opens the panel, which then loads the player status', async () => {
      const user = userEvent.setup();
      renderPreviewer();
      await screen.findByRole('img', { name: 'Reception' });
      expect(fetchDisplayStatusWindow).not.toHaveBeenCalled();

      await user.click(screen.getByRole('button', { name: 'Details' }));

      expect(await screen.findByText('4.2 GB free')).toBeInTheDocument();
      expect(vi.mocked(fetchDisplayStatusWindow).mock.calls[0]![0]).toBe(42);
    });

    test("the panel's own Close button closes the panel but leaves the viewer open", async () => {
      const user = userEvent.setup();
      const { onClose } = renderPreviewer();
      await screen.findByRole('img', { name: 'Reception' });
      await user.click(screen.getByRole('button', { name: 'Details' }));
      await screen.findByText('4.2 GB free');

      await user.click(screen.getAllByRole('button', { name: 'Close' })[1]!);

      expect(onClose).not.toHaveBeenCalled();
      // Re-opening asks for a fresh status, which it would not if the panel had stayed open.
      await user.click(screen.getByRole('button', { name: 'Details' }));
      await waitFor(() => expect(fetchDisplayStatusWindow).toHaveBeenCalledTimes(2));
    });

    // Open — xibosignage/xibo#4067.
    knownFailure(
      'the details panel stays out of the accessibility tree until Details is clicked',
      async () => {
        renderPreviewer();
        await screen.findByRole('img', { name: 'Reception' });
      },
      () => expect(screen.queryByRole('complementary')).not.toBeInTheDocument(),
    );
  });
});
