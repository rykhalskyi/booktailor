<script setup lang="ts">
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
		title="New book"
		label="Book name"
		confirm-label="Create book"
		:busy="loading.mutation"
		:error="error"
		@confirm="submitBook"
		@close="closeDialog" />
	<TextInputDialog
		v-else-if="dialog?.type === 'chapter-create'"
		:title="dialog.parentId === null ? 'New chapter' : 'New subchapter'"
		label="Chapter name"
		confirm-label="Create chapter"
		:busy="loading.mutation"
		:error="error"
		@confirm="submitChapter"
		@close="closeDialog" />
	<TextInputDialog
		v-else-if="dialog?.type === 'chapter-rename'"
		title="Rename chapter"
		label="Chapter name"
		:initial-value="dialog.chapter.name"
		confirm-label="Rename chapter"
		:busy="loading.mutation"
		:error="error"
		@confirm="submitChapterRename"
		@close="closeDialog" />
	<ConfirmDialog
		v-else-if="dialog?.type === 'chapter-delete'"
		title="Delete chapter"
		:message="`Delete “${dialog.chapter.name}” and all its subchapters and assets?`"
		confirm-label="Delete chapter"
		:busy="loading.mutation"
		:error="error"
		@confirm="submitChapterDelete"
		@close="closeDialog" />
	<TextInputDialog
		v-else-if="dialog?.type === 'asset-create'"
		title="New asset"
		label="File name"
		confirm-label="Create asset"
		:busy="loading.mutation"
		:error="error"
		@confirm="submitAsset"
		@close="closeDialog" />
	<ConfirmDialog
		v-else-if="dialog?.type === 'asset-delete'"
		title="Delete asset"
		:message="`Delete “${dialog.asset.fileName}”?`"
		confirm-label="Delete file"
		:busy="loading.mutation"
		:error="error"
		@confirm="submitAssetDelete"
		@close="closeDialog" />
	<TagAssignmentDialog v-else-if="dialog?.type === 'asset-tags'" />
	<ChapterHistoryDialog v-else-if="dialog?.type === 'chapter-history'" />
</template>
