import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vitest/config'

export default defineConfig({
	plugins: [vue()],
	test: {
		css: false,
		environment: 'jsdom',
		include: ['tests/js/**/*.spec.ts'],
		server: {
			deps: {
				inline: ['@nextcloud/vue'],
			},
		},
	},
})
