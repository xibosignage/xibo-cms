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

import { CUSTOM_TEXT_ABOUT_CONFIG } from './fixtures/about';
import { renderAboutContent } from './helpers/renderAbout';

import { testQueryClient } from '@/setupTests';

// Custom text replaces all default content, including the copyright line.

describe('About content - custom About text', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  test('shows the custom About text', async () => {
    await renderAboutContent(CUSTOM_TEXT_ABOUT_CONFIG);

    expect(screen.getByRole('heading', { name: 'Acme About' })).toBeInTheDocument();
    expect(screen.getByText(/Custom terms\./)).toBeInTheDocument();
  });

  test('none of the default sections are shown alongside it', async () => {
    await renderAboutContent(CUSTOM_TEXT_ABOUT_CONFIG);

    expect(screen.getAllByRole('heading')).toHaveLength(1);
    expect(screen.queryByText(/Version/)).not.toBeInTheDocument();
    expect(screen.queryByText(/Copyright/)).not.toBeInTheDocument();
  });

  test('links in the custom text are clickable links', async () => {
    await renderAboutContent(CUSTOM_TEXT_ABOUT_CONFIG);

    expect(screen.getByRole('link', { name: 'Terms' })).toHaveAttribute(
      'href',
      'https://example.com/terms',
    );
  });

  test('empty custom text falls back to the default content', async () => {
    await renderAboutContent({ aboutText: '' });

    expect(screen.getByRole('heading', { name: 'Licence' })).toBeInTheDocument();
  });
});
