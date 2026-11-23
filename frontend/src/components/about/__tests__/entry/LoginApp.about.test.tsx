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
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { LoginApp } from '@/login/LoginApp';
import type { LoginConfig } from '@/login/types';
import { testQueryClient } from '@/setupTests';

// LoginApp captures this object at import, so tests change its flags instead of replacing it.
const loginConfig = vi.hoisted(() => {
  const config: LoginConfig = {
    priorRoute: '',
    logoUrl: '',
    logoDarkUrl: '',
    passwordReminderEnabled: false,
    authCASEnabled: false,
    version: '4.4.0',
    appName: 'Xibo',
    supportUrl: '',
    sourceUrl: 'https://github.com/xibosignage/xibo-cms',
    removeLicenceFromLogin: false,
    i18n: { aboutLabel: 'About', closeLabel: 'Close', sourceLabel: 'Source' },
  };
  window.__LOGIN_CONFIG__ = config;
  return config;
});

vi.mock('@/login/components/LoginAboutModal', () => ({
  LoginAboutModal: ({ onClose }: { onClose: () => void }) => (
    <div role="dialog" aria-label="About">
      <button onClick={onClose}>Close</button>
    </div>
  ),
}));
vi.mock('@/login/components/LoginForm', () => ({ LoginForm: () => null }));

describe('Login page - About', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    loginConfig.removeLicenceFromLogin = false;
  });

  test('the About link in the footer opens the About window', async () => {
    const user = userEvent.setup();
    render(<LoginApp />);
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'About' }));

    expect(screen.getByRole('dialog', { name: 'About' })).toBeInTheDocument();
  });

  test('Close shuts the login About window', async () => {
    const user = userEvent.setup();
    render(<LoginApp />);
    await user.click(screen.getByRole('button', { name: 'About' }));

    await user.click(screen.getByRole('button', { name: 'Close' }));

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
  });

  test('the About link is hidden when the licence is removed from the login page', () => {
    loginConfig.removeLicenceFromLogin = true;
    render(<LoginApp />);

    expect(screen.queryByRole('button', { name: 'About' })).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Source' })).not.toBeInTheDocument();
    expect(screen.getByText('4.4.0')).toBeInTheDocument();
  });
});
