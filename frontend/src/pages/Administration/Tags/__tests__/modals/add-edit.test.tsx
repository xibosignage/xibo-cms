/*
 * Copyright (C) 2026 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { buildTag } from '../fixtures/tag';

import AddEditTagModal from '@/pages/Administration/Tags/components/AddEditTagModal';
import { createTag, updateTag } from '@/services/tagApi';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/tagApi');
vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Helpers
// =============================================================================

const onClose = vi.fn();
const onSuccess = vi.fn();

const renderAdd = () =>
  render(<AddEditTagModal isOpen mode="add" tag={null} onClose={onClose} onSuccess={onSuccess} />);

const renderEdit = (tag = buildTag()) =>
  render(<AddEditTagModal isOpen mode="edit" tag={tag} onClose={onClose} onSuccess={onSuccess} />);

// =============================================================================
// Tests
// =============================================================================

describe('AddEditTagModal — add mode', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  test('renders with title "Add Tag"', () => {
    renderAdd();
    expect(screen.getByRole('dialog', { name: /add tag/i })).toBeInTheDocument();
  });

  test('name input is empty by default', () => {
    renderAdd();
    const nameInput = screen.getByRole('textbox', { name: /^name/i });
    expect(nameInput).toHaveValue('');
  });

  test('Save calls createTag with the entered name', async () => {
    const user = userEvent.setup();
    vi.mocked(createTag).mockResolvedValue(buildTag({ tag: 'new-tag' }));
    renderAdd();

    await user.type(screen.getByRole('textbox', { name: /^name/i }), 'new-tag');
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    await waitFor(() => {
      expect(createTag).toHaveBeenCalledWith(expect.objectContaining({ name: 'new-tag' }));
    });
  });

  test('Save calls onSuccess and onClose on success', async () => {
    const user = userEvent.setup();
    vi.mocked(createTag).mockResolvedValue(buildTag({ tag: 'new-tag' }));
    renderAdd();

    await user.type(screen.getByRole('textbox', { name: /^name/i }), 'new-tag');
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    await waitFor(() => {
      expect(onSuccess).toHaveBeenCalledOnce();
      expect(onClose).toHaveBeenCalledOnce();
    });
  });

  test('shows validation error when name is empty', async () => {
    const user = userEvent.setup();
    renderAdd();

    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(await screen.findByText('Name is required')).toBeInTheDocument();
    expect(createTag).not.toHaveBeenCalled();
  });

  test('shows API error in alert when createTag rejects', async () => {
    const user = userEvent.setup();
    vi.mocked(createTag).mockRejectedValue(new Error('Tag already exists'));
    renderAdd();

    await user.type(screen.getByRole('textbox', { name: /^name/i }), 'dup');
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Tag already exists');
  });

  test('Cancel button calls onClose without saving', async () => {
    const user = userEvent.setup();
    renderAdd();

    await user.click(screen.getByRole('button', { name: /^cancel$/i }));

    expect(onClose).toHaveBeenCalledOnce();
    expect(createTag).not.toHaveBeenCalled();
  });

  test('Save button shows "Saving…" while the request is pending', async () => {
    const user = userEvent.setup();
    let resolve!: (v: ReturnType<typeof buildTag>) => void;
    vi.mocked(createTag).mockReturnValueOnce(
      new Promise<ReturnType<typeof buildTag>>((r) => {
        resolve = r;
      }),
    );
    renderAdd();

    await user.type(screen.getByRole('textbox', { name: /^name/i }), 'pending-tag');
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(await screen.findByRole('button', { name: /saving/i })).toBeDisabled();

    resolve(buildTag({ tag: 'pending-tag' }));
    await waitFor(() => expect(screen.getByRole('button', { name: /^save$/i })).toBeEnabled());
  });
});

describe('AddEditTagModal — edit mode', () => {
  const existingTag = buildTag({
    tagId: 5,
    tag: 'existing-tag',
    options: '["a","b"]',
    isRequired: 1,
  });

  beforeEach(() => {
    vi.clearAllMocks();
  });

  test('renders with title "Edit Tag"', () => {
    renderEdit(existingTag);
    expect(screen.getByRole('dialog', { name: /edit tag/i })).toBeInTheDocument();
  });

  test('pre-fills the name field with the tag name', () => {
    renderEdit(existingTag);
    expect(screen.getByRole('textbox', { name: /^name/i })).toHaveValue('existing-tag');
  });

  test('pre-fills the values field with comma-separated options', () => {
    renderEdit(existingTag);
    expect(screen.getByRole('textbox', { name: /values/i })).toHaveValue('a,b');
  });

  test('Save calls updateTag with the tag id and updated payload', async () => {
    const user = userEvent.setup();
    vi.mocked(updateTag).mockResolvedValue(existingTag);
    renderEdit(existingTag);

    const nameInput = screen.getByRole('textbox', { name: /^name/i });
    await user.clear(nameInput);
    await user.type(nameInput, 'renamed-tag');
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    await waitFor(() => {
      expect(updateTag).toHaveBeenCalledWith(
        existingTag.tagId,
        expect.objectContaining({ name: 'renamed-tag' }),
      );
    });
  });
});
