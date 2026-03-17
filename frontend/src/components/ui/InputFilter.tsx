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
import { twMerge } from 'tailwind-merge';

type InputFilterProps = {
  label: string;
  name: string;
  placeholder?: string;
  type: 'text' | 'number';
  value: string | number;
  onChange: (name: string, value: string | number | null) => void;
  className?: string;
};

export default function InputFilter({
  label,
  name,
  placeholder,
  type,
  value,
  onChange,
  className,
}: InputFilterProps) {
  const { t } = useTranslation();

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const val = e.target.value;

    if (type === 'number') {
      onChange(name, val === '' ? null : Number(val));
    } else {
      onChange(name, val);
    }
  };

  return (
    <div
      className={twMerge(
        'flex flex-col gap-1 text-gray-500 w-full md:w-auto md:flex-1 min-w-0 relative',
        className,
      )}
    >
      <label className="text-sm font-semibold">{t(label)}</label>
      <input
        type={type}
        value={value ?? ''}
        onChange={handleChange}
        placeholder={t(placeholder || label)}
        className="w-full bg-white rounded-lg border border-gray-200 px-4 py-[13.5px] text-left text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all"
      />
    </div>
  );
}
