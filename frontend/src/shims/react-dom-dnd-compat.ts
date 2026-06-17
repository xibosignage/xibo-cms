// React 19 dropped unstable_batchedUpdates from the main react-dom entry point.
// @dnd-kit/core v6 still imports it via ESM. Since React 19 batches all state
// updates automatically, a no-op replacement is correct.
// This shim is injected only for @dnd-kit imports by a Vite plugin.

// Re-export the real createPortal so @dnd-kit/core gets it too.
export { createPortal } from 'react-dom';

export const unstable_batchedUpdates = (fn: () => void): void => fn();
