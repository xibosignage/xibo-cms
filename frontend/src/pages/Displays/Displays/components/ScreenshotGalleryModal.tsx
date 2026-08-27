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

import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import DisplayScreenshotPreviewer from './DisplayScreenshotPreviewer';
import ScreenshotGallery from './ScreenshotGallery';

import Modal from '@/components/ui/modals/Modal';
import type { Display } from '@/types/display';
import type { DisplayScreenshot } from '@/types/displayManage';

interface ScreenshotGalleryModalProps {
  display: Display | null;
  onClose: () => void;
}

/**
 * Standalone entry point into a display's screenshot history — the gallery from the Manage
 * modal's Proof of Play tab, opened directly as its own modal rather than behind a tab. Used by
 * the Displays Overview page's "Screenshots" quick action, where there's no surrounding Manage
 * modal to host a tab.
 */
// PARKED (screenshot history & interval): nothing renders this any more. See the note in
// ScreenshotGallery.tsx.
export default function ScreenshotGalleryModal({ display, onClose }: ScreenshotGalleryModalProps) {
  const { t } = useTranslation();
  const [previewScreenshot, setPreviewScreenshot] = useState<DisplayScreenshot | null>(null);

  if (!display) {
    return null;
  }

  const handleClose = () => {
    setPreviewScreenshot(null);
    onClose();
  };

  return (
    <>
      <Modal
        isOpen
        onClose={handleClose}
        title={t('Screenshots: {{name}}', { name: display.display })}
        showCloseButton
        size="xl"
        actions={[{ label: t('Close'), onClick: handleClose, variant: 'secondary' }]}
      >
        <ScreenshotGallery display={display} onSelect={setPreviewScreenshot} />
      </Modal>

      {/* Opened over the gallery modal, with the history id telling it which image to load. */}
      {previewScreenshot && (
        <DisplayScreenshotPreviewer
          display={display}
          screenshotId={previewScreenshot.displayScreenshotId}
          onClose={() => setPreviewScreenshot(null)}
        />
      )}
    </>
  );
}
