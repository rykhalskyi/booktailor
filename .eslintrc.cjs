module.exports = {
	extends: [
		'@nextcloud',
	],
	settings: {
		'import/resolver': {
			node: {
				extensions: ['.js', '.mjs', '.ts', '.vue'],
			},
		},
	},
	rules: {
		'jsdoc/require-jsdoc': 'off',
		'vue/first-attribute-linebreak': 'off',
		'padded-blocks': 'off',
		'import/extensions': ['error', 'ignorePackages', {
			js: 'never',
			mjs: 'never',
			ts: 'never',
			vue: 'always',
		}],
	},
	overrides: [
		{
			files: ['**/*.vue'],
			parser: 'vue-eslint-parser',
			parserOptions: {
				parser: '@typescript-eslint/parser',
				ecmaVersion: 'latest',
				sourceType: 'module',
				extraFileExtensions: ['.vue'],
			},
			rules: {
				'n/no-missing-import': 'off',
				'func-call-spacing': 'off',
				'@typescript-eslint/func-call-spacing': 'error',
			},
		},
	],
}
