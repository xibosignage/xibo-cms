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
// Names that users typed must not go through translation (xibo-cms PR #3948).
// i18next reads ":" as a namespace separator and "." as a key separator, so a
// daypart called "Store: Main.Hall" came out mangled in every dropdown.
//
// Translating by default is intended: fixed labels such as "Generic File" are
// translation keys and must be translated. A dropdown that lists names users
// created opts out with translateLabels={false}, as #3948 did for about 30 of
// them, so the safe setting is decided where the dropdown is used.
//
// The shared react-i18next mock returns every key unchanged, which makes a
// translated name and a raw one look the same: a test using it passes against
// the bug. This file swaps in a t() that wraps what it translates in « », and
// the first test proves that swap is the one in use.
// =============================================================================

import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

const t = vi.hoisted(() => vi.fn((key: string) => `«${key}»`));
vi.mock('react-i18next', () => ({ useTranslation: () => ({ t, i18n: {} }) }));

import SelectDropdown, { type SelectOption } from '@/components/ui/forms/SelectDropdown';
import { knownFailure } from '@/testUtils/knownFailure';

const USER_NAMED: SelectOption[] = [{ value: '5', label: 'Store: Main.Hall' }];
const FIXED: SelectOption[] = [{ value: 'genericfile', label: 'Generic File' }];

const field = () => screen.getByRole('combobox', { name: '«Daypart»' });

beforeEach(() => {
  vi.clearAllMocks();
});

describe('SelectDropdown - names made by users', () => {
  test('the translation stub in this file is the one in use', () => {
    render(<SelectDropdown label="Daypart" options={USER_NAMED} onSelect={vi.fn()} />);

    field(); // throws unless the label went through this file's t
    expect(t).toHaveBeenCalledWith('Daypart');
  });

  test('with translation turned off, a name shows exactly as the user typed it', async () => {
    const user = userEvent.setup();
    render(
      <SelectDropdown
        label="Daypart"
        options={USER_NAMED}
        onSelect={vi.fn()}
        translateLabels={false}
      />,
    );

    await user.click(field());

    expect(screen.getByRole('option')).toHaveTextContent(/^Store: Main\.Hall$/);
    expect(t).not.toHaveBeenCalledWith('Store: Main.Hall');
  });

  // A fix that translates the closed field must keep it untranslated when translation is off.
  test('the closed field shows the chosen name exactly as typed', () => {
    render(
      <SelectDropdown
        label="Daypart"
        value="5"
        options={USER_NAMED}
        onSelect={vi.fn()}
        translateLabels={false}
      />,
    );

    expect(field()).toHaveTextContent(/^Store: Main\.Hall$/);
  });
});

describe('SelectDropdown - fixed labels, translated by default', () => {
  test('by default, option labels are translated', async () => {
    const user = userEvent.setup();
    render(<SelectDropdown label="Daypart" options={FIXED} onSelect={vi.fn()} />);

    await user.click(field());

    expect(screen.getByRole('option')).toHaveTextContent(/^«Generic File»$/);
  });

  // https://github.com/xibosignage/xibo/issues/4118 (open). The list translates its labels, but the
  // closed field shows the picked one untranslated: in German the list reads "Allgemeine Datei"
  // and the field "Generic File". The fix must translate it only while translateLabels is on.
  knownFailure(
    'the closed field shows a chosen fixed label translated, as the list does',
    () => {
      render(
        <SelectDropdown label="Daypart" value="genericfile" options={FIXED} onSelect={vi.fn()} />,
      );
    },
    () => expect(field()).toHaveTextContent(/^«Generic File»$/),
  );
});
