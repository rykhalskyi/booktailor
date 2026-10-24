<script setup lang="ts">
import { computed, ref } from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { mdiChevronDown, mdiChevronRight } from '../icons'
import { useLibrary } from '../stores/useLibrary'
import type { Chapter } from '../types'

defineOptions({ name: 'ChapterNode' })

const props = defineProps<{
	chapter: Chapter
	depth: number
}>()

const {
	expandedChapterIds,
	loading,
	openDialog,
	selectChapter,
	selectedChapterId,
	toggleChapter,
	uploadAsset,
} = useLibrary()

const fileInput = ref<HTMLInputElement | null>(null)

const hasChildren = computed(() => props.chapter.children.length > 0)
const isExpanded = computed(() => expandedChapterIds.value[props.chapter.id] ?? props.depth === 0)
const isSelected = computed(() => selectedChapterId.value === props.chapter.id)

function toggle(): void {
	toggleChapter(props.chapter.id, isExpanded.value)
}

function chooseFile(): void {
	fileInput.value?.click()
}

async function onFileChange(event: Event): Promise<void> {
	const input = event.target as HTMLInputElement
	const file = input.files?.[0]
	input.value = ''
	if (file) {
		await uploadAsset(props.chapter.id, file)
	}
}
</script>

<template>
	<li class="chapter-node">
		<div
			class="chapter-row"
			:class="{ 'chapter-row--selected': isSelected }"
			:style="{ paddingInlineStart: `${8 + depth * 16}px` }">
			<button
				v-if="hasChildren"
				type="button"
				class="chapter-chevron"
				:aria-label="isExpanded ? `Collapse ${chapter.name}` : `Expand ${chapter.name}`"
				:aria-expanded="isExpanded"
				@click.stop="toggle">
				<NcIconSvgWrapper
					:path="isExpanded ? mdiChevronDown : mdiChevronRight"
					:size="20" />
			</button>
			<span v-else class="chapter-chevron chapter-chevron--empty" aria-hidden="true" />
			<button
				type="button"
				class="chapter-name"
				:title="chapter.name"
				@click="selectChapter(chapter)">
				{{ chapter.name }}
			</button>
			<div class="chapter-actions" @click.stop>
				<NcActions :aria-label="`Actions for ${chapter.name}`" :force-menu="true">
					<NcActionButton
						:disabled="loading.mutation"
						@click="openDialog({ type: 'chapter-create', parentId: chapter.id })">
						Add subchapter
					</NcActionButton>
					<NcActionButton
						:disabled="loading.mutation"
						@click="openDialog({ type: 'asset-create', chapterId: chapter.id })">
						New asset
					</NcActionButton>
					<NcActionButton :disabled="loading.mutation" @click="chooseFile">
						Upload file
					</NcActionButton>
					<NcActionButton
						:disabled="loading.mutation"
						@click="openDialog({ type: 'chapter-rename', chapter })">
						Rename
					</NcActionButton>
					<NcActionSeparator />
					<NcActionButton
						:disabled="loading.mutation"
						@click="openDialog({ type: 'chapter-delete', chapter })">
						Delete chapter
					</NcActionButton>
				</NcActions>
			</div>
		</div>
		<ul v-if="hasChildren && isExpanded" class="chapter-children">
			<ChapterNode
				v-for="child in chapter.children"
				:key="child.id"
				:chapter="child"
				:depth="depth + 1" />
		</ul>
		<input
			ref="fileInput"
			class="hidden-file-input"
			type="file"
			accept=".md,text/markdown"
			@change="onFileChange">
	</li>
</template>

<style scoped>
.chapter-node,
.chapter-children {
	list-style: none;
	margin: 0;
	padding: 0;
}

.chapter-row {
	display: flex;
	align-items: center;
	gap: 4px;
	min-height: 38px;
	padding-inline-end: 6px;
	border-radius: var(--border-radius);
}

.chapter-row:hover {
	background: var(--color-background-hover);
}

.chapter-row--selected {
	background: var(--color-primary-element-light);
}

.chapter-chevron {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 26px;
	height: 26px;
	padding: 0;
	border: none;
	background: transparent;
	color: var(--color-main-text);
	cursor: pointer;
}

.chapter-chevron--empty {
	cursor: default;
}

.chapter-name {
	flex: 1;
	min-width: 0;
	padding: 4px 2px;
	border: none;
	background: transparent;
	color: var(--color-main-text);
	font-weight: 600;
	text-align: start;
	text-overflow: ellipsis;
	overflow: hidden;
	white-space: nowrap;
	cursor: pointer;
}

.chapter-actions {
	display: flex;
	align-items: center;
}

.chapter-chevron:focus-visible,
.chapter-name:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
}

.hidden-file-input {
	display: none;
}
</style>
