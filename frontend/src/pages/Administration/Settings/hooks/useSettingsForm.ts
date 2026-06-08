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

import { useEffect, useRef, useState } from 'react';

import type { SettingsData, SettingsFormValues } from '@/types/settings';

export function useSettingsForm(data: SettingsData | undefined) {
  const [formValues, setFormValues] = useState<SettingsFormValues>({});
  const [isDirty, setIsDirty] = useState(false);
  const initializedRef = useRef(false);

  // Initialize form values when data first loads
  useEffect(() => {
    if (data && !initializedRef.current) {
      const values: SettingsFormValues = {};
      for (const [key, meta] of Object.entries(data.settings)) {
        values[key] = meta.value ?? '';
      }
      // ELEVATE_LOG_UNTIL: use the formatted date from the API (or empty if expired/null)
      // The raw DB value is a Unix timestamp which the backend's getDate() cannot parse
      values['ELEVATE_LOG_UNTIL'] = data.elevateLogUntil ?? '';
      setFormValues(values);
      initializedRef.current = true;
    }
  }, [data]);

  const updateField = (key: string, value: string) => {
    setFormValues((prev) => ({ ...prev, [key]: value }));
    setIsDirty(true);
  };

  const isVisible = (key: string): boolean => {
    return data?.settings[key]?.userSee === 1;
  };

  const isEditable = (key: string): boolean => {
    const meta = data?.settings[key];
    return meta?.userSee === 1 && meta?.userChange === 1;
  };

  const resetForm = () => {
    if (data) {
      const values: SettingsFormValues = {};
      for (const [key, meta] of Object.entries(data.settings)) {
        values[key] = meta.value ?? '';
      }
      values['ELEVATE_LOG_UNTIL'] = data.elevateLogUntil ?? '';
      setFormValues(values);
      setIsDirty(false);
      initializedRef.current = true;
    }
  };

  return { formValues, updateField, isVisible, isEditable, isDirty, resetForm };
}
