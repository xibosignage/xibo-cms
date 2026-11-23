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

// =============================================================================
// Test type: Component test
// TagInput basic entry — adding, removing, locked and disabled tags, labels.
// Suggestions are switched off here (see TagInput.suggestions.test.tsx) and
// values are switched off where the value step would get in the way (see
// TagInput.values.test.tsx). Only the tag API is mocked.
// =============================================================================

import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState, type ComponentProps } from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { buildTag, EMPTY_TAG_TABLE } from './fixtures/tags';
import { renderWithQueryClient } from './helpers/renderWithQueryClient';

import TagInput from '@/components/ui/forms/TagInput';
import { fetchTags } from '@/services/tagApi';
import { testQueryClient } from '@/setupTests';
import type { Tag } from '@/types/tag';

vi.mock('@/hooks/useDebounce');
vi.mock('@/services/tagApi', () => ({
  fetchTags: vi.fn(),
}));

// -----------------------------------------------------------------------------
// Harness — keeps the tag list in state so chips update like they do in a form,
// and reports every change to the spy the test passes in.
// -----------------------------------------------------------------------------
type HarnessProps = Omit<ComponentProps<typeof TagInput>, 'value' | 'onChange'> & {
  initial?: Tag[];
  onChange?: (tags: Tag[]) => void;
};

function Harness({ initial = [], onChange, ...props }: HarnessProps) {
  const [tags, setTags] = useState<Tag[]>(initial);
  return (
    <TagInput
      suggestions={false}
      {...props}
      value={tags}
      onChange={(next) => {
        setTags(next);
        onChange?.(next);
      }}
    />
  );
}

const renderTagInput = (props: HarnessProps = {}) => renderWithQueryClient(<Harness {...props} />);

const tagInput = (name: string | RegExp = 'Tags') => screen.getByRole('combobox', { name });

beforeEach(() => {
  testQueryClient.clear();
  vi.clearAllMocks();
  vi.mocked(fetchTags).mockResolvedValue(EMPTY_TAG_TABLE);
});

