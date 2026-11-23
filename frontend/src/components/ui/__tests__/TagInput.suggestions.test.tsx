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
// TagInput suggestions — the list of existing tags shown while typing. Uses
// the real useTagSuggestions hook (React Query) with the debounce bypassed;
// only fetchTags is mocked, and it returns the same three tags for any term.
// =============================================================================

import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState, type ComponentProps } from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { buildTag, SUGGESTION_TAGS } from './fixtures/tags';
import { renderWithQueryClient } from './helpers/renderWithQueryClient';

import TagInput from '@/components/ui/forms/TagInput';
import { fetchTags } from '@/services/tagApi';
import { testQueryClient } from '@/setupTests';
import type { Tag } from '@/types/tag';

vi.mock('@/hooks/useDebounce');
vi.mock('@/services/tagApi', () => ({
  fetchTags: vi.fn(),
}));

type HarnessProps = Omit<ComponentProps<typeof TagInput>, 'value' | 'onChange'> & {
  initial?: Tag[];
  onChange?: (tags: Tag[]) => void;
};

function Harness({ initial = [], onChange, ...props }: HarnessProps) {
  const [tags, setTags] = useState<Tag[]>(initial);
  return (
    <TagInput
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

const tagInput = () => screen.getByRole('combobox', { name: 'Tags' });
const suggestionNames = (listbox: HTMLElement) =>
  within(listbox)
    .getAllByRole('option')
    .map((o) => o.textContent);

beforeEach(() => {
  testQueryClient.clear();
  vi.clearAllMocks();
  vi.mocked(fetchTags).mockResolvedValue(SUGGESTION_TAGS);
});

describe('TagInput suggestions', () => {
  test('typing shows matching tags, leaving out tags already added', async () => {
    const user = userEvent.setup();
    renderTagInput({ initial: [buildTag({ tag: 'lounge' })] });

    await user.type(tagInput(), 'lo');

    const listbox = await screen.findByRole('listbox');
    expect(suggestionNames(listbox)).toEqual(['lobby', 'location']);
    expect(fetchTags).toHaveBeenCalledWith(
      expect.objectContaining({ tag: 'lo', allTags: 1, length: 10 }),
    );
  });

  test('clicking a suggestion adds it', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    renderTagInput({ onChange, allowValues: false });

    await user.type(tagInput(), 'lo');
    await user.click(await screen.findByRole('option', { name: 'lobby' }));

    expect(onChange).toHaveBeenCalledTimes(1);
    expect(onChange).toHaveBeenCalledWith([expect.objectContaining({ tag: 'lobby', value: '' })]);
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
  });

  test('arrow keys move through suggestions, wrapping at the ends, and Enter adds the highlighted one', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    renderTagInput({ onChange, allowValues: false });

    // Down twice from nothing highlighted lands on the second suggestion.
    await user.type(tagInput(), 'lo');
    await screen.findByRole('listbox');
    await user.keyboard('{ArrowDown}{ArrowDown}{Enter}');
    expect(onChange).toHaveBeenLastCalledWith([expect.objectContaining({ tag: 'lounge' })]);

    // Up from nothing highlighted wraps to the last suggestion
    // (lounge is now added, so the list is lobby, location).
    await user.type(tagInput(), 'lo');
    await screen.findByRole('listbox');
    await user.keyboard('{ArrowUp}{Enter}');
    expect(onChange).toHaveBeenLastCalledWith([
      expect.objectContaining({ tag: 'lounge' }),
      expect.objectContaining({ tag: 'location' }),
    ]);
  });

  test('Escape closes the suggestion list and keeps the typed text', async () => {
    const user = userEvent.setup();
    renderTagInput();

    await user.type(tagInput(), 'lo');
    await screen.findByRole('listbox');
    await user.keyboard('{Escape}');

    await waitFor(() => expect(screen.queryByRole('listbox')).not.toBeInTheDocument());
    expect(tagInput()).toHaveValue('lo');
  });

  test('with suggestions turned off, no lookup happens and no list appears', async () => {
    const user = userEvent.setup();
    renderTagInput({ suggestions: false });

    await user.type(tagInput(), 'lo');

    expect(fetchTags).not.toHaveBeenCalled();
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
  });

  test('choosing a suggestion with a fixed list of values shows them straight away, with no extra lookup', async () => {
    const user = userEvent.setup();
    renderTagInput();

    await user.type(tagInput(), 'loc');
    await user.click(await screen.findByRole('option', { name: 'location' }));

    expect(screen.getByText('Select a value for this Tag (required).')).toBeInTheDocument();
    await user.click(screen.getAllByRole('combobox')[1]!);
    const values = await screen.findAllByRole('option');
    expect(values.map((o) => o.textContent)).toEqual(expect.arrayContaining(['north', 'south']));

    // Suggestion searches use length 10; the value-entry lookup uses length 5.
    expect(fetchTags).not.toHaveBeenCalledWith(expect.objectContaining({ length: 5 }));
  });
});
