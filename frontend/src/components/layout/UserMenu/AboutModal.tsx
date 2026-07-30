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

import { AboutModalContent } from '@/components/about/AboutModalContent';
import Modal from '@/components/ui/modals/Modal';

interface AboutModalProps {
  isOpen?: boolean;
  onClose: () => void;
}

export default function AboutModal({ isOpen = true, onClose }: AboutModalProps) {
  const { t } = useTranslation();

  return (
    <Modal
      variant="confirmation"
      isOpen={isOpen}
      onClose={onClose}
      title={t('About')}
      size="lg"
      scrollable={true}
      actions={[{ label: t('Close'), variant: 'secondary', onClick: onClose }]}
    >
      <div className="p-6 text-gray-700 space-y-6">
        <AboutModalContent />
      </div>
    </Modal>
  );
}
