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

/*
 * Checks the fast getComputedStyle in src/setupTests.ts against what a browser (and jsdom
 * without the shim) answers. Run it both ways when changing the shim or upgrading jsdom:
 *   npx vitest run src/testUtils/__tests__/fastComputedStyle.test.tsx
 *   VITEST_REAL_STYLES=1 npx vitest run src/testUtils/__tests__/fastComputedStyle.test.tsx
 */
import { render, screen } from '@testing-library/react';
import { describe, expect, test } from 'vitest';

describe('Fast getComputedStyle', () => {
  test('accessible names keep the space between block-level children', () => {
    render(
      <>
        <button>
          <div>Delete</div>
          <div>item</div>
        </button>
        <button>
          <span>Save</span>
          <span>draft</span>
        </button>
        <a href="#list">
          <p>Open</p>
          list
        </a>
        <table>
          <tbody>
            <tr>
              <td>Main</td>
              <td>Layout</td>
            </tr>
          </tbody>
        </table>
      </>,
    );

    expect
      .soft(screen.queryByRole('button', { name: 'Delete item' }), 'div children')
      .toBeInTheDocument();
    expect
      .soft(screen.queryByRole('button', { name: 'Savedraft' }), 'span children')
      .toBeInTheDocument();
    expect
      .soft(screen.queryByRole('link', { name: 'Open list' }), 'p then text')
      .toBeInTheDocument();
    expect
      .soft(screen.queryByRole('row', { name: 'Main Layout' }), 'table cells')
      .toBeInTheDocument();
  });

  test('default display matches the browser for common tags', () => {
    const expected: Record<string, string> = {
      div: 'block',
      span: 'inline',
      p: 'block',
      button: 'inline-block',
      input: 'inline-block',
      li: 'list-item',
      table: 'table',
      tr: 'table-row',
      td: 'table-cell',
      th: 'table-cell',
      thead: 'table-header-group',
      tbody: 'table-row-group',
      a: 'inline',
      label: 'inline',
    };
    for (const [tag, display] of Object.entries(expected)) {
      const el = document.createElement(tag);
      document.body.appendChild(el);
      expect.soft(getComputedStyle(el).display, tag).toBe(display);
      expect
        .soft(getComputedStyle(el).getPropertyValue('display'), `${tag} via getPropertyValue`)
        .toBe(display);
      el.remove();
    }
  });

  test('elements hidden by attribute, tag or inline style are not visible', () => {
    render(
      <>
        <p hidden>Hidden attribute</p>
        <dialog>Closed dialog</dialog>
        <dialog open>Open dialog</dialog>
        <input type="hidden" data-testid="hidden-input" />
        <p style={{ display: 'none' }}>Inline display none</p>
        <div style={{ visibility: 'hidden' }}>
          <p>Inside visibility hidden</p>
        </div>
        <div style={{ opacity: 0 }}>Opacity zero</div>
        <p>Plain paragraph</p>
      </>,
    );

    expect.soft(screen.getByText('Hidden attribute'), 'hidden attribute').not.toBeVisible();
    expect.soft(screen.getByText('Closed dialog'), 'closed dialog').not.toBeVisible();
    expect.soft(screen.getByText('Open dialog'), 'open dialog').toBeVisible();
    expect.soft(screen.getByTestId('hidden-input'), 'input type=hidden').not.toBeVisible();
    expect.soft(screen.getByText('Inline display none'), 'inline display none').not.toBeVisible();
    expect
      .soft(screen.getByText('Inside visibility hidden'), 'inherited visibility')
      .not.toBeVisible();
    expect.soft(screen.getByText('Opacity zero'), 'opacity 0').not.toBeVisible();
    expect.soft(screen.getByText('Plain paragraph'), 'plain paragraph').toBeVisible();
  });

  test('pointer-events inherits, including through an explicit inherit', () => {
    render(
      <div data-testid="outer" style={{ pointerEvents: 'none' }}>
        <div data-testid="inherits">
          <span data-testid="explicit" style={{ pointerEvents: 'inherit' }} />
        </div>
      </div>,
    );

    expect
      .soft(getComputedStyle(screen.getByTestId('inherits')).pointerEvents, 'implicit')
      .toBe('none');
    expect
      .soft(getComputedStyle(screen.getByTestId('explicit')).pointerEvents, 'explicit inherit')
      .toBe('none');
    expect.soft(getComputedStyle(document.body).pointerEvents, 'body').toBe('auto');
  });

  test('unset and initial resolve like jsdom does', () => {
    render(
      <div style={{ pointerEvents: 'none', visibility: 'hidden', display: 'flex' }}>
        <div
          data-testid="unset"
          style={{ pointerEvents: 'unset', visibility: 'unset', display: 'unset' }}
        />
        <div
          data-testid="initial"
          style={{ pointerEvents: 'initial', visibility: 'initial', display: 'initial' }}
        />
      </div>,
    );

    // unset inherits for inherited properties; display is not inherited, so it goes to inline.
    const unset = getComputedStyle(screen.getByTestId('unset'));
    expect.soft(unset.pointerEvents, 'unset pointer-events').toBe('none');
    expect.soft(unset.visibility, 'unset visibility').toBe('hidden');
    expect.soft(unset.display, 'unset display').toBe('inline');
    const initial = getComputedStyle(screen.getByTestId('initial'));
    expect.soft(initial.pointerEvents, 'initial pointer-events').toBe('auto');
    expect.soft(initial.visibility, 'initial visibility').toBe('visible');
    expect.soft(initial.display, 'initial display').toBe('inline');
  });

  test('`in` checks see the real style properties', () => {
    const style = getComputedStyle(document.body);

    expect.soft('display' in style, 'display').toBe(true);
    expect.soft('color' in style, 'color').toBe(true);
    expect.soft('getPropertyValue' in style, 'getPropertyValue').toBe(true);
  });

  test("reading a tag's default display does not touch the test's document", () => {
    let constructed = 0;
    customElements.define(
      'fast-style-probe-check',
      class extends HTMLElement {
        constructor() {
          super();
          constructed += 1;
        }
      },
    );
    const el = document.createElement('fast-style-probe-check');
    document.body.appendChild(el);
    const observer = new MutationObserver(() => {});
    observer.observe(document, { childList: true, subtree: true });

    // figcaption is used nowhere else in this file, so its default is not cached yet.
    const caption = document.createElement('figcaption');
    expect.soft(getComputedStyle(el).display, 'custom element display').toBe('inline');
    expect.soft(getComputedStyle(caption).display, 'figcaption display').toBe('block');

    expect.soft(constructed, 'custom element constructor runs once').toBe(1);
    expect.soft(observer.takeRecords(), 'no mutations in the document').toHaveLength(0);
    observer.disconnect();
    el.remove();
  });

  test('other properties still come from jsdom', () => {
    render(<div data-testid="styled" style={{ color: 'rgb(255, 0, 0)', paddingLeft: '4px' }} />);

    const style = getComputedStyle(screen.getByTestId('styled'));
    expect.soft(style.color, 'color').toBe('rgb(255, 0, 0)');
    expect.soft(style.getPropertyValue('padding-left'), 'padding-left').toBe('4px');
  });
});
