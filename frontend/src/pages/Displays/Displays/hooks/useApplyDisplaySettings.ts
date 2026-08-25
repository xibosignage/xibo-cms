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

import { useState } from 'react';

import { updateDisplay, type UpdateDisplayRequest } from '@/services/displaysApi';
import type { Display } from '@/types/display';

export interface DisplaySettingsToApply {
  displayName: string;
  folderId: number | null;
  authorise: boolean;
}

/** Display.languages is stored comma-joined, but the edit endpoint expects an array. */
function splitLanguages(languages: string | null): string[] | undefined {
  if (!languages) {
    return undefined;
  }

  const parts = languages
    .split(',')
    .map((part) => part.trim())
    .filter((part) => part !== '');

  return parts.length > 0 ? parts : undefined;
}

/**
 * Apply the name, folder and authorisation an operator chose to a display that has just registered.
 *
 * IMPORTANT - PUT /display/{id} is a FULL REPLACE. Display::edit() reads roughly thirty parameters
 * with no defaults, including `license`, which is the Player's hardware key. Sending a partial
 * payload blanks it and permanently disconnects the Player, and also wipes description, timeZone,
 * displayProfileId, bandwidthLimit and the ref fields.
 *
 * So every field is carried across from the display we just read, and only the three the operator
 * actually chose are overridden. The field set below mirrors EditDisplayModal's own update call -
 * if a field is added there, add it here too.
 */
export function useApplyDisplaySettings() {
  const [isApplying, setIsApplying] = useState(false);
  const [error, setError] = useState<string | undefined>();

  const apply = async (
    display: Display,
    settings: DisplaySettingsToApply,
  ): Promise<Display | null> => {
    setIsApplying(true);
    setError(undefined);

    // Carry everything forward, then override only what the operator chose.
    const payload: UpdateDisplayRequest = {
      // --- the three fields this feature exists to set ---
      display: settings.displayName,
      folderId: settings.folderId ?? display.folderId ?? null,
      licensed: settings.authorise ? 1 : 0,

      // --- carried across verbatim; must not be dropped ---
      license: display.license,
      description: display.description || undefined,
      incSchedule: display.incSchedule,
      emailAlert: display.emailAlert,
      alertTimeout: display.alertTimeout,
      latitude: display.latitude,
      longitude: display.longitude,
      timeZone: display.timeZone || undefined,
      // Stored comma-joined, sent as an array. Display::edit() nulls it when the param is absent,
      // so it has to be split and resent or the display loses its languages.
      languages: splitLanguages(display.languages),
      displayTypeId: display.displayTypeId,
      venueId: display.venueId,
      address: display.address || undefined,
      screenSize: display.screenSize,
      isMobile: display.isMobile,
      isOutdoor: display.isOutdoor,
      bandwidthLimit: display.bandwidthLimit,
      costPerPlay: display.costPerPlay,
      impressionsPerPlay: display.impressionsPerPlay,
      ref1: display.ref1 || undefined,
      ref2: display.ref2 || undefined,
      ref3: display.ref3 || undefined,
      ref4: display.ref4 || undefined,
      ref5: display.ref5 || undefined,
      customId: display.customId || undefined,
      displayProfileId: display.displayProfileId,
      defaultLayoutId: display.defaultLayoutId,
      wakeOnLanEnabled: display.wakeOnLanEnabled,
      broadCastAddress: display.broadCastAddress || undefined,
      secureOn: display.secureOn || undefined,
      wakeOnLanTime: display.wakeOnLanTime || undefined,
      cidr: display.cidr || undefined,
      teamViewerSerial: display.teamViewerSerial || undefined,
      webkeySerial: display.webkeySerial || undefined,
    };

    try {
      const updated = await updateDisplay(display.displayId, payload);
      return updated;
    } catch (err: unknown) {
      // The display exists either way - it just kept its default name and folder.
      setError(err instanceof Error ? err.message : undefined);
      return null;
    } finally {
      setIsApplying(false);
    }
  };

  return { apply, isApplying, error };
}
