import js from '@eslint/js'
import pluginVue from 'eslint-plugin-vue'
import globals from 'globals'

export default [
    {
        ignores: ['node_modules/**', 'public/build/**', 'vendor/**'],
    },
    js.configs.recommended,
    ...pluginVue.configs['flat/essential'],
    {
        files: ['resources/js/**/*.{js,vue}', 'tests/js/**/*.js', '*.config.js'],
        languageOptions: {
            globals: {
                ...globals.browser,
                ...globals.node,
            },
        },
        rules: {
            'no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
            'vue/html-self-closing': 'off',
            'vue/multi-word-component-names': 'off',
            'vue/no-v-text-v-html-on-component': 'off',
            'vue/require-default-prop': 'off',
        },
    },
]
