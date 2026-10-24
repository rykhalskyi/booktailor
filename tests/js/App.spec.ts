import { flushPromises, shallowMount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

vi.mock('../../src/api/client', () => {
	class ApiError extends Error {
		constructor(message: string, public readonly status: number) {
			super(message)
			this.name = 'ApiError'
		}
	}

	return {
		ApiError,
		api: {
			books: {
				list: vi.fn().mockResolvedValue([]),
				create: vi.fn(),
			},
			structures: {
				list: vi.fn().mockResolvedValue([]),
			},
			chapters: {
				tree: vi.fn().mockResolvedValue([]),
				create: vi.fn(),
				rename: vi.fn(),
				remove: vi.fn(),
			},
			assets: {
				list: vi.fn().mockResolvedValue([]),
				create: vi.fn(),
				upload: vi.fn(),
				remove: vi.fn(),
			},
		},
	}
})

import App from '../../src/App.vue'

describe('App', () => {
	it('shows the empty books state', async () => {
		const wrapper = shallowMount(App, {
			global: {
				stubs: {
					NcContent: { template: '<div><slot /></div>' },
					NcAppNavigation: { template: '<nav><slot /></nav>' },
					NcAppNavigationNew: true,
					NcAppNavigationList: { template: '<ul><slot /></ul>' },
					NcAppNavigationItem: true,
					NcAppContent: { template: '<main><slot /></main>' },
					NcAppNavigationCaption: true,
					NcEmptyContent: {
						props: ['name', 'description'],
						template: '<div><h2>{{ name }}</h2><p>{{ description }}</p><slot name="action" /></div>',
					},
					NcNoteCard: true,
					NcButton: true,
					NcLoadingIcon: true,
					LibraryToolbar: true,
					ChapterTree: true,
					AssetList: true,
					DialogHost: true,
				},
			},
		})

		await flushPromises()

		expect(wrapper.text()).toContain('No books yet')
		expect(wrapper.text()).toContain('Create a book to start organizing')
	})
})
