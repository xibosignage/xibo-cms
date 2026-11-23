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

import DeleteTagModal from '@/pages/Administration/Tags/components/DeleteTagModal';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Tests
// =============================================================================

describe('DeleteTagModal', () => {
  const onClose = vi.fn();
  const onDelete = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();
  });

  test('shows singular heading for a single item', () => {
    render(
      <DeleteTagModal
        isOpen
        onClose={onClose}
        onDelete={onDelete}
        itemCount={1}
        tagName="location"
      />,
    );
    expect(screen.getByRole('heading', { name: /delete tag\?/i })).toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: /delete tags\?/i })).not.toBeInTheDocument();
  });

  test('shows the tag name in the confirmation for a single item', () => {
    render(
      <DeleteTagModal
        isOpen
        onClose={onClose}
        onDelete={onDelete}
        itemCount={1}
        tagName="location"
      />,
    );
    expect(screen.getByText('location', { selector: 'strong' })).toBeInTheDocument();
  });

  test('shows plural heading for multiple items', () => {
    render(<DeleteTagModal isOpen onClose={onClose} onDelete={onDelete} itemCount={3} />);
    expect(screen.getByRole('heading', { name: /delete tags\?/i })).toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: /delete tag\?/i })).not.toBeInTheDocument();
  });

  test('shows the item count in the plural confirmation', () => {
    render(<DeleteTagModal isOpen onClose={onClose} onDelete={onDelete} itemCount={3} />);
    expect(screen.getByText(/are you sure you want to delete 3 tags/i)).toBeInTheDocument();
  });

  test('clicking Cancel calls onClose', async () => {
    const user = userEvent.setup();
    render(
      <DeleteTagModal
        isOpen
        onClose={onClose}
        onDelete={onDelete}
        itemCount={1}
        tagName="location"
      />,
    );

    await user.click(screen.getByRole('button', { name: /^cancel$/i }));

    expect(onClose).toHaveBeenCalledOnce();
    expect(onDelete).not.toHaveBeenCalled();
  });

  test('clicking Yes, Delete calls onDelete', async () => {
    const user = userEvent.setup();
    render(
      <DeleteTagModal
        isOpen
        onClose={onClose}
        onDelete={onDelete}
        itemCount={1}
        tagName="location"
      />,
    );

    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    expect(onDelete).toHaveBeenCalledOnce();
  });

  test('shows "Deleting…" button when isLoading is true', () => {
    render(
      <DeleteTagModal
        isOpen
        onClose={onClose}
        onDelete={onDelete}
        itemCount={1}
        tagName="location"
        isLoading
      />,
    );
    expect(screen.getByRole('button', { name: /deleting/i })).toBeDisabled();
  });

  test('shows the error in an alert', () => {
    render(
      <DeleteTagModal
        isOpen
        onClose={onClose}
        onDelete={onDelete}
        itemCount={1}
        tagName="location"
        error="Cannot delete — tag is in use."
      />,
    );
    expect(screen.getByRole('alert')).toHaveTextContent('Cannot delete — tag is in use.');
  });

  test('renders nothing when isOpen is false', () => {
    const { container } = render(
      <DeleteTagModal isOpen={false} onClose={onClose} onDelete={onDelete} itemCount={1} />,
    );
    expect(container).toBeEmptyDOMElement();
  });
});
