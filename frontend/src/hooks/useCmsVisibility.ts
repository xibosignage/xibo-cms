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

/** Whether this CMS offers a service (e.g. "billing"), or "pending" until that's known. */
export type ServiceVisibility = 'shown' | 'hidden' | 'pending';

export type GetServiceVisibility = (service: string) => ServiceVisibility;

const allShown: GetServiceVisibility = () => 'shown';

const CMS_VISIBILITY: { useServiceVisibility: () => GetServiceVisibility } = {
  useServiceVisibility: () => allShown,
};

/** For routes, tabs and features keyed with a `service`: only "shown" ones are offered. */
export const useServiceVisibility = CMS_VISIBILITY.useServiceVisibility;

export const isServiceShown = (
  getVisibility: GetServiceVisibility,
  service: string | undefined,
): boolean => !service || getVisibility(service) === 'shown';
