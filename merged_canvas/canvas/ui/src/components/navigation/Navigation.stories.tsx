import Panel from '@/components/Panel';

import Navigation from './Navigation';

import type { Meta, StoryObj } from '@storybook/react';
import type { ContentStub } from '@/types/Content';

const meta: Meta<typeof Navigation> = {
  title: 'Components/Navigation',
  component: Navigation,
  decorators: [(story) => <Panel p="4">{story()}</Panel>],
};
export default meta;

type Story = StoryObj<typeof Navigation>;

const items: ContentStub[] = [
  {
    title: 'Alpha',
    path: '/alpha',
    internalPath: '/page/1',
    id: 1,
    status: true,
    autoSaveLabel: '',
    autoSavePath: '',
    links: {
      'delete-form': '/canvas/api/v0/content/canvas_page/1',
      'edit-form': '/canvas/editor/canvas_page/1',
      'https://drupal.org/project/canvas#link-rel-duplicate':
        '/canvas/api/v0/content/canvas_page',
      'https://drupal.org/project/canvas#link-rel-set-as-homepage':
        '/canvas/api/v0/content/canvas_page',
    },
  },
  {
    title: 'Bravo',
    path: '/bravo',
    internalPath: '/page/2',
    id: 2,
    status: true,
    autoSaveLabel: '',
    autoSavePath: '',
    links: {
      'delete-form': '/canvas/api/v0/content/canvas_page/2',
      'edit-form': '/canvas/editor/canvas_page/2',
      'https://drupal.org/project/canvas#link-rel-duplicate':
        '/canvas/api/v0/content/canvas_page',
      'https://drupal.org/project/canvas#link-rel-set-as-homepage':
        '/canvas/api/v0/content/canvas_page',
    },
  },
  {
    title: 'Charlie',
    path: '/charlie',
    internalPath: '/page/3',
    id: 3,
    status: false,
    autoSaveLabel: '',
    autoSavePath: '',
    links: {
      'delete-form': '/canvas/api/v0/content/canvas_page/3',
      'edit-form': '/canvas/editor/canvas_page/3',
      'https://drupal.org/project/canvas#link-rel-duplicate':
        '/canvas/api/v0/content/canvas_page',
      'https://drupal.org/project/canvas#link-rel-set-as-homepage':
        '/canvas/api/v0/content/canvas_page',
    },
  },
  {
    title: 'Delta',
    path: '/delta',
    internalPath: '/page/4',
    id: 4,
    status: false,
    autoSaveLabel: '',
    autoSavePath: '',
    links: {
      'delete-form': '/canvas/api/v0/content/canvas_page/4',
      'edit-form': '/canvas/editor/canvas_page/4',
      'https://drupal.org/project/canvas#link-rel-duplicate':
        '/canvas/api/v0/content/canvas_page',
      'https://drupal.org/project/canvas#link-rel-set-as-homepage':
        '/canvas/api/v0/content/canvas_page',
    },
  },
  {
    title: 'Echo',
    path: '/echo',
    internalPath: '/page/5',
    id: 5,
    status: true,
    autoSaveLabel: '',
    autoSavePath: '',
    links: {
      'delete-form': '/canvas/api/v0/content/canvas_page/5',
      'edit-form': '/canvas/editor/canvas_page/5',
      'https://drupal.org/project/canvas#link-rel-duplicate':
        '/canvas/api/v0/content/canvas_page',
      'https://drupal.org/project/canvas#link-rel-set-as-homepage':
        '/canvas/api/v0/content/canvas_page',
    },
  },
  {
    title: 'Foxtrot',
    path: '/foxtrot',
    internalPath: '/page/6',
    id: 6,
    status: true,
    autoSaveLabel: '',
    autoSavePath: '',
    links: {
      'delete-form': '/canvas/api/v0/content/canvas_page/6',
      'edit-form': '/canvas/editor/canvas_page/6',
      'https://drupal.org/project/canvas#link-rel-duplicate':
        '/canvas/api/v0/content/canvas_page',
      'https://drupal.org/project/canvas#link-rel-set-as-homepage':
        '/canvas/api/v0/content/canvas_page',
    },
  },
  {
    title: 'Golf',
    path: '/golf',
    internalPath: '/page/7',
    id: 7,
    status: false,
    autoSaveLabel: '',
    autoSavePath: '',
    links: {
      'delete-form': '/canvas/api/v0/content/canvas_page/7',
      'edit-form': '/canvas/editor/canvas_page/7',
      'https://drupal.org/project/canvas#link-rel-duplicate':
        '/canvas/api/v0/content/canvas_page',
      'https://drupal.org/project/canvas#link-rel-set-as-homepage':
        '/canvas/api/v0/content/canvas_page',
    },
  },
  {
    title: 'Hotel',
    path: '/hotel',
    internalPath: '/page/8',
    id: 8,
    status: true,
    autoSaveLabel: '',
    autoSavePath: '',
    links: {
      'delete-form': '/canvas/api/v0/content/canvas_page/8',
      'edit-form': '/canvas/editor/canvas_page/8',
      'https://drupal.org/project/canvas#link-rel-duplicate':
        '/canvas/api/v0/content/canvas_page',
      'https://drupal.org/project/canvas#link-rel-set-as-homepage':
        '/canvas/api/v0/content/canvas_page',
    },
  },
  {
    title: 'India',
    path: '/india',
    internalPath: '/page/9',
    id: 9,
    status: false,
    autoSaveLabel: '',
    autoSavePath: '',
    links: {
      'delete-form': '/canvas/api/v0/content/canvas_page/9',
      'edit-form': '/canvas/editor/canvas_page/9',
      'https://drupal.org/project/canvas#link-rel-duplicate':
        '/canvas/api/v0/content/canvas_page',
      'https://drupal.org/project/canvas#link-rel-set-as-homepage':
        '/canvas/api/v0/content/canvas_page',
    },
  },
  {
    title: 'Juliet',
    path: '/juliet',
    internalPath: '/page/10',
    id: 10,
    status: true,
    autoSaveLabel: '',
    autoSavePath: '',
    links: {
      'delete-form': '/canvas/api/v0/content/canvas_page/10',
      'edit-form': '/canvas/editor/canvas_page/10',
      'https://drupal.org/project/canvas#link-rel-duplicate':
        '/canvas/api/v0/content/canvas_page',
      'https://drupal.org/project/canvas#link-rel-set-as-homepage':
        '/canvas/api/v0/content/canvas_page',
    },
  },
];

export const Default: Story = {
  args: {
    loading: false,
    showNew: true,
    items,
    onNewPage: () => console.log('Creating new page'),
    onSearch: (query: string) => console.log('Searching for', query),
    onSelect: (value: ContentStub) => console.log('Selected', value),
    onDuplicate: (page: ContentStub) => console.log('Duplicated', page),
    onSetHomepage: (page: ContentStub) => console.log('Set as homepage', page),
    onDelete: (page: ContentStub) => console.log('Deleted', page),
  },
};

export const Loading: Story = {
  args: {
    loading: true,
    items: [],
  },
};

export const NoItems: Story = {
  args: {
    loading: false,
    items: [],
  },
};

export const NoNewDropDown: Story = {
  args: {
    loading: false,
    showNew: false,
  },
};
