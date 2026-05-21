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

import { useTranslation } from 'react-i18next';

import SelectDropdown from '@/components/ui/forms/SelectDropdown';

const getColorOptions = (t: ReturnType<typeof useTranslation>['t']) => [
  { value: 'off', label: t('Off') },
  { value: 'red', label: t('Red') },
  { value: 'green', label: t('Green') },
  { value: 'blue', label: t('Blue') },
  { value: 'white', label: t('White') },
];

interface PhilipsAndroidFieldsProps {
  value: string;
  onChange: (value: string) => void;
}

export default function PhilipsAndroidFields({ value, onChange }: PhilipsAndroidFieldsProps) {
  const { t } = useTranslation();

  return (
    <SelectDropdown
      label={t('LED Colour')}
      value={value}
      options={getColorOptions(t)}
      onSelect={(val) => onChange(val || 'off')}
    />
  );
}
