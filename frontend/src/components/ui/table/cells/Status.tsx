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
.*/

interface StatusProps {
  label: string;
  type?: 'success' | 'warning' | 'danger' | 'info' | 'neutral';
}

const styles = {
  success: 'bg-teal-100',
  warning: 'bg-yellow-100',
  danger: 'bg-red-100',
  info: 'bg-blue-100',
  neutral: 'bg-gray-100',
};

export function Status({ label, type = 'neutral' }: StatusProps) {
  return <span className={`inline-flex gap-x-1.5 py-1 px-2 ${styles[type]}`}>{label}</span>;
}
