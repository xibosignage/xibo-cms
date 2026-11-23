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
import { vi } from 'vitest';

import { AboutModalContent } from '../../AboutModalContent';
import { buildAboutConfig, type AboutConfig } from '../fixtures/about';
import { stubAboutConfig } from '../mocks/aboutApi';

import AboutModal from '@/components/layout/UserMenu/AboutModal';

// Every loaded branch renders at least one heading.
export const waitForAboutLoaded = () => screen.findAllByRole('heading');

export async function renderAboutContent(overrides: Partial<AboutConfig> = {}) {
  stubAboutConfig(buildAboutConfig(overrides));
  const result = render(<AboutModalContent />);
  await waitForAboutLoaded();
  return result;
}

// Opens the CMS About window and waits for its content to load.
export async function renderAboutModal(onClose = vi.fn()) {
  stubAboutConfig(buildAboutConfig());
  render(<AboutModal onClose={onClose} />);
  const dialog = await screen.findByRole('dialog', { name: 'About' });
  await waitForAboutLoaded();
  return { onClose, dialog };
}
