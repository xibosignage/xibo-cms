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

import { screen, within } from '@testing-library/react';
import { vi, afterEach, beforeEach, describe, test, expect } from 'vitest';

import { renderAboutContent } from './helpers/renderAbout';

import { testQueryClient } from '@/setupTests';
import { knownFailure } from '@/testUtils/knownFailure';

describe('About content - default Xibo branding', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  test('requests the About details once from the install root', async () => {
    await renderAboutContent();

    expect(fetch).toHaveBeenCalledTimes(1);
    expect(fetch).toHaveBeenCalledWith('/about/config');
  });

  test('shows the Xibo heading with the tagline', async () => {
    await renderAboutContent();

    // Accessible-name computation trims the tagline's leading space.
    const heading = screen.getByRole('heading', {
      name: /^Xibo\s*- Simple\. Intuitive\. Impactful\. Secure\.$/,
    });
    expect(heading).toHaveTextContent('Xibo - Simple. Intuitive. Impactful. Secure.');
  });

  test('shows the Xibo Digital Signage line with a link to xibosignage.com', async () => {
    await renderAboutContent();

    const link = screen.getByRole('link', { name: 'xibosignage.com' });
    expect(link).toHaveAttribute('href', 'https://xibosignage.com');
    expect(link).toHaveAttribute('target', '_blank');
    expect(screen.getByText(/Xibo Digital Signage -/)).toBeInTheDocument();
  });

  test('the copyright line runs from 2006 to the current year', async () => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date('2031-03-15T12:00:00Z'));

    await renderAboutContent();

    expect(screen.getByText(/Copyright © 2006-2031 Xibo Signage Ltd and the/)).toBeInTheDocument();
  });

  test('the copyright line links to the Xibo Developers page', async () => {
    await renderAboutContent();

    expect(screen.getByRole('link', { name: 'Xibo Developers' })).toHaveAttribute(
      'href',
      'https://github.com/orgs/xibosignage/people',
    );
  });

  test('shows the CMS version and SHA', async () => {
    await renderAboutContent({ version: '4.4.0', revision: 'abc1234' });

    expect(screen.getByText(/Version 4\.4\.0/)).toBeInTheDocument();
    expect(screen.getByText('SHA: abc1234')).toBeInTheDocument();
  });

  test('hides the version and SHA when the CMS reports no version', async () => {
    await renderAboutContent({ version: '', revision: '' });

    expect(screen.queryByText(/Version/)).not.toBeInTheDocument();
    expect(screen.queryByText(/SHA:/)).not.toBeInTheDocument();
  });

  test('shows the Licence, Source Code, Third Party Licenses, Fonts and Weather sections', async () => {
    await renderAboutContent();

    for (const name of ['Licence', 'Source Code', 'Third Party Licenses', 'Fonts', 'Weather']) {
      expect(screen.getByRole('heading', { name })).toBeInTheDocument();
    }
  });

  test('the GitHub link points to the source-code URL the CMS reports', async () => {
    await renderAboutContent({ sourceUrl: 'https://example.com/fork' });

    const link = screen.getByRole('link', { name: 'GitHub' });
    expect(link).toHaveAttribute('href', 'https://example.com/fork');
    expect(link).toHaveAttribute('target', '_blank');
  });

  test('shows the Feedback and Commercial sections with their links', async () => {
    await renderAboutContent();

    expect(screen.getByRole('heading', { name: 'Feedback' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Commercial' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'privacy policy' })).toHaveAttribute(
      'href',
      'https://xibosignage.com/privacy',
    );
    expect(screen.getByRole('link', { name: 'data processing agreement' })).toHaveAttribute(
      'href',
      'https://xibosignage.com/dpa',
    );
    expect(screen.getByRole('link', { name: 'Xibo Signage Ltd' })).toHaveAttribute(
      'href',
      'https://xibosignage.com',
    );
  });

  test('the Fonts section lists at least one bundled font, each with a link', async () => {
    await renderAboutContent();

    const fonts = screen.getAllByRole('listitem');
    expect(fonts.length).toBeGreaterThan(0);
    for (const font of fonts) {
      expect(within(font).getByRole('link')).toHaveAttribute(
        'href',
        expect.stringMatching(/^https:\/\//),
      );
    }
  });

  test('links that open in a new tab do not pass the referrer', async () => {
    await renderAboutContent();

    const newTabLinks = screen
      .getAllByRole('link')
      .filter((link) => link.getAttribute('target') === '_blank');
    expect(newTabLinks.length).toBeGreaterThan(0);
    for (const link of newTabLinks) {
      expect(link).toHaveAttribute('rel', expect.stringContaining('noreferrer'));
    }
  });

  // https://github.com/xibosignage/xibo/issues/4074
  knownFailure(
    'no empty SHA badge is shown when the CMS reports a version but no SHA',
    async () => {
      await renderAboutContent({ version: '4.4.0', revision: '' });
      expect(screen.getByText(/Version 4\.4\.0/)).toBeInTheDocument();
    },
    () => {
      expect(screen.queryByText(/SHA:/)).not.toBeInTheDocument();
    },
  );

  // https://github.com/xibosignage/xibo/issues/4075
  knownFailure(
    'every external link opens in a new tab',
    async () => {
      await renderAboutContent();
      expect(screen.getByRole('link', { name: 'GitHub' })).toHaveAttribute('target', '_blank');
      return screen.getAllByRole('link');
    },
    (links) => {
      expect(links.filter((link) => link.getAttribute('target') !== '_blank')).toEqual([]);
    },
  );
});
