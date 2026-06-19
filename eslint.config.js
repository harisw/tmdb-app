import pluginVue from 'eslint-plugin-vue'
import prettier from 'eslint-config-prettier'

export default [
    ...pluginVue.configs['flat/recommended'],
    {
        rules: {
            'no-console': 'warn',
            'vue/multi-word-component-names': 'off',
            'vue/no-unused-vars': 'error',
            'vue/require-default-prop': 'off',
        },
    },
    prettier,
]
