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

import { render } from '@testing-library/react';
import { vi } from 'vitest';

import AddEditTaskModal from '../../../components/AddEditTaskModal';

type AddEditTaskModalProps = React.ComponentProps<typeof AddEditTaskModal>;

// Renders AddEditTaskModal standalone (no page, no router) — the modal has no
// dependency on page-level context.
export const renderAddEditTaskModal = (props: Partial<AddEditTaskModalProps> = {}) => {
  const onClose = props.onClose ?? vi.fn();
  const onSuccess = props.onSuccess ?? vi.fn();

  const utils = render(
    <AddEditTaskModal mode="add" task={null} onClose={onClose} onSuccess={onSuccess} {...props} />,
  );

  return { onClose, onSuccess, ...utils };
};
