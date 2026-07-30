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
