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

import type { AuditLog } from '@/types/auditTrail';

// Audit log builders shared by the AuditTrail tests. Kept free of vi.mock calls so a test that
// declares its own module mocks can import them without pulling in Setup.tsx's mocks.

export const createMockAuditLog = (overrides?: Partial<AuditLog>): AuditLog => ({
  logId: 1,
  logDate: 1704067200,
  userName: 'admin',
  entity: 'Layout',
  entityId: 42,
  ipAddress: '127.0.0.1',
  message: 'Layout saved',
  objectAfter: null,
  ...overrides,
});

export const mockAuditLogList: AuditLog[] = [
  createMockAuditLog({ logId: 1, entity: 'Layout', message: 'Layout saved' }),
  createMockAuditLog({
    logId: 2,
    entity: 'User',
    message: 'User updated',
    userName: 'editor',
  }),
  createMockAuditLog({
    logId: 3,
    entity: 'Campaign',
    message: 'Campaign deleted',
    objectAfter: { name: 'Test Campaign', status: 'active' },
  }),
];
