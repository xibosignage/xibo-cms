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
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockFont } from '../fixtures/font';

import DeleteFontModal from '@/pages/Administration/Fonts/components/DeleteFontModal';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Tests
// =============================================================================

describe('DeleteFontModal', () => {
  const onClose = vi.fn();
  const onDelete = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();
  });

  test('shows the singular heading and the font name for a single item', () => {
    render(
      <DeleteFontModal
        onClose={onClose}
        onDelete={onDelete}
        itemCount={1}
        fontName={mockFont.name}
      />,
    );

    expect(screen.getByRole('heading', { name: /delete font\?/i })).toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: /delete fonts\?/i })).not.toBeInTheDocument();
    expect(screen.getByText(mockFont.name, { selector: 'strong' })).toBeInTheDocument();
  });

  test('shows the plural heading and the count for multiple items', () => {
    render(<DeleteFontModal onClose={onClose} onDelete={onDelete} itemCount={3} />);

    expect(screen.getByRole('heading', { name: /delete fonts\?/i })).toBeInTheDocument();
    expect(screen.getByText('3', { selector: 'strong' })).toBeInTheDocument();
  });

  test('Yes, Delete calls onDelete', async () => {
    const user = userEvent.setup();
    render(
      <DeleteFontModal
        onClose={onClose}
        onDelete={onDelete}
        itemCount={1}
        fontName={mockFont.name}
      />,
    );

    await user.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    expect(onDelete).toHaveBeenCalledTimes(1);
    expect(onClose).not.toHaveBeenCalled();
  });

  test('Cancel calls onClose', async () => {
    const user = userEvent.setup();
    render(
      <DeleteFontModal
        onClose={onClose}
        onDelete={onDelete}
        itemCount={1}
        fontName={mockFont.name}
      />,
    );

    await user.click(screen.getByRole('button', { name: 'Cancel' }));

    expect(onClose).toHaveBeenCalledTimes(1);
    expect(onDelete).not.toHaveBeenCalled();
  });

  test('shows a disabled "Deleting…" button while loading', () => {
    render(
      <DeleteFontModal
        onClose={onClose}
        onDelete={onDelete}
        itemCount={1}
        fontName={mockFont.name}
        isLoading
      />,
    );

    expect(screen.getByRole('button', { name: 'Deleting…' })).toBeDisabled();
    expect(screen.queryByRole('button', { name: 'Yes, Delete' })).not.toBeInTheDocument();
  });

  test('shows the error message when one is supplied', () => {
    render(
      <DeleteFontModal
        onClose={onClose}
        onDelete={onDelete}
        itemCount={1}
        fontName={mockFont.name}
        error="Font is in use"
      />,
    );

    expect(screen.getByText('Font is in use')).toBeInTheDocument();
  });
});
