import { fn } from '@storybook/test';

import PageList from './PageList';

import type { Meta, StoryObj } from '@storybook/react';
import type { ContentStub } from '@/types/Content';

const meta: Meta<typeof PageList> = {
  title: 'Components/PageList',
  component: PageList,
  parameters: {
    layout: 'centered',
    docs: {
      description: {
        component:
          'PageList displays a page navigation panel with search, create new button, and a list of pages with management controls.',
      },
    },
  },
  decorators: [
    (Story) => (
      <div
        style={{
          width: '295px',
          backgroundColor: 'white',
          padding: '16px',
          borderRadius: '8px',
          border: '1px solid #e5e5e5',
        }}
      >
        <Story />
      </div>
    ),
  ],
  argTypes: {
    isPageItemsLoading: {
      control: 'boolean',
      description: 'Loading state for page items',
    },
    pageItemsError: {
      control: 'text',
      description: 'Error message for page loading',
    },
    homepagePath: {
      control: 'text',
      description: 'Internal path of the homepage',
    },
    selectedPageId: {
      control: 'text',
      description: 'ID of the currently selected page',
    },
    canCreatePages: {
      control: 'boolean',
      description: 'Whether user can create new pages',
    },
  },
  args: {
    onNewPage: fn(),
    onDeletePage: fn(),
    onDuplicatePage: fn(),
    onSelectPage: fn(),
    onSetHomepage: fn(),
    onSearch: fn(),
  },
};

export default meta;
type Story = StoryObj<typeof PageList>;

// Sample page data
const samplePages: ContentStub[] = [
  {
    id: 1,
    title: 'Homepage',
    path: '/home',
    internalPath: '/home',
    status: true,
    autoSaveLabel: null,
    autoSavePath: '/home',
    links: {
      'edit-form': '/edit/1',
      'delete-form': '/delete/1',
      'https://drupal.org/project/canvas#link-rel-duplicate': '/duplicate/1',
      'https://drupal.org/project/canvas#link-rel-set-as-homepage':
        '/homepage/1',
    },
  },
  {
    id: 2,
    title: 'About Us',
    path: '/about',
    internalPath: '/about',
    status: true,
    autoSaveLabel: null,
    autoSavePath: '/about',
    links: {
      'edit-form': '/edit/2',
      'delete-form': '/delete/2',
      'https://drupal.org/project/canvas#link-rel-duplicate': '/duplicate/2',
      'https://drupal.org/project/canvas#link-rel-set-as-homepage':
        '/homepage/2',
    },
  },
  {
    id: 3,
    title: 'Contact',
    path: '/contact',
    internalPath: '/contact',
    status: true,
    autoSaveLabel: null,
    autoSavePath: '/contact',
    links: {
      'edit-form': '/edit/3',
      'https://drupal.org/project/canvas#link-rel-duplicate': '/duplicate/3',
    },
  },
  {
    id: 4,
    title: 'Services',
    path: '/services',
    internalPath: '/services',
    status: true,
    autoSaveLabel: null,
    autoSavePath: '/services',
    links: {
      'edit-form': '/edit/4',
    },
  },
];

export const Default: Story = {
  args: {
    pageItems: samplePages,
    homepagePath: '/home',
    canCreatePages: true,
  },
};

export const WithSelectedPage: Story = {
  args: {
    pageItems: samplePages,
    homepagePath: '/home',
    selectedPageId: 2, // Select the "About Us" page
    canCreatePages: true,
  },
};

export const LoadingState: Story = {
  args: {
    pageItems: [],
    isPageItemsLoading: true,
    homepagePath: '/home',
    canCreatePages: true,
  },
};

export const ErrorState: Story = {
  args: {
    pageItems: [],
    pageItemsError: 'Failed to load pages. Please try again.',
    homepagePath: '/home',
    canCreatePages: true,
  },
};

export const EmptyPageList: Story = {
  args: {
    pageItems: [],
    homepagePath: '/home',
    canCreatePages: true,
  },
};

export const NoCreatePermission: Story = {
  args: {
    pageItems: samplePages,
    homepagePath: '/home',
    canCreatePages: false,
  },
};

export const LimitedPermissions: Story = {
  args: {
    pageItems: [
      {
        id: 1,
        title: 'Read Only Page',
        path: '/readonly',
        internalPath: '/readonly',
        status: true,
        autoSaveLabel: null,
        autoSavePath: '/readonly',
        links: {}, // No permissions
      },
      {
        id: 2,
        title: 'Edit Only Page',
        path: '/edit-only',
        internalPath: '/edit-only',
        status: true,
        autoSaveLabel: null,
        autoSavePath: '/edit-only',
        links: {
          'edit-form': '/edit/2',
        },
      },
    ],
    homepagePath: '/home',
    canCreatePages: false,
  },
};

export const LongPageList: Story = {
  args: {
    pageItems: [
      ...samplePages,
      {
        id: 5,
        title: 'Blog',
        path: '/blog',
        internalPath: '/blog',
        status: true,
        autoSaveLabel: null,
        autoSavePath: '/blog',
        links: { 'edit-form': '/edit/5' },
      },
      {
        id: 6,
        title: 'Portfolio',
        path: '/portfolio',
        internalPath: '/portfolio',
        status: true,
        autoSaveLabel: null,
        autoSavePath: '/portfolio',
        links: { 'edit-form': '/edit/6' },
      },
      {
        id: 7,
        title: 'News',
        path: '/news',
        internalPath: '/news',
        status: true,
        autoSaveLabel: null,
        autoSavePath: '/news',
        links: { 'edit-form': '/edit/7' },
      },
      {
        id: 8,
        title: 'Events',
        path: '/events',
        internalPath: '/events',
        status: true,
        autoSaveLabel: null,
        autoSavePath: '/events',
        links: { 'edit-form': '/edit/8' },
      },
    ],
    homepagePath: '/home',
    canCreatePages: true,
  },
};

export const WithAutoSaveLabels: Story = {
  args: {
    pageItems: [
      {
        id: 1,
        title: 'Homepage',
        path: '/home',
        internalPath: '/home',
        status: true,
        autoSaveLabel: 'Homepage (draft)',
        autoSavePath: '/home-draft',
        links: {
          'edit-form': '/edit/1',
          'delete-form': '/delete/1',
        },
      },
      {
        id: 2,
        title: 'About Us',
        path: '/about',
        internalPath: '/about',
        status: true,
        autoSaveLabel: null,
        autoSavePath: '/about',
        links: {
          'edit-form': '/edit/2',
        },
      },
    ],
    homepagePath: '/home',
    canCreatePages: true,
  },
};
