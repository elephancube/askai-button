import { defineConfig } from 'vitest/config';

export default defineConfig({
  // Keeps `/*! ... */` comments, which the minifier would otherwise drop.
  esbuild: { legalComments: 'inline' },
  build: {
    target: 'es2019',
    lib: {
      entry: 'src/index.ts',
      name: 'AskAIButton',
      formats: ['iife', 'es'],
      fileName: (format) => (format === 'iife' ? 'askai-button.min.js' : 'askai-button.esm.js'),
    },
    rollupOptions: {
      output: {
        // Licence notice that stays in the built files, also after minification.
        banner: '/*! askai-button | MIT License | (c) 2026 Elephancube Inc. */',
      },
    },
  },
  test: {
    include: ['test/**/*.test.ts'],
    css: true,
  },
});
