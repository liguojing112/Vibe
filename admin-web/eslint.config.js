import antfu from '@antfu/eslint-config'

export default antfu(
  {
    unocss: true,
    ignores: [
      'public',
      'dist*',
      '**/*.md',
    ],
  },
  {
    rules: {
      'no-undefined': 'off',
      'vue/custom-event-name-casing': 'off',
      'vue/no-unused-refs': 'off',
      'perfectionist/sort-imports': 'off',
      'vue/component-definition-name-casing': 'off',
      'eslint-comments/no-unlimited-disable': 'off',
      'curly': ['error', 'all'],
      'antfu/consistent-list-newline': 'off',
      'ts/no-unused-expressions': 'off',
      'no-console': 'off',
      'vue/require-v-for-key': 'off',
      'array-callback-return': 'off',
    },
  },
  {
    files: [
      'src/**/*.vue',
      'src/**/*.tsx',
    ],
    rules: {
      'vue/block-order': ['error', {
        order: ['route', 'i18n', 'script', 'template', 'style'],
      }],
    },
  },
  // ---- MineAdmin 自带代码存在历史 lint 错误，这里对上游目录放宽这两条规则；
  //      我们自己的 src/modules/cl/** 在下一个块里重新收紧（见 PROGRESS Q0-11）。----
  {
    files: ['src/**/*.{js,jsx,ts,tsx,vue}'],
    rules: {
      'unused-imports/no-unused-vars': 'warn',
      'prefer-promise-reject-errors': 'warn',
    },
  },
  {
    files: ['src/modules/cl/**/*.{js,jsx,ts,tsx,vue}'],
    rules: {
      'unused-imports/no-unused-vars': 'error',
      'prefer-promise-reject-errors': 'error',
    },
  },
)