describe('TagInput', () => {
  describe('labels and hints', () => {
    test('shows the default label, a custom label and the Optional marker', () => {
      const { unmount } = renderTagInput();
      expect(tagInput('Tags')).toBeInTheDocument();
      expect(screen.queryByText('Optional')).not.toBeInTheDocument();
      unmount();

      renderTagInput({ label: 'Labels', optional: true });
      expect(tagInput(/Labels/)).toBeInTheDocument();
      expect(screen.getByText('Optional')).toBeInTheDocument();
    });

    test('shows the placeholder only while there are no tags', async () => {
      const user = userEvent.setup();
      const { unmount } = renderTagInput();
      expect(tagInput()).toHaveAttribute('placeholder', 'Add tags');
      unmount();

      renderTagInput({ placeholder: 'Type a tag', allowValues: false });
      expect(tagInput()).toHaveAttribute('placeholder', 'Type a tag');

      await user.type(tagInput(), 'lobby{Enter}');
      expect(tagInput()).toHaveAttribute('placeholder', '');
    });

    test('shows existing tags, with their values', () => {
      renderTagInput({
        initial: [buildTag({ tag: 'lobby' }), buildTag({ tag: 'floor', value: 3 })],
      });
      expect(screen.getByText('lobby')).toBeInTheDocument();
      expect(screen.getByText('floor|3')).toBeInTheDocument();
    });

    test('an error replaces the help text', () => {
      const { rerender } = renderTagInput({ helpText: 'Use commas' });
      expect(screen.getByText('Use commas')).toBeInTheDocument();

      rerender(<Harness helpText="Use commas" error="Too many tags" />);
      expect(screen.getByText('Too many tags')).toBeInTheDocument();
      expect(screen.queryByText('Use commas')).not.toBeInTheDocument();
    });
  });

  describe('adding tags', () => {
    test('pressing Enter adds the typed tag', async () => {
      const user = userEvent.setup();
      const onChange = vi.fn();
      renderTagInput({ onChange, allowValues: false });

      await user.type(tagInput(), 'lobby{Enter}');

      expect(onChange).toHaveBeenCalledTimes(1);
      expect(onChange).toHaveBeenCalledWith([{ tag: 'lobby', value: '', tagId: 0 }]);
      expect(tagInput()).toHaveValue('');
    });

    test('typing a comma adds the typed tag', async () => {
      const user = userEvent.setup();
      const onChange = vi.fn();
      renderTagInput({ onChange, allowValues: false });

      await user.type(tagInput(), 'lobby,');

      expect(onChange).toHaveBeenCalledWith([expect.objectContaining({ tag: 'lobby' })]);
      expect(tagInput()).toHaveValue('');
    });

    test('leaving the input adds the typed tag', async () => {
      const user = userEvent.setup();
      const onChange = vi.fn();
      renderTagInput({ onChange, allowValues: false });

      await user.type(tagInput(), 'lobby');
      await user.tab();

      expect(onChange).toHaveBeenCalledWith([expect.objectContaining({ tag: 'lobby' })]);
    });

    test('a duplicate tag or spaces-only text is not added', async () => {
      const user = userEvent.setup();
      const onChange = vi.fn();
      renderTagInput({ onChange, allowValues: false, initial: [buildTag({ tag: 'lobby' })] });

      await user.type(tagInput(), 'lobby{Enter}');
      await user.clear(tagInput());
      await user.type(tagInput(), '   {Enter}');

      expect(onChange).not.toHaveBeenCalled();
    });

    test('when the caller controls the typed text, typing is reported and the caller text is shown', async () => {
      const user = userEvent.setup();
      const onInputChange = vi.fn();
      renderTagInput({ inputValue: 'lob', onInputChange });

      await user.type(tagInput(), 'b');

      expect(onInputChange).toHaveBeenLastCalledWith('lobb');
      expect(tagInput()).toHaveValue('lob');
    });
  });

  describe('removing tags', () => {
    test('the remove button removes that tag', async () => {
      const user = userEvent.setup();
      const onChange = vi.fn();
      renderTagInput({
        onChange,
        initial: [buildTag({ tag: 'lobby' }), buildTag({ tag: 'lounge' })],
      });

      await user.click(screen.getByRole('button', { name: 'Remove tag lobby' }));

      expect(onChange).toHaveBeenCalledWith([expect.objectContaining({ tag: 'lounge' })]);
    });

    test('Backspace in an empty input removes the last tag', async () => {
      const user = userEvent.setup();
      const onChange = vi.fn();
      renderTagInput({
        onChange,
        initial: [buildTag({ tag: 'lobby' }), buildTag({ tag: 'lounge' })],
      });

      await user.click(tagInput());
      await user.keyboard('{Backspace}');

      expect(onChange).toHaveBeenCalledWith([expect.objectContaining({ tag: 'lobby' })]);
    });

    test('locked tags have no remove button and are not removed by Backspace', async () => {
      // Current behaviour, pending a product decision: Backspace only ever targets
      // the last tag, so a locked last tag also shields the unlocked tags before it.
      const user = userEvent.setup();
      const onChange = vi.fn();
      renderTagInput({
        onChange,
        lockedTags: ['lounge'],
        initial: [buildTag({ tag: 'lobby' }), buildTag({ tag: 'lounge' })],
      });

      expect(screen.getByRole('button', { name: 'Remove tag lobby' })).toBeInTheDocument();
      expect(screen.queryByRole('button', { name: 'Remove tag lounge' })).not.toBeInTheDocument();

      await user.click(tagInput());
      await user.keyboard('{Backspace}');

      expect(onChange).not.toHaveBeenCalled();
      expect(screen.getByText('lounge')).toBeInTheDocument();
    });

    test('when disabled, the input and remove buttons cannot be used', () => {
      renderTagInput({ disabled: true, initial: [buildTag({ tag: 'lobby' })] });

      expect(tagInput()).toBeDisabled();
      expect(screen.getByRole('button', { name: 'Remove tag lobby' })).toBeDisabled();
    });
  });
});
