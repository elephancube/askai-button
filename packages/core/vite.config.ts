import { defineConfig } from 'vitest/config';

export default defineConfig({
  build: {
    target: 'es2019',
    lib: {
      entry: 'src/index.ts',
      name: 'AskAIButton',
      formats: ['iife', 'es'],
      fileName: (format) => (format === 'iife' ? 'askai-button.min.js' : 'askai-button.esm.js'),
    },
  },
  test: {
    include: ['test/**/*.test.ts'],
    css: true,
  },
});
