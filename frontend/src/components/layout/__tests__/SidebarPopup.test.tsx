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

import { fireEvent, screen } from '@testing-library/react';
import { beforeEach, describe, expect, test } from 'vitest';

import { renderSidebar } from './sidebarTestUtils';

import { testQueryClient } from '@/setupTests';

/** Open the popup a collapsed sidebar shows for the item whose link matches `selector`. */
const openPopupFor = (container: HTMLElement, selector: string) => {
  const item = container.querySelector(selector);
  expect(item).not.toBeNull();
  // The popup opens on hover or focus; focus is the one jsdom can drive.
  fireEvent.focus(item!);
};

// Regression cover for xibosignage/xibo#3956. When the sidebar is collapsed,
// each item opens a popup with its name as a link. That link was relative
// (href="dashboard"), so from a nested page such as /displays/settings it led
// to /displays/dashboard, which doesn't exist. From "/" the two resolve the
// same, so the test starts on a nested page.
describe('Collapsed sidebar popup links', () => {
  beforeEach(() => {
    testQueryClient.clear();
  });

  test("a popup's own link is absolute, so it works from a nested page", async () => {
    const { container } = renderSidebar({
      isCollapsed: true,
      initialRoute: '/displays/settings',
    });

    openPopupFor(container, 'a[href="/dashboard"]');

    expect(await screen.findByRole('link', { name: 'Dashboard' })).toHaveAttribute(
      'href',
      '/dashboard',
    );
  });

  // A section with sub-pages has no page of its own, so its name in the popup
  // is a heading. It used to be a link to href="", which reloads the page.
  test("a section's popup has no link that goes nowhere", async () => {
    const { container } = renderSidebar({
      isCollapsed: true,
      initialRoute: '/displays/settings',
    });

    // A section's icon links to its first sub-page.
    openPopupFor(container, 'a[href^="/displays/"]');
    await screen.findByRole('link', { name: 'Display Settings' });

    expect(document.querySelectorAll('a[href=""]')).toHaveLength(0);
  });
});
