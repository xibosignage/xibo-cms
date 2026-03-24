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

import { render, screen, fireEvent } from '@testing-library/react';
import { describe, test, expect, vi } from 'vitest';

import InputFilter from '../InputFilter';

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key: string) => key, i18n: { changeLanguage: vi.fn() } }),
}));

type RenderProps = Partial<React.ComponentProps<typeof InputFilter>>;

// Renders a text InputFilter with defaults that a test can override.
const renderInput = (props: RenderProps = {}) =>
  render(
    <InputFilter label="Name" name="name" type="text" value="" onChange={vi.fn()} {...props} />,
  );

describe('InputFilter', () => {
  // An input box shows hint text, avoids leaving the user with no idea what to type.
  test('empty box shows the label as a hint so the user knows what to type', () => {
    renderInput();

    expect(screen.getByPlaceholderText('Name')).toBeInTheDocument();
  });

  // When the user types in a text box, the page must be told two things
  test('typing in the box tells the page which field changed and what was typed', () => {
    const onChange = vi.fn();
    renderInput({ onChange });

    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'hello' } });

    expect(onChange).toHaveBeenCalledWith('name', 'hello');
  });

  // When the user types "42" in a number box, the page must receive the
  // actual number 42 — not the text "42"
  test('typing a number sends an actual number to the page, not text', () => {
    const onChange = vi.fn();
    renderInput({ label: 'Count', name: 'count', type: 'number', value: 0, onChange });

    fireEvent.change(screen.getByRole('spinbutton'), { target: { value: '42' } });

    expect(onChange).toHaveBeenCalledWith('count', 42);
  });

  // When the user deletes everything in a number box and leaves it empty,
  // that means "I don't want to filter by this any more — show everything"
  test('clearing a number box tells the page to remove that filter entirely', () => {
    const onChange = vi.fn();
    renderInput({ label: 'Count', name: 'count', type: 'number', value: 5, onChange });

    fireEvent.change(screen.getByRole('spinbutton'), { target: { value: '' } });

    expect(onChange).toHaveBeenCalledWith('count', null);
  });
});
