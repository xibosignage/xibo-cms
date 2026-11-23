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
// TagInput value entry — the "Tag Value" step that opens after a tag is added
// without a value. Whether a tag is required, or has a fixed list of values,
// comes from a fetchTags lookup for the typed name, so that lookup is the mock
// each test shapes.
//
// Accessibility fallback (xibosignage/xibo#4068): the fixed-value dropdown has
// no accessible name, so it is found as the second combobox — the first is
// the tag input itself.
// =============================================================================

import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState, type ComponentProps } from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { buildTag, EMPTY_TAG_TABLE, mockLocationTag } from './fixtures/tags';
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
// Harness — tag list in state, plus a "Reset tags" button standing in for a
// parent form that clears its tags (e.g. a modal being closed and reopened).
// -----------------------------------------------------------------------------
type HarnessProps = Omit<ComponentProps<typeof TagInput>, 'value' | 'onChange'> & {
  onChange?: (tags: Tag[]) => void;
};

function Harness({ onChange, ...props }: HarnessProps) {
  const [tags, setTags] = useState<Tag[]>([]);
  return (
    <>
      <TagInput
        suggestions={false}
        {...props}
        value={tags}
        onChange={(next) => {
          setTags(next);
          onChange?.(next);
        }}
      />
      <button type="button" onClick={() => setTags([])}>
        Reset tags
      </button>
    </>
  );
}

const renderTagInput = (props: HarnessProps = {}) => renderWithQueryClient(<Harness {...props} />);

const tagInput = () => screen.getByRole('combobox', { name: 'Tags' });
const valueInput = () => screen.getByRole('textbox', { name: 'Tag Value' });

// The lookup answers per tag name, like the API would.
const lookupReturns = (known: Tag[]) => {
  vi.mocked(fetchTags).mockImplementation(async ({ tag } = {}) => {
    const rows = known.filter((k) => k.tag === tag);
    return { rows, totalCount: rows.length };
  });
};

const REQUIRED_HINT = 'Enter a value for this Tag (required) and confirm by pressing enter.';
const OPTIONAL_HINT = 'Enter the value for this Tag and confirm by pressing enter on keyboard.';

beforeEach(() => {
  testQueryClient.clear();
  vi.clearAllMocks();
  vi.mocked(fetchTags).mockResolvedValue(EMPTY_TAG_TABLE);
});

