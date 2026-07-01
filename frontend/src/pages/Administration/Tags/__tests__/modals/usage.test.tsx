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

import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { buildTag } from '../fixtures/tag';

import TagUsageModal from '@/pages/Administration/Tags/components/TagUsageModal';
import { fetchTagUsage } from '@/services/tagApi';
import type { TagUsageEntry } from '@/types/tag';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/tagApi');
vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Helpers
// =============================================================================

const mockTag = buildTag({ tagId: 3, tag: 'location' });
const onClose = vi.fn();

const mockUsageEntry: TagUsageEntry = {
  entityId: 10,
  type: 'Layout',
  name: 'My Layout',
  value: null,
};

const renderModal = (tag = mockTag) => render(<TagUsageModal isOpen tag={tag} onClose={onClose} />);

// =============================================================================
// Tests
// =============================================================================

describe('TagUsageModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  test('calls fetchTagUsage on mount with the tag id', async () => {
    vi.mocked(fetchTagUsage).mockResolvedValue({ rows: [], totalCount: 0 });
    renderModal();

    await waitFor(() => {
      expect(fetchTagUsage).toHaveBeenCalledWith(mockTag.tagId, expect.any(AbortSignal));
    });
  });

  test('shows "Loading..." while the request is in flight', async () => {
    let resolveUsage!: (v: { rows: TagUsageEntry[]; totalCount: number }) => void;
    vi.mocked(fetchTagUsage).mockReturnValueOnce(
      new Promise((res) => {
        resolveUsage = res;
      }),
    );
    renderModal();

    expect(screen.getByText('Loading...')).toBeInTheDocument();

    resolveUsage({ rows: [], totalCount: 0 });
    await waitFor(() => expect(screen.queryByText('Loading...')).not.toBeInTheDocument());
  });

  test('shows "This tag is not currently used." when there are no entries', async () => {
    vi.mocked(fetchTagUsage).mockResolvedValue({ rows: [], totalCount: 0 });
    renderModal();

    await screen.findByText('This tag is not currently used.');
  });

  test('renders usage entries in a table', async () => {
    vi.mocked(fetchTagUsage).mockResolvedValue({ rows: [mockUsageEntry], totalCount: 1 });
    renderModal();

    await screen.findByText('My Layout');
    expect(screen.getByText('Layout')).toBeInTheDocument();
    expect(screen.getByText('10')).toBeInTheDocument();
  });

  test('shows an error when fetchTagUsage rejects', async () => {
    vi.mocked(fetchTagUsage).mockRejectedValue(new Error('Failed to fetch usage'));
    renderModal();

    await screen.findByText('Failed to fetch usage');
  });

  test('clicking Close calls onClose', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchTagUsage).mockResolvedValue({ rows: [], totalCount: 0 });
    renderModal();

    await user.click(screen.getByRole('button', { name: /close/i }));

    expect(onClose).toHaveBeenCalledOnce();
  });

  test('renders nothing when tag is null', () => {
    vi.mocked(fetchTagUsage).mockResolvedValue({ rows: [], totalCount: 0 });
    const { container } = render(<TagUsageModal isOpen tag={null} onClose={onClose} />);
    expect(container).toBeEmptyDOMElement();
  });
});
