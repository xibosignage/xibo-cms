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

// Lets a tab that just logged out tell sibling tabs of the same origin to stop acting
// authenticated immediately, instead of waiting for their own next poll/focus check. This is a
// UX/collision-frequency improvement only - the server-side session fix is what actually
// prevents a sibling tab's request from resurrecting the logged-out session.
const CHANNEL_NAME = 'xibo-auth';

let channel: BroadcastChannel | null = null;

function getChannel(): BroadcastChannel | null {
  if (typeof BroadcastChannel === 'undefined') {
    return null;
  }

  if (!channel) {
    channel = new BroadcastChannel(CHANNEL_NAME);
  }

  return channel;
}

export function broadcastLogout(): void {
  getChannel()?.postMessage({ type: 'logout' });
}

export function listenForLogout(onLogout: () => void): () => void {
  const ch = getChannel();

  if (!ch) {
    return () => {};
  }

  const handler = (event: MessageEvent) => {
    if (event.data?.type === 'logout') {
      onLogout();
    }
  };

  ch.addEventListener('message', handler);

  return () => ch.removeEventListener('message', handler);
}
