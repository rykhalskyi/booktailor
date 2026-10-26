import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import VersionHistoryDialog from '../../src/components/VersionHistoryDialog.vue'
import { useLibrary } from '../../src/stores/useLibrary'
import type { Asset, Version } from '../../src/types'

const NcButtonStub = {
	props: ['variant', 'disabled'],
	emits: ['click'],
	template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
}

const NcTextFieldStub = {
	props: ['modelValue', 'label', 'disabled'],
	emits: ['update:modelValue'],
	template: '<input :value="modelValue" :disabled="disabled" @input="$emit(\'update:modelValue\', $event.target.value)">',
}

const NcDialogStub = {
	props: ['open', 'name', 'size'],
	emits: ['closing'],
	template: '<div><slot /><slot name="actions" /></div>',
}

function asset(): Asset {
	return {
		id: 10,
		bookId: 1,
		chapterId: 1,
		name: 'Notes',
		fileName: 'Notes.md',
		fileId: 20,
		path: 'BookTailor/Book/Chapter/Notes.md',
		tags: [],
		createdAt: '2026-01-01T00:00:00+00:00',
		updatedAt: '2026-01-01T00:00:00+00:00',
	}
}

function version(revisionId: string, isCurrent: boolean, message: string | null = null): Version {
	return {
		revisionId,
		author: 'Ada',
		message,
		size: 42,
		createdAt: '2026-01-01T00:00:00+00:00',
		isCurrent,
	}
}

function mountDialog() {
	return mount(VersionHistoryDialog, {
		global: {
			stubs: {
				NcButton: NcButtonStub,
				NcDialog: NcDialogStub,
				NcLoadingIcon: true,
				NcNoteCard: true,
				NcTextField: NcTextFieldStub,
			},
		},
	})
}

function buttonByText(wrapper: ReturnType<typeof mountDialog>, text: string) {
	const button = wrapper.findAll('button').find((item) => item.text().trim() === text)
	if (button === undefined) {
		throw new Error(`Button ${text} not found`)
	}

	return button
}

beforeEach(() => {
	const store = useLibrary()
	store.capabilities.value = { editor: 'text', versions: true }
	store.historyAsset.value = asset()
	store.historyVersions.value = [
		version('200', true, 'Current draft'),
		version('100', false, 'First draft'),
	]
})

afterEach(() => {
	const store = useLibrary()
	store.closeHistory()
	vi.restoreAllMocks()
})

describe('VersionHistoryDialog', () => {
	it('shows the current revision and historical messages', () => {
		const wrapper = mountDialog()

		expect(wrapper.text()).toContain('Current revision')
		expect(wrapper.text()).toContain('Current draft')
		expect(wrapper.text()).toContain('First draft')
		expect(wrapper.text()).toContain('Ada')
	})

	it('submits a check-in message for the current revision', async () => {
		const store = useLibrary()
		const checkIn = vi.spyOn(store, 'checkInCurrentRevision').mockResolvedValue(true)
		const wrapper = mountDialog()

		await wrapper.get('input').setValue('Reviewed')
		await buttonByText(wrapper, 'Check in').trigger('click')

		expect(checkIn).toHaveBeenCalledWith(store.historyAsset.value, 'Reviewed')
	})

	it('requires an inline confirmation before restoring a version', async () => {
		const store = useLibrary()
		const restore = vi.spyOn(store, 'restoreVersion').mockResolvedValue(true)
		const wrapper = mountDialog()

		await buttonByText(wrapper, 'Restore').trigger('click')
		expect(restore).not.toHaveBeenCalled()

		await buttonByText(wrapper, 'Restore version').trigger('click')
		expect(restore).toHaveBeenCalledWith(store.historyAsset.value, '100')
	})
})
