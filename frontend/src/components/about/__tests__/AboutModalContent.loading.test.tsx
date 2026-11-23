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
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { AboutModalContent } from '../AboutModalContent';

import { buildAboutConfig, EMPTY_ABOUT_CONFIG } from './fixtures/about';
import { waitForAboutLoaded } from './helpers/renderAbout';
import {
  holdAboutConfig,
  stubAboutConfig,
  stubAboutConfigError,
  stubAboutConfigInvalidJson,
  stubAboutConfigNetworkError,
} from './mocks/aboutApi';

import { testQueryClient } from '@/setupTests';
import { knownFailure } from '@/testUtils/knownFailure';

// Every failure path falls back to the default content with no version.

const expectDefaultContentWithoutVersion = () => {
  expect(screen.getByRole('heading', { name: /^Xibo\s*- Simple/ })).toBeInTheDocument();
  expect(screen.getByRole('heading', { name: 'Licence' })).toBeInTheDocument();
  expect(screen.queryByText(/Version/)).not.toBeInTheDocument();
};

describe('About content - loading and failure', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  test('no About content is shown while the details are loading', async () => {
    const request = holdAboutConfig();
    render(<AboutModalContent />);

    expect(screen.queryAllByRole('heading')).toHaveLength(0);
    expect(screen.queryByRole('link')).not.toBeInTheDocument();

    request.release(buildAboutConfig());
    await waitForAboutLoaded();
  });

  test('the content appears once the details arrive', async () => {
    const request = holdAboutConfig();
    render(<AboutModalContent />);
    expect(screen.queryAllByRole('heading')).toHaveLength(0);

    request.release(buildAboutConfig());

    await screen.findByRole('heading', { name: 'Licence' });
    expect(screen.getByText(/Version 4\.4\.0/)).toBeInTheDocument();
  });

  test('a failed request shows the default Xibo content without a version', async () => {
    stubAboutConfigNetworkError();
    render(<AboutModalContent />);
    await waitForAboutLoaded();

    expectDefaultContentWithoutVersion();
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });

  test('a response that is not valid JSON shows the default content', async () => {
    stubAboutConfigInvalidJson();
    render(<AboutModalContent />);
    await waitForAboutLoaded();

    expectDefaultContentWithoutVersion();
  });

  test('an empty response shows the default content and default GitHub link', async () => {
    stubAboutConfig(EMPTY_ABOUT_CONFIG);
    render(<AboutModalContent />);
    await waitForAboutLoaded();

    expectDefaultContentWithoutVersion();
    expect(screen.getByRole('link', { name: 'GitHub' })).toHaveAttribute(
      'href',
      'https://github.com/xibosignage/xibo-cms',
    );
  });

  test('an error status with a JSON body shows the default content and no error message', async () => {
    stubAboutConfigError(500, { error: 'Server error' });
    render(<AboutModalContent />);
    await waitForAboutLoaded();

    expectDefaultContentWithoutVersion();
    expect(screen.queryByText('Server error')).not.toBeInTheDocument();
  });

  // https://github.com/xibosignage/xibo/issues/4073
  knownFailure(
    'the loading spinner is announced to screen readers',
    () => {
      holdAboutConfig();
      render(<AboutModalContent />);
      expect(screen.queryAllByRole('heading')).toHaveLength(0);
    },
    () => {
      expect(screen.getByRole('status', { name: /loading/i })).toBeInTheDocument();
    },
  );
});