describe('TagInput value entry', () => {
  test('adding a tag without a value opens a focused value box and locks the tag input', async () => {
    const user = userEvent.setup();
    renderTagInput();

    await user.type(tagInput(), 'floor{Enter}');

    expect(valueInput()).toHaveFocus();
    expect(tagInput()).toBeDisabled();
    expect(fetchTags).toHaveBeenCalledWith({ tag: 'floor', allTags: 1, length: 5 });
  });

  test('Enter saves the value, and numbers are saved as numbers', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    renderTagInput({ onChange });

    await user.type(tagInput(), 'floor{Enter}');
    await user.type(valueInput(), '3{Enter}');
    await user.type(tagInput(), 'zone{Enter}');
    await user.type(valueInput(), 'north{Enter}');

    expect(onChange).toHaveBeenLastCalledWith([
      expect.objectContaining({ tag: 'floor', value: 3 }),
      expect.objectContaining({ tag: 'zone', value: 'north' }),
    ]);
    expect(screen.queryByRole('textbox', { name: 'Tag Value' })).not.toBeInTheDocument();
  });

  test('an empty value on an optional tag keeps the tag without a value', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    renderTagInput({ onChange });

    await user.type(tagInput(), 'floor{Enter}');
    await user.keyboard('{Enter}');

    expect(screen.queryByRole('textbox', { name: 'Tag Value' })).not.toBeInTheDocument();
    expect(onChange).toHaveBeenLastCalledWith([
      expect.objectContaining({ tag: 'floor', value: '' }),
    ]);
  });

  test('a required tag will not accept an empty value until something is typed', async () => {
    const user = userEvent.setup();
    lookupReturns([buildTag({ tag: 'floor', isRequired: 1 })]);
    renderTagInput();

    await user.type(tagInput(), 'floor{Enter}');
    await screen.findByText(REQUIRED_HINT);
    await user.keyboard('{Enter}');

    expect(screen.getByText('This Tag requires a value.')).toBeInTheDocument();
    expect(valueInput()).toBeInTheDocument();

    await user.type(valueInput(), '2');
    expect(screen.queryByText('This Tag requires a value.')).not.toBeInTheDocument();
  });

  test('Escape keeps an optional tag but removes a required one', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    lookupReturns([buildTag({ tag: 'zone', isRequired: 1 })]);
    renderTagInput({ onChange });

    await user.type(tagInput(), 'floor{Enter}');
    await user.keyboard('{Escape}');
    expect(screen.queryByRole('textbox', { name: 'Tag Value' })).not.toBeInTheDocument();
    expect(screen.getByText('floor')).toBeInTheDocument();

    await user.type(tagInput(), 'zone{Enter}');
    await screen.findByText(REQUIRED_HINT);
    await user.keyboard('{Escape}');

    expect(onChange).toHaveBeenLastCalledWith([expect.objectContaining({ tag: 'floor' })]);
    expect(screen.queryByText('zone')).not.toBeInTheDocument();
  });

  test('a tag with a fixed list of values offers a dropdown, and choosing one saves it', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    lookupReturns([mockLocationTag]);
    renderTagInput({ onChange });

    await user.type(tagInput(), 'location{Enter}');
    await screen.findByText('Select a value for this Tag (required).');
    await user.click(screen.getAllByRole('combobox')[1]!);
    await user.click(await screen.findByRole('option', { name: 'south' }));

    expect(onChange).toHaveBeenLastCalledWith([
      expect.objectContaining({ tag: 'location', value: 'south' }),
    ]);
    expect(screen.queryByText('Tag Value')).not.toBeInTheDocument();
  });

  test('the hint under the value box matches the kind of tag', async () => {
    const user = userEvent.setup();
    lookupReturns([
      buildTag({ tag: 'req', isRequired: 1 }),
      buildTag({ tag: 'opts', options: '["a","b"]' }),
      buildTag({ tag: 'reqopts', isRequired: 1, options: '["a","b"]' }),
    ]);
    renderTagInput();

    const cases: [string, string][] = [
      ['free', OPTIONAL_HINT],
      ['req', REQUIRED_HINT],
      ['opts', 'Select a value for this Tag, or leave blank.'],
      ['reqopts', 'Select a value for this Tag (required).'],
    ];

    for (const [name, hint] of cases) {
      await user.type(tagInput(), `${name}{Enter}`);
      await screen.findByText(hint);
      await user.click(screen.getByRole('button', { name: `Remove tag ${name}` }));
    }
  });

  test('typing "name|value" adds the tag with its value and skips the value box', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    renderTagInput({ onChange });

    await user.type(tagInput(), 'floor|3{Enter}');

    expect(onChange).toHaveBeenLastCalledWith([
      expect.objectContaining({ tag: 'floor', value: 3 }),
    ]);
    expect(screen.queryByRole('textbox', { name: 'Tag Value' })).not.toBeInTheDocument();
    expect(fetchTags).not.toHaveBeenCalled();
  });

  test('when values are turned off, no value box appears', async () => {
    const user = userEvent.setup();
    renderTagInput({ allowValues: false });

    await user.type(tagInput(), 'floor{Enter}');

    expect(screen.queryByRole('textbox', { name: 'Tag Value' })).not.toBeInTheDocument();
    expect(tagInput()).toBeEnabled();
  });

  test('the caller is told when value entry starts and when it ends', async () => {
    const user = userEvent.setup();
    const onPendingValueChange = vi.fn();
    renderTagInput({ onPendingValueChange });

    await user.type(tagInput(), 'floor{Enter}');
    expect(onPendingValueChange).toHaveBeenLastCalledWith(true);

    await user.keyboard('{Enter}');
    expect(onPendingValueChange).toHaveBeenLastCalledWith(false);
  });

  test('removing the tag being edited closes the value box', async () => {
    // Required, so leaving the value box (when the remove button is clicked)
    // does not close it by itself — only the removal can.
    const user = userEvent.setup();
    const onChange = vi.fn();
    lookupReturns([buildTag({ tag: 'floor', isRequired: 1 })]);
    renderTagInput({ onChange });

    await user.type(tagInput(), 'floor{Enter}');
    await screen.findByText(REQUIRED_HINT);
    await user.click(screen.getByRole('button', { name: 'Remove tag floor' }));

    expect(screen.queryByRole('textbox', { name: 'Tag Value' })).not.toBeInTheDocument();
    expect(onChange).toHaveBeenLastCalledWith([]);
  });

  test('when the caller clears all tags, the value box closes', async () => {
    const user = userEvent.setup();
    lookupReturns([buildTag({ tag: 'floor', isRequired: 1 })]);
    renderTagInput();

    await user.type(tagInput(), 'floor{Enter}');
    await screen.findByText(REQUIRED_HINT);
    await user.click(screen.getByRole('button', { name: 'Reset tags' }));

    await waitFor(() =>
      expect(screen.queryByRole('textbox', { name: 'Tag Value' })).not.toBeInTheDocument(),
    );
    expect(tagInput()).toBeEnabled();
  });
});
