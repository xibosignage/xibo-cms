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
import { describe, expect, test } from 'vitest';

import { LoginForm } from '@/login/components/LoginForm';
import { SignInProviders } from '@/login/components/SignInProviders';
import type { SignInProvider } from '@/login/types';

const saml: SignInProvider = {
  id: 'saml',
  type: 'saml',
  name: 'Okta',
  isPrimary: false,
  label: 'Sign in with Okta',
  url: '/saml/login?priorRoute=%2Fdisplays',
};

const cas: SignInProvider = {
  id: 'cas',
  type: 'cas',
  name: 'University Login',
  isPrimary: false,
  label: 'Sign in with University Login',
  url: '/cas/login',
};

const noop = () => {};

describe('SignInProviders', () => {
  test('renders nothing without providers', () => {
    const { container } = render(<SignInProviders />);
    expect(container).toBeEmptyDOMElement();
  });

  test('renders a link per provider, in order', () => {
    render(<SignInProviders providers={[saml, cas]} />);

    const links = screen.getAllByRole('link');
    expect(links.map((l) => l.textContent)).toEqual([
      'Sign in with Okta',
      'Sign in with University Login',
    ]);
    expect(links[0]).toHaveAttribute('href', '/saml/login?priorRoute=%2Fdisplays');
  });
});

describe('LoginForm with SSO providers', () => {
  test('shows the local form and the provider buttons together', () => {
    render(
      <LoginForm
        onSuccess={noop}
        onTfa={noop}
        onForgot={noop}
        passwordReminderEnabled={false}
        authCASEnabled={false}
        signInProviders={[saml]}
      />,
    );

    expect(screen.getByLabelText('identifier')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Sign in with Okta' })).toBeInTheDocument();
  });

  test('a CAS-only page shows just the CAS button', () => {
    render(
      <LoginForm
        onSuccess={noop}
        onTfa={noop}
        onForgot={noop}
        passwordReminderEnabled={false}
        authCASEnabled
        signInProviders={[saml]}
      />,
    );

    expect(screen.queryByLabelText('identifier')).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Sign in with Okta' })).not.toBeInTheDocument();
  });
});
