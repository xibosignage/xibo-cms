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

import { describe, expect, test } from 'vitest';

import { recoverPlayback } from '@/utils/recoverPlayback';

function mediaElement(currentTime: number): HTMLMediaElement {
  const element = document.createElement('video');
  element.src = 'https://cms.example.com/json/library/download/5?stream=1';
  Object.defineProperty(element, 'currentTime', { value: currentTime, writable: true });
  return element;
}

describe('recoverPlayback', () => {
  test('a failure before playback started is reloaded once, as the link may have expired while waiting', () => {
    const element = mediaElement(0);

    expect(recoverPlayback(element, 'https://cms.example.com/fresh', 1000)).toBe(true);
    expect(recoverPlayback(element, 'https://cms.example.com/fresh', 2000)).toBe(false);
  });

  test('a failure during playback reloads the source and returns to the same position', () => {
    const element = mediaElement(42);

    expect(recoverPlayback(element, 'https://cms.example.com/fresh', 1000)).toBe(true);
    expect(element.src).toBe('https://cms.example.com/fresh');

    element.currentTime = 0;
    element.dispatchEvent(new Event('loadedmetadata'));
    expect(element.currentTime).toBe(42);
  });

  test('a second failure straight after a reload is not recovered again', () => {
    const element = mediaElement(42);

    expect(recoverPlayback(element, 'https://cms.example.com/fresh', 1000)).toBe(true);
    expect(recoverPlayback(element, 'https://cms.example.com/fresh', 5000)).toBe(false);
    element.dispatchEvent(new Event('loadedmetadata'));
    expect(recoverPlayback(element, 'https://cms.example.com/fresh', 20000)).toBe(true);
  });
});
