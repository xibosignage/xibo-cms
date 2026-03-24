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
import { Search, ChevronRight } from 'lucide-react';
import { describe, test, expect, vi } from 'vitest';

import Button from '../Button';

describe('Button', () => {
  // Button shows label
  test('shows its label so the user knows what it does', () => {
    render(<Button>Save</Button>);

    expect(screen.getByRole('button', { name: 'Save' })).toBeInTheDocument();
  });

  // When the user clicks the button, the click handler must be called exactly
  // once.
  test('runs the click handler when the user clicks it', () => {
    const onClick = vi.fn();
    render(<Button onClick={onClick}>Save</Button>);

    fireEvent.click(screen.getByRole('button'));

    expect(onClick).toHaveBeenCalledTimes(1);
  });

  // When a button is marked as disabled, it must not be clickable
  test('cannot be clicked when it is disabled', () => {
    render(<Button disabled>Save</Button>);

    expect(screen.getByRole('button')).toBeDisabled();
  });

  // An icon placed on the left of the button label helps users recognise
  // the action faster, especially for common actions like Search or Upload.
  test('shows a left icon when one is provided', () => {
    const { container } = render(<Button leftIcon={Search}>Search</Button>);

    // Lucide icons render as <svg> elements
    expect(container.querySelector('svg')).toBeInTheDocument();
  });

  // An icon placed on the right of the label is used for directional cues
  // like "Next ›". Without it the button loses its visual meaning.
  test('shows a right icon when one is provided', () => {
    const { container } = render(<Button rightIcon={ChevronRight}>Next</Button>);

    expect(container.querySelector('svg')).toBeInTheDocument();
  });

  // When both a left and a right icon are given, both must appear — for
  // example a Search icon on the left and a spinner on the right while loading.
  test('shows both icons when both are provided', () => {
    const { container } = render(
      <Button leftIcon={Search} rightIcon={ChevronRight}>
        Next
      </Button>,
    );

    expect(container.querySelectorAll('svg')).toHaveLength(2);
  });

  // Icons are purely decorative — not announced aloud for screen readers
  test('hides icons from screen readers so they are not read aloud', () => {
    const { container } = render(<Button leftIcon={Search}>Search</Button>);

    expect(container.querySelector('svg')).toHaveAttribute('aria-hidden', 'true');
  });

  // An icon-only button (no visible text) must carry an aria-label so screen
  // reader users know what it does.
  test('uses aria-label as the accessible name when there is no visible text', () => {
    render(<Button leftIcon={Search} ariaLabel="Search media" />);

    expect(screen.getByRole('button', { name: 'Search media' })).toBeInTheDocument();
  });

  // When a button already has visible text, the aria-label must NOT be set
  test('does not add aria-label when visible text is already present', () => {
    render(
      <Button leftIcon={Search} ariaLabel="Search media">
        Search
      </Button>,
    );

    // aria-label should be undefined — accessible name comes from children
    expect(screen.getByRole('button')).not.toHaveAttribute('aria-label');
  });

  // Every button defaults to type="button"
  test('defaults to type="button" to avoid accidentally submitting a form', () => {
    render(<Button>Save</Button>);

    expect(screen.getByRole('button')).toHaveAttribute('type', 'button');
  });

  // On mobile the button text stays invisible when it should be hidden to save space.
  test('hides the button text on small screens when removeTextOnMobile is on', () => {
    const { container } = render(<Button removeTextOnMobile>Save</Button>);

    const span = container.querySelector('span');
    expect(span).toHaveClass('hidden');
    expect(span).toHaveClass('sm:inline');
  });

  // When removeTextOnMobile is off (the default), the text must always be
  // visible — on both mobile and desktop.
  test('keeps the button text visible on all screen sizes by default', () => {
    const { container } = render(<Button>Save</Button>);

    const span = container.querySelector('span');
    expect(span).not.toHaveClass('hidden');
  });
});
