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

import '@/testUtils/notifyMock';

import { renderHook, act } from '@testing-library/react';
import type { TFunction } from 'i18next';
import { describe, test, expect, vi, beforeEach } from 'vitest';

import { useMediaActions } from '../../hooks/useMediaActions';
import { mockEditMedia } from '../mediaTestUtils';

import { cloneMedia, setMediaEnableStat } from '@/services/mediaApi';
import { knownFailure } from '@/testUtils/knownFailure';
import { mockNotifyError, mockNotifySuccess } from '@/testUtils/notifyMock';
import type { Media } from '@/types/media';
import type { Tag } from '@/types/tag';

// The requests behind the Copy and Enable Stats modals. Companion to
// useMediaActions.test.tsx, which covers Move.

vi.mock('@/services/mediaApi', () => ({
  cloneMedia: vi.fn(),
  deleteMedia: vi.fn(),
  setMediaEnableStat: vi.fn(),
  tidyLibrary: vi.fn(),
  updateMedia: vi.fn(),
}));
vi.mock('@/services/folderApi', () => ({ selectFolder: vi.fn() }));

const MEDIA: Media = { ...mockEditMedia, mediaId: 42 };

const mockT = ((str: string) => str) as unknown as TFunction;
const handleRefresh = vi.fn();
const closeModal = vi.fn();

const renderActions = () =>
  renderHook(() =>
    useMediaActions({
      t: mockT,
      handleRefresh,
      closeModal,
      setRowSelection: vi.fn(),
      setItemsToMove: vi.fn(),
      setItemsToDelete: vi.fn(),
    }),
  );

describe('useMediaActions — copy and stats collection', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.spyOn(console, 'error').mockImplementation(() => {});
  });

  describe('copy', () => {
    test('copies the chosen media under the new name with its tags, values included', async () => {
      vi.mocked(cloneMedia).mockResolvedValue(MEDIA);
      const { result } = renderActions();

      await act(() =>
        result.current.handleConfirmClone(MEDIA, 'Welcome (1)', [
          { tag: 'nature', value: 'forest', tagId: 1 },
          { tag: 'lobby', value: '', tagId: 0 },
        ]),
      );

      expect(cloneMedia).toHaveBeenCalledWith({
        mediaId: 42,
        name: 'Welcome (1)',
        tags: 'nature|forest,lobby',
      });
      expect(mockNotifySuccess).toHaveBeenCalledWith('Media copied successfully');
      expect(handleRefresh).toHaveBeenCalledOnce();
      expect(closeModal).toHaveBeenCalledOnce();
    });

    test('a numeric tag value is kept', async () => {
      vi.mocked(cloneMedia).mockResolvedValue(MEDIA);
      const { result } = renderActions();

      await act(() =>
        result.current.handleConfirmClone(MEDIA, 'Copy', [{ tag: 'floor', value: 0, tagId: 3 }]),
      );

      expect(vi.mocked(cloneMedia).mock.calls[0]![0].tags).toBe('floor|0');
    });

    test('a failed copy tells the user and keeps the modal open', async () => {
      vi.mocked(cloneMedia).mockRejectedValue(new Error('boom'));
      const { result } = renderActions();

      await act(() => result.current.handleConfirmClone(MEDIA, 'Copy', []));

      expect(mockNotifyError).toHaveBeenCalledWith('Failed to copy media');
      expect(closeModal).not.toHaveBeenCalled();
      expect(result.current.isCloning).toBe(false);
    });

    test('with no media chosen nothing is sent', async () => {
      const { result } = renderActions();

      await act(() => result.current.handleConfirmClone(null, 'Copy', []));

      expect(cloneMedia).not.toHaveBeenCalled();
    });

    // Open — xibosignage/xibo#4057.
    knownFailure(
      'copying media whose tag has no value does not give the copy a tag value of "null"',
      async () => {
        vi.mocked(cloneMedia).mockResolvedValue(MEDIA);
        const { result } = renderActions();
        const tagFromApi = { tag: 'nature', value: null, tagId: 1 } as unknown as Tag;
        await act(() => result.current.handleConfirmClone(MEDIA, 'Copy', [tagFromApi]));
        return vi.mocked(cloneMedia).mock.calls[0]![0].tags;
      },
      (tags) => expect(tags).toBe('nature'),
    );
  });

  describe('stats collection', () => {
    test('sends the chosen setting for that media item', async () => {
      vi.mocked(setMediaEnableStat).mockResolvedValue(undefined);
      const { result } = renderActions();

      await act(() => result.current.handleConfirmEnableStats(MEDIA, 'Off'));

      expect(setMediaEnableStat).toHaveBeenCalledWith(42, 'Off');
      expect(mockNotifySuccess).toHaveBeenCalledWith('Stats collection updated');
      expect(handleRefresh).toHaveBeenCalledOnce();
      expect(closeModal).toHaveBeenCalledOnce();
    });

    test('a failed update tells the user and keeps the modal open', async () => {
      vi.mocked(setMediaEnableStat).mockRejectedValue(new Error('boom'));
      const { result } = renderActions();

      await act(() => result.current.handleConfirmEnableStats(MEDIA, 'On'));

      expect(mockNotifyError).toHaveBeenCalledWith('Failed to update stats collection');
      expect(closeModal).not.toHaveBeenCalled();
      expect(result.current.isUpdatingStats).toBe(false);
    });
  });
});
