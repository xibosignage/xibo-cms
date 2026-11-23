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

import { screen } from '@testing-library/react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { WHITE_LABEL_ABOUT_CONFIG } from './fixtures/about';
import { renderAboutContent } from './helpers/renderAbout';

import { testQueryClient } from '@/setupTests';

// Any appName other than exactly 'Xibo' is white-label.

describe('About content - white-label branding', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  test('the heading shows the custom app name with no Xibo tagline', async () => {
    await renderAboutContent(WHITE_LABEL_ABOUT_CONFIG);

    expect(screen.getByRole('heading', { name: 'Acme Signage' })).toBeInTheDocument();
    expect(screen.queryByText(/Simple\. Intuitive\. Impactful\. Secure\./)).not.toBeInTheDocument();
  });

  test('the Xibo Digital Signage line is hidden', async () => {
    await renderAboutContent(WHITE_LABEL_ABOUT_CONFIG);

    expect(screen.queryByText(/Xibo Digital Signage/)).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'xibosignage.com' })).not.toBeInTheDocument();
  });

  test('the Xibo Signage Ltd copyright line is still shown', async () => {
    await renderAboutContent(WHITE_LABEL_ABOUT_CONFIG);

    expect(screen.getByText(/Copyright © 2006-\d{4} Xibo Signage Ltd/)).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Xibo Developers' })).toBeInTheDocument();
  });

  test('the Feedback and Commercial sections are hidden', async () => {
    await renderAboutContent(WHITE_LABEL_ABOUT_CONFIG);

    expect(screen.queryByRole('heading', { name: 'Feedback' })).not.toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: 'Commercial' })).not.toBeInTheDocument();
  });

  test('the Licence, Source Code, Third Party Licenses and Weather sections are still shown', async () => {
    await renderAboutContent(WHITE_LABEL_ABOUT_CONFIG);

    for (const name of ['Licence', 'Source Code', 'Third Party Licenses', 'Weather']) {
      expect(screen.getByRole('heading', { name })).toBeInTheDocument();
    }
  });

  test('the GitHub link points to the custom source-code URL', async () => {
    await renderAboutContent(WHITE_LABEL_ABOUT_CONFIG);

    expect(screen.getByRole('link', { name: 'GitHub' })).toHaveAttribute(
      'href',
      'https://git.example.com/acme/cms',
    );
  });

  test('an app name of "xibo" in lower case counts as white-label', async () => {
    await renderAboutContent({ appName: 'xibo' });

    expect(screen.getByRole('heading', { name: 'xibo' })).toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: 'Feedback' })).not.toBeInTheDocument();
  });
});
