import { defineConfig } from 'vitepress'

export default defineConfig({
   title: 'Laravel Error Audit',
   description: 'AI-assisted daily audit of your Laravel log files, delivered by email.',
   base: '/laravel-error-audit/',
   cleanUrls: true,
   lastUpdated: true,

   head: [
      ['meta', { name: 'theme-color', content: '#E11D48' }],
      ['link', { rel: 'icon', type: 'image/svg+xml', href: '/laravel-error-audit/logo.svg' }],
   ],

   themeConfig: {
      logo: '/logo.svg',

      nav: [
         { text: 'Getting Started', link: '/getting-started' },
         { text: 'Configuration', link: '/configuration' },
      ],

      sidebar: [
         {
            text: 'Guide',
            items: [
               { text: 'Getting Started', link: '/getting-started' },
               { text: 'Configuration', link: '/configuration' },
               { text: 'Advanced', link: '/advanced' },
               { text: 'Privacy', link: '/privacy' },
            ],
         },
      ],

      socialLinks: [
         { icon: 'github', link: 'https://github.com/jonaaix/laravel-error-audit' },
      ],

      footer: {
         message: 'Released under the MIT License.',
         copyright: 'Copyright © 2026 Jonas Gnioui',
      },

      search: {
         provider: 'local',
      },
   },
})
