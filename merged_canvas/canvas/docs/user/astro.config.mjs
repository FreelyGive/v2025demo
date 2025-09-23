// @ts-check
import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';

// https://astro.build/config
export default defineConfig({
  trailingSlash: 'never',
  integrations: [
    starlight({
      title: 'Drupal Canvas',
      social: [
        {
          icon: 'gitlab',
          label: 'GitLab',
          href: 'https://git.drupalcode.org/project/canvas/',
        },
        {
          icon: 'slack',
          label: 'Slack',
          href: 'https://drupal.slack.com/archives/C072JMEPUS1',
        },
        {
          icon: 'bun',
          label: 'drupal.org',
          href: 'https://www.drupal.org/project/canvas',
        },
      ],
      sidebar: [
        {
          label: 'Code components',
          items: [
            { label: 'Introduction', slug: 'code-components' },
            { label: 'Props', slug: 'code-components/props' },
            { label: 'Slots', slug: 'code-components/slots' },
            { label: 'Using packages', slug: 'code-components/packages' },
            { label: 'Data fetching', slug: 'code-components/data-fetching' },
            {
              label: 'Responsive images',
              slug: 'code-components/responsive-images',
            },
            {
              label: 'CLI tool',
              slug: 'code-components/cli-tool',
            },
          ],
        },
        {
          label: 'SDC components',
          items: [
            { label: 'Introduction', slug: 'sdc-components' },
            { label: 'Props', slug: 'sdc-components/props' },
            { label: 'Slots', slug: 'sdc-components/slots' },
            { label: 'Image', slug: 'sdc-components/image' },
            {
              label: 'Validations',
              slug: 'sdc-components/validations',
            },
            { label: 'Troubleshooting', slug: 'sdc-components/troubleshooting' },
          ],
        },
        {
          label: 'AI assistant',
          items: [
            { label: 'Introduction', slug: 'ai-assistant' }
          ],
        },
        {
          label: 'APIs',
          items: [
            { label: 'Introduction', slug: 'apis' },
            { label: 'Customizing forms', slug: 'apis/customizing-forms' },
          ],
        }
      ],
    }),
  ],
  base: process.env.ASTRO_BASE || undefined,
  site: process.env.ASTRO_SITE || undefined,
});
