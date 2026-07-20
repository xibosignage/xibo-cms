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

import { BookOpen, CircleHelp, PlayCircle, type LucideIcon } from 'lucide-react';

import createContentImg from '@/assets/onboarding/create-content.png';
import displaysImg from '@/assets/onboarding/displays.png';
import existingContentImg from '@/assets/onboarding/existing-content.png';
import playlistsImg from '@/assets/onboarding/playlists.png';
import scheduleImg from '@/assets/onboarding/schedule.png';

export interface VideoLink {
  id: string;
  title: string;
}

export interface ServiceCard {
  serviceId: string;
  titleKey: string;
  descriptionKey: string;
  imageUrl: string;
  featureFlags: string[];
  links: {
    labelKey: string;
    to: string;
    isExternal?: boolean;
    variant?: 'primary' | 'secondary' | 'link';
  }[];
  videoLinks: VideoLink[];
}

export interface ResourceCard {
  titleKey: string;
  descriptionKey: string;
  iconName: string;
  icon: LucideIcon;
  xiboOnly?: boolean;
  links: {
    labelKey: string;
    url: string;
    superAdminOnly?: boolean;
  }[];
}

// Main service/feature cards
export const SERVICE_CARDS: ServiceCard[] = [
  {
    serviceId: 'displays',
    titleKey: 'Displays',
    descriptionKey:
      "Displays are managed from the CMS and control when and how content is shown. Get started by installing one of our Player App's to start scheduling content to show on Displays.",
    imageUrl: displaysImg,
    featureFlags: ['displays.view'],
    links: [
      { labelKey: 'Get Started', to: '/displays/displays', variant: 'primary' },
      {
        labelKey: 'Learn More',
        to: 'https://xibosignage.com/manual/en/displays',
        isExternal: true,
        variant: 'link',
      },
    ],
    videoLinks: [{ id: 'ZFpgArKZgU8', title: 'What is a Display?' }],
  },
  {
    serviceId: 'existing-content',
    titleKey: 'Existing Content',
    descriptionKey:
      'Do you already have an image or video content ready to be shown? Upload your media and schedule to be shown full screen on Displays in a few easy steps.',
    imageUrl: existingContentImg,
    featureFlags: ['library.view', 'layout.view'],
    links: [
      { labelKey: 'Upload Content', to: '/library/media', variant: 'primary' },
      { labelKey: 'Schedule Content', to: '/schedule/events', variant: 'secondary' },
      {
        labelKey: 'Learn More',
        to: 'https://xibosignage.com/manual/en/getting_started_showing_an_image',
        isExternal: true,
        variant: 'link',
      },
    ],
    videoLinks: [
      {
        id: 'YZTiNOGY8JQ',
        title: 'How to show an Image created outside of the CMS on Displays',
      },
      {
        id: 'MLJPmadsjeI',
        title: 'How to show a single video on Displays',
      },
    ],
  },
  {
    serviceId: 'playlists',
    titleKey: 'Playlists',
    descriptionKey:
      "Create a 'slideshow' of content to be shown in sequence to schedule and show on your Displays.",
    imageUrl: playlistsImg,
    featureFlags: ['library.view', 'layout.view'],
    links: [
      { labelKey: 'Get Started', to: '/library/playlists', variant: 'primary' },
      {
        labelKey: 'Learn More',
        to: 'https://xibosignage.com/manual/en/getting_started_showing_a_playlist',
        isExternal: true,
        variant: 'link',
      },
    ],
    videoLinks: [{ id: '2yLj4C1-zhA', title: 'How to create a Playlist and show on Displays' }],
  },
  {
    serviceId: 'create-content',
    titleKey: 'Create Content',
    descriptionKey:
      'Create your own eye catching Layout designs using a variety of tools available in the Layout Editor.',
    imageUrl: createContentImg,
    featureFlags: ['library.view', 'layout.view'],
    links: [
      { labelKey: 'Get Started', to: '/design/layout', variant: 'primary' },
      {
        labelKey: 'Learn More',
        to: 'https://xibosignage.com/manual/en/layouts_editor',
        isExternal: true,
        variant: 'link',
      },
    ],
    videoLinks: [{ id: '4cB0YpLqYtc', title: 'Layout Editor Overview' }],
  },
  {
    serviceId: 'schedule',
    titleKey: 'Schedule',
    descriptionKey:
      'Once you have your content ready to be shown, use the Scheduling tools to ensure that your content is shown at the right place at the right time.',
    imageUrl: scheduleImg,
    featureFlags: ['schedule.view'],
    links: [
      { labelKey: 'Get Started', to: '/schedule/events', variant: 'primary' },
      {
        labelKey: 'Learn More',
        to: 'https://xibosignage.com/manual/en/simple_scheduling',
        isExternal: true,
        variant: 'link',
      },
    ],
    videoLinks: [
      { id: 'Aw-hFcs7mV4', title: 'Creating simple schedules to show content on Displays' },
    ],
  },
];

// Resource/help cards at the bottom
export const RESOURCE_CARDS: ResourceCard[] = [
  {
    titleKey: 'Documentation',
    descriptionKey:
      "Our documentation is there to help you at every turn. It's updated regularly to reflect changes and additions to the platform, and so it's a valuable reference tool for all users.",
    iconName: 'documentation',
    icon: BookOpen,
    links: [
      { labelKey: 'User Manual', url: 'https://xibosignage.com/docs' },
      { labelKey: 'Admin', url: 'https://docs.xibosignage.com/', superAdminOnly: true },
      {
        labelKey: 'Developer',
        url: 'https://docs.xibosignage.com/',
        superAdminOnly: true,
      },
    ],
  },
  {
    titleKey: 'Training',
    descriptionKey: 'A collection of training videos to help new users get started.',
    iconName: 'training',
    icon: PlayCircle,
    xiboOnly: true,
    links: [{ labelKey: 'New User Training', url: 'https://xibosignage.com/training' }],
  },
  {
    titleKey: 'Help',
    descriptionKey: 'We are here to help! All the support you are looking for, at your fingertips.',
    iconName: 'help',
    icon: CircleHelp,
    xiboOnly: true,
    links: [{ labelKey: 'Help Centre', url: 'https://xibosignage.com/install-upgrade#install' }],
  },
];
