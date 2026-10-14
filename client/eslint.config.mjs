import js from '@eslint/js';
import a11y from 'eslint-plugin-jsx-a11y';
import hooks from 'eslint-plugin-react-hooks';
import importSort from 'eslint-plugin-simple-import-sort';
import tseslint from 'typescript-eslint';

const applicationImportMessage =
  'Use @/ for application modules; relative paths are for test support and assets.';
const sourceRelative = '^\\.\\.?/(?!.*\\.(?:s?css|svg|png|jpg|webp)$)';
const sourceTraversal = '^\\.\\.?/(?!.*\\.(?:s?css|svg|png|jpg|webp)$)(?:.*/)?src(?:/|$)';
const restrictApplicationImports = (pattern) => ({
  'no-restricted-imports': [
    'error',
    { patterns: [{ regex: pattern, message: applicationImportMessage }] }
  ],
  // no-restricted-imports covers static imports/re-exports, not lazy loads or mock identifiers.
  'no-restricted-syntax': [
    'error',
    ...[
      'ImportExpression > Literal.source',
      'CallExpression[callee.object.name="vi"][callee.property.name=/^(mock|doMock|unmock|doUnmock|importActual|importMock)$/] > Literal.arguments'
    ].map((selector) => ({
      selector: `${selector}[value=/${pattern.replaceAll('/', '\\/')}/]`,
      message: applicationImportMessage
    }))
  ]
});

export default tseslint.config(
  { ignores: ['node_modules/**'] },
  js.configs.recommended,
  {
    files: ['**/*.{ts,tsx,mjs}'],
    plugins: { 'simple-import-sort': importSort },
    rules: {
      'simple-import-sort/imports': [
        'error',
        {
          groups: [
            ['^\\u0000'], // Side effects retain their original order, including stylesheets.
            ['^react(?:[/-]|$)'],
            ['^(?:node:|@?\\w)'],
            ['^@/'],
            ['^\\.'],
            ['^.*\\u0000$'] // Explicit type-only imports follow all runtime groups.
          ]
        }
      ],
      'simple-import-sort/exports': 'error'
    }
  },
  {
    files: ['**/*.{ts,tsx}'],
    extends: [tseslint.configs.recommendedTypeChecked],
    languageOptions: {
      parserOptions: {
        project: ['./tsconfig.json', './tsconfig.catalog.json'],
        tsconfigRootDir: import.meta.dirname
      }
    },
    plugins: { 'react-hooks': hooks, 'jsx-a11y': a11y },
    rules: {
      ...hooks.configs.recommended.rules,
      ...a11y.configs.recommended.rules,
      '@typescript-eslint/consistent-type-imports': ['error', { fixStyle: 'separate-type-imports' }]
    }
  },
  { files: ['src/**/*.{ts,tsx}'], rules: restrictApplicationImports(sourceRelative) },
  {
    files: ['tests/**/*.{ts,tsx}', 'stories/**/*.tsx'],
    rules: restrictApplicationImports(sourceTraversal)
  },
  {
    files: ['scripts/*.mjs'],
    languageOptions: { globals: { console: 'readonly' } }
  }
);
