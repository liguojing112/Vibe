export default {
  extends: [
    'stylelint-config-standard-scss',
    'stylelint-config-standard-vue/scss',
    'stylelint-config-recess-order',
    '@stylistic/stylelint-config',
  ],
  plugins: [
    'stylelint-scss',
  ],
  rules: {
    'at-rule-no-unknown': null,
    'no-descending-specificity': null,
    'property-no-unknown': null,
    'font-family-no-missing-generic-family-keyword': null,
    'selector-class-pattern': null,
    'no-empty-source': null,
    'scss/double-slash-comment-empty-line-before': null,
    'scss/no-global-function-names': null,
    // @apply / @screen 等是 UnoCSS、Tailwind 的合法 at-rule，stylelint 默认不认识
    'scss/at-rule-no-unknown': [true, {
      ignoreAtRules: ['apply', 'screen', 'tailwind', 'variants', 'responsive', 'layer'],
    }],
    '@stylistic/max-line-length': null,
    '@stylistic/block-closing-brace-newline-after': [
      'always',
      {
        ignoreAtRules: ['if', 'else'],
      },
    ],
  },
  // MineAdmin 自带样式存在大量历史格式问题，这里只对上游目录放宽；
  // 我们自己的 src/modules/cl/** 不在此列表内，保持严格（见 PROGRESS Q0-11）。
  overrides: [
    {
      files: [
        'src/components/**/*.{vue,css,scss}',
        'src/layouts/**/*.{vue,css,scss}',
        'src/plugins/**/*.{vue,css,scss}',
        'src/assets/**/*.{vue,css,scss}',
        'src/modules/base/**/*.{vue,css,scss}',
      ],
      rules: {
        'declaration-block-single-line-max-declarations': null,
        'no-duplicate-selectors': null,
        'scss/operator-no-unspaced': null,
        'keyframes-name-pattern': null,
        'selector-type-no-unknown': null,
        'scss/at-rule-no-unknown': null,
      },
    },
  ],
  allowEmptyInput: true,
  ignoreFiles: [
    'node_modules/**/*',
    'dist*/**/*',
  ],
}
