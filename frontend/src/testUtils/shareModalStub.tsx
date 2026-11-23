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

import { screen } from '@testing-library/react';

/**
 * A stand-in for ShareModal that shows what it was opened for, so a page test can check which
 * ids a Share action hands over without running the modal itself. Use it from a mock factory:
 *
 *   vi.mock('@/components/ui/modals/ShareModal', async () => ({
 *     default: (await import('@/testUtils/shareModalStub')).ShareModalStub,
 *   }));
 */
export const ShareModalStub = ({
  entityId,
  entityType,
  title,
}: {
  entityId?: number | number[] | null;
  entityType?: string;
  title?: string;
}) => (
  <div
    role="dialog"
    aria-label="Share"
    data-entity-id={JSON.stringify(entityId ?? null)}
    data-entity-type={entityType}
    data-title={title}
  />
);

/** Waits for the stubbed Share modal and returns the ids, type and title it was opened with. */
export const sharedWith = async () => {
  const dialog = await screen.findByRole('dialog', { name: 'Share' });
  return {
    ids: JSON.parse(dialog.dataset.entityId!) as number | number[],
    type: dialog.dataset.entityType,
    title: dialog.dataset.title,
  };
};
