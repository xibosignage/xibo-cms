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

import type { TFunction } from 'i18next';
import { describe, test, expect } from 'vitest';

import { PERSONAS } from '../index';

import { getFeatureDefinitions } from '@/pages/Administration/Users/config/featureDefinitions';

// =============================================================================
// Safety net against future drift — proves every persona's feature keys are
// still real, known features. Does NOT prove a persona has every feature it
// should (that's a judgment call whenever a new feature ships).
// =============================================================================

const t = ((key: string) => key) as unknown as TFunction;

// Known, already-reported gap: the backend emits `dataset.dataConnector`,
// the frontend catalog expects `dataset.realtime`. Not a persona error —
// excluded so it doesn't mask a real new mismatch. Remove once fixed on
// either side.
const KNOWN_GAPS = new Set(['dataset.dataConnector']);

describe('PERSONAS integrity', () => {
  const knownFeatures = new Set(getFeatureDefinitions(t).map((f) => f.feature));

  test.each(Object.entries(PERSONAS))(
    '%s: every feature key is real and known',
    (_name, persona) => {
      for (const key of Object.keys(persona.features)) {
        if (KNOWN_GAPS.has(key)) continue;
        expect(knownFeatures.has(key)).toBe(true);
      }
    },
  );
});
