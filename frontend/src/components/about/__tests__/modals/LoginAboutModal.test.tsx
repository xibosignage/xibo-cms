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

import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { buildAboutConfig } from '../fixtures/about';
import { waitForAboutLoaded } from '../helpers/renderAbout';
import { stubAboutConfig } from '../mocks/aboutApi';

import { LoginAboutModal } from '@/login/components/LoginAboutModal';
import { testQueryClient } from '@/setupTests';

// login/i18n captures this object at import, so tests swap its keys instead of replacing it.
const loginStrings = vi.hoisted(() => {
  const strings: Record<string, string> = {};
  (window as unknown as { __LOGIN_CONFIG__: { i18n: Record<string, string> } }).__LOGIN_CONFIG__ = {
    i18n: strings,
  };
  return strings;
});

const setLoginStrings = (strings: Record<string, string>) => {
  for (const key of Object.keys(loginStrings)) delete loginStrings[key];
  Object.assign(loginStrings, strings);
};

const renderLoginAboutModal = async () => {
  const onClose = vi.fn();
  stubAboutConfig(buildAboutConfig());
  const result = render(<LoginAboutModal onClose={onClose} />);
  await waitForAboutLoaded();
  return { onClose, ...result };
};

describe('About window (login page)', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    setLoginStrings({ aboutLabel: 'About', closeLabel: 'Close' });
  });

  test('is a dialog named by its About heading', async () => {
    await renderLoginAboutModal();

    screen.getByRole('dialog', { name: 'About' });
  });

  test('uses the login page translated labels', async () => {
    setLoginStrings({ aboutLabel: 'À propos', closeLabel: 'Fermer' });
    await renderLoginAboutModal();

    expect(screen.getByRole('dialog', { name: 'À propos' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Fermer' })).toBeInTheDocument();
  });

  test('shows the About content', async () => {
    await renderLoginAboutModal();

    const dialog = screen.getByRole('dialog', { name: 'About' });
    expect(within(dialog).getByRole('heading', { name: 'Licence' })).toBeInTheDocument();
  });

  test('Close shuts the window', async () => {
    const user = userEvent.setup();
    const { onClose } = await renderLoginAboutModal();

    await user.click(screen.getByRole('button', { name: 'Close' }));

    expect(onClose).toHaveBeenCalledTimes(1);
  });

  test('pressing Escape shuts the window', async () => {
    const user = userEvent.setup();
    const { onClose } = await renderLoginAboutModal();

    await user.keyboard('{Escape}');

    expect(onClose).toHaveBeenCalledTimes(1);
  });

  test('pressing any other key leaves the window open', async () => {
    const user = userEvent.setup();
    const { onClose } = await renderLoginAboutModal();

    await user.keyboard('{Enter}a');

    expect(onClose).not.toHaveBeenCalled();
  });

  test('pressing Escape after the window is gone does nothing', async () => {
    const user = userEvent.setup();
    const { onClose, unmount } = await renderLoginAboutModal();
    unmount();

    await user.keyboard('{Escape}');

    expect(onClose).not.toHaveBeenCalled();
  });
});
