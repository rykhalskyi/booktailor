<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { useLibrary } from '../stores/useLibrary'
import ChapterHistoryDialog from './ChapterHistoryDialog.vue'
import ConfirmDialog from './ConfirmDialog.vue'
import TagAssignmentDialog from './TagAssignmentDialog.vue'
import TextInputDialog from './TextInputDialog.vue'

const {
	closeDialog,
	createAsset,
	createBook,
	createChapter,
	deleteAsset,
	deleteChapter,
	dialog,
	error,
	loading,
	renameChapter,
} = useLibrary()

async function submitBook(name: string): Promise<void> {
	if (await createBook(name)) {
		closeDialog()
	}
}

async function submitChapter(name: string): Promise<void> {
	const state = dialog.value
	if (state?.type !== 'chapter-create') {
		return
	}
	if (await createChapter(name, state.parentId)) {
		closeDialog()
	}
}

async function submitChapterRename(name: string): Promise<void> {
	const state = dialog.value
	if (state?.type !== 'chapter-rename') {
		return
	}
	if (await renameChapter(state.chapter, name)) {
		closeDialog()
	}
}

async function submitChapterDelete(): Promise<void> {
	const state = dialog.value
	if (state?.type !== 'chapter-delete') {
		return
	}
	if (await deleteChapter(state.chapter)) {
		closeDialog()
	}
}

async function submitAsset(name: string): Promise<void> {
	const state = dialog.value
	if (state?.type !== 'asset-create') {
		return
	}
	if (await createAsset(state.chapterId, name)) {
		closeDialog()
	}
}

async function submitAssetDelete(): Promise<void> {
	const state = dialog.value
	if (state?.type !== 'asset-delete') {
		return
	}
	if (await deleteAsset(state.asset)) {
		closeDialog()
	}
}
</script>

<template>
	<TextInputDialog
		v-if="dialog?.type === 'book-create'"
		:title="t('booktailor', 'New book')"
		:label="t('booktailor', 'Book name')"
		:confirm-label="t('booktailor', 'Create book')"
		:busy="loading.mutation"
		:error="error"
		@confirm="submitBook"
		@close="closeDialog" />
	<TextInputDialog
		v-else-if="dialog?.type === 'chapter-create'"
		:title="dialog.parentId === null ? t('booktailor', 'New chapter') : t('booktailor', 'New subchapter')"
		:label="t('booktailor', 'Chapter name')"
		:confirm-label="t('booktailor', 'Create chapter')"
		:busy="loading.mutation"
		:error="error"
		@confirm="submitChapter"
		@close="closeDialog" />
	<TextInputDialog
		v-else-if="dialog?.type === 'chapter-rename'"
		:title="t('booktailor', 'Rename chapter')"
		:label="t('booktailor', 'Chapter name')"
		:initial-value="dialog.chapter.name"
		:confirm-label="t('booktailor', 'Rename chapter')"
		:busy="loading.mutation"
		:error="error"
		@confirm="submitChapterRename"
		@close="closeDialog" />
	<ConfirmDialog
		v-else-if="dialog?.type === 'chapter-delete'"
		:title="t('booktailor', 'Delete chapter')"
		:message="t('booktailor', 'Delete “{name}” and all its subchapters and assets?', { name: dialog.chapter.name })"
		:confirm-label="t('booktailor', 'Delete chapter')"
		:busy="loading.mutation"
		:error="error"
		@confirm="submitChapterDelete"
		@close="closeDialog" />
	<TextInputDialog
		v-else-if="dialog?.type === 'asset-create'"
		:title="t('booktailor', 'New asset')"
		:label="t('booktailor', 'File name')"
		:confirm-label="t('booktailor', 'Create asset')"
		:busy="loading.mutation"
		:error="error"
		@confirm="submitAsset"
		@close="closeDialog" />
	<ConfirmDialog
		v-else-if="dialog?.type === 'asset-delete'"
		:title="t('booktailor', 'Delete asset')"
		:message="t('booktailor', 'Delete “{name}”?', { name: dialog.asset.fileName })"
		:confirm-label="t('booktailor', 'Delete file')"
		:busy="loading.mutation"
		:error="error"
		@confirm="submitAssetDelete"
		@close="closeDialog" />
	<TagAssignmentDialog v-else-if="dialog?.type === 'asset-tags'" />
	<ChapterHistoryDialog v-else-if="dialog?.type === 'chapter-history'" />
</template>
