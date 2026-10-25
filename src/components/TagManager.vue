<script setup lang="ts">
import { computed, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import ConfirmDialog from './ConfirmDialog.vue'
import TagEditDialog from './TagEditDialog.vue'
import { useLibrary } from '../stores/useLibrary'
import type { Tag } from '../types'
import { DEFAULT_TAG_COLOR, TAG_COLORS, tagChipStyle } from '../utils/tags'

type TagDialog =
	| { type: 'create' }
	| { type: 'edit', tag: Tag }
	| { type: 'delete', tag: Tag }
	| null

const {
	closeTagsManager,
	createTag,
	deleteTag,
	error,
	loading,
	renameTag,
	tags,
	tagsLoading,
} = useLibrary()

const tagDialog = ref<TagDialog>(null)

const newTagColor = computed<string>(() => TAG_COLORS[tags.value.length % TAG_COLORS.length]?.value ?? DEFAULT_TAG_COLOR)

async function submitCreate(name: string, color: string): Promise<void> {
	if (await createTag(name, color)) {
		tagDialog.value = null
	}
}

async function submitEdit(name: string, color: string): Promise<void> {
	const dialog = tagDialog.value
	if (dialog?.type !== 'edit') {
		return
	}
	if (await renameTag(dialog.tag, name, color)) {
		tagDialog.value = null
	}
}

async function submitDelete(): Promise<void> {
	const dialog = tagDialog.value
	if (dialog?.type !== 'delete') {
		return
	}
	if (await deleteTag(dialog.tag)) {
		tagDialog.value = null
	}
}
</script>

<template>
	<div class="tag-manager">
		<header class="tag-header">
			<div>
				<h1 class="tag-title">
					Tags
				</h1>
				<p class="tag-subtitle">
					Tags are per book. They label assets and drive composition rules.
				</p>
			</div>
			<div class="tag-header-actions">
				<NcButton :disabled="loading.mutation" @click="tagDialog = { type: 'create' }">
					New tag
				</NcButton>
				<NcButton @click="closeTagsManager">
					Back to chapters
				</NcButton>
			</div>
		</header>

		<NcNoteCard v-if="error && !tagDialog" type="error" :text="error" />

		<div v-if="tagsLoading" class="tag-state">
			<NcLoadingIcon name="Loading tags" :size="32" />
		</div>
		<NcEmptyContent
			v-else-if="tags.length === 0"
			class="tag-state"
			name="No tags yet"
			description="Create tags such as Raw, Summary, or Terms to classify assets.">
			<template #action>
				<NcButton variant="primary" @click="tagDialog = { type: 'create' }">
					Create tag
				</NcButton>
			</template>
		</NcEmptyContent>
		<ul v-else class="tag-list">
			<li v-for="tag in tags" :key="tag.id" class="tag-row">
				<div class="tag-info">
					<span class="tag-name" :style="tagChipStyle(tag.color)">{{ tag.name }}</span>
					<span class="tag-slug">{{ tag.slug }}</span>
				</div>
				<div class="tag-actions">
					<NcButton :disabled="loading.mutation" @click="tagDialog = { type: 'edit', tag }">
						Edit
					</NcButton>
					<NcButton
						variant="error"
						:disabled="loading.mutation"
						@click="tagDialog = { type: 'delete', tag }">
						Delete
					</NcButton>
				</div>
			</li>
		</ul>

		<TagEditDialog
			v-if="tagDialog?.type === 'create'"
			title="New tag"
			:initial-color="newTagColor"
			confirm-label="Create tag"
			:busy="loading.mutation"
			:error="error"
			@confirm="({ name, color }) => submitCreate(name, color)"
			@close="tagDialog = null" />
		<TagEditDialog
			v-else-if="tagDialog?.type === 'edit'"
			title="Edit tag"
			:initial-name="tagDialog.tag.name"
			:initial-color="tagDialog.tag.color"
			confirm-label="Save tag"
			:busy="loading.mutation"
			:error="error"
			@confirm="({ name, color }) => submitEdit(name, color)"
			@close="tagDialog = null" />
		<ConfirmDialog
			v-else-if="tagDialog?.type === 'delete'"
			title="Delete tag"
			:message="`Delete “${tagDialog.tag.name}”? It will be removed from all assets and composition rules.`"
			confirm-label="Delete tag"
			:busy="loading.mutation"
			:error="error"
			@confirm="submitDelete"
			@close="tagDialog = null" />
	</div>
</template>

<style scoped>
.tag-manager {
	display: flex;
	flex-direction: column;
	gap: 16px;
	box-sizing: border-box;
	width: 100%;
	height: 100%;
	padding: 16px;
	overflow: auto;
}

.tag-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 16px;
	flex-wrap: wrap;
}

.tag-title {
	margin: 0;
	font-size: 1.3rem;
}

.tag-subtitle {
	margin: 4px 0 0;
	color: var(--color-text-maxcontrast);
}

.tag-header-actions,
.tag-actions {
	display: flex;
	gap: 8px;
	flex-wrap: wrap;
}

.tag-state {
	display: flex;
	align-items: center;
	justify-content: center;
	min-height: 220px;
}

.tag-list {
	list-style: none;
	margin: 0;
	padding: 0;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	overflow: hidden;
}

.tag-row {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	padding: 10px 16px;
	border-bottom: 1px solid var(--color-border-dark);
}

.tag-row:last-child {
	border-bottom: none;
}

.tag-info {
	display: flex;
	align-items: baseline;
	gap: 8px;
	min-width: 0;
}

.tag-name {
	padding: 2px 10px;
	border-radius: 12px;
	font-weight: 600;
	font-size: 0.9rem;
}

.tag-slug {
	color: var(--color-text-maxcontrast);
	font-size: 0.85rem;
}
</style>
