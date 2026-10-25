<script setup lang="ts">
import { computed, inject, ref, watch } from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { mdiChevronDown, mdiChevronRight } from '../icons'
import { useLibrary } from '../stores/useLibrary'
import type { Asset, Chapter } from '../types'
import { assetFileUrl } from '../utils/files'
import { normalizeTagColor } from '../utils/tags'
import { treeFocusKey } from '../utils/treeFocus'

defineOptions({ name: 'ChapterNode' })

const props = defineProps<{
	chapter: Chapter
	depth: number
}>()

const {
	assetsByChapter,
	capabilities,
	expandedChapterIds,
	loading,
	loadingAssets,
	loadAssets,
	openAssetManager,
	openDialog,
	selectChapter,
	selectedChapterId,
	toggleChapter,
	uploadAsset,
} = useLibrary()

const fileInput = ref<HTMLInputElement | null>(null)
const treeFocus = inject(treeFocusKey, null)

const hasChildren = computed(() => props.chapter.children.length > 0)
const isExpanded = computed(() => expandedChapterIds.value[props.chapter.id] ?? props.depth === 0)
const isSelected = computed(() => selectedChapterId.value === props.chapter.id)
const tabIndex = computed(() => treeFocus === null || treeFocus.focusedChapterId.value === props.chapter.id ? 0 : -1)

const chapterAssets = computed<Asset[]>(() => assetsByChapter.value[props.chapter.id] ?? [])
const chapterAssetsLoading = computed<boolean>(() => loadingAssets.value[props.chapter.id] ?? false)

watch(
	() => props.chapter.id,
	(chapterId) => {
		if (assetsByChapter.value[chapterId] === undefined && !loadingAssets.value[chapterId]) {
			loadAssets(chapterId)
		}
	},
	{ immediate: true },
)

function onNameFocus(): void {
	treeFocus?.setFocusedChapter(props.chapter.id)
}

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

function fileUrl(asset: Asset): string | null {
	return assetFileUrl(asset.fileId)
}

function assetTagNames(asset: Asset): string {
	return asset.tags.map((tag) => tag.name).join(', ')
}

function openAsset(asset: Asset): void {
	const url = fileUrl(asset)
	if (url !== null) {
		window.open(url, '_blank', 'noopener')
	}
}
</script>

<template>
	<li
		class="chapter-node"
		role="treeitem"
		:aria-level="depth + 1"
		:aria-expanded="hasChildren ? isExpanded : undefined"
		:aria-selected="isSelected">
		<div
			class="chapter-row"
			:class="{ 'chapter-row--selected': isSelected }"
			:style="{ paddingInlineStart: `${8 + depth * 16}px` }">
			<button
				v-if="hasChildren"
				type="button"
				class="chapter-chevron"
				tabindex="-1"
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
				:tabindex="tabIndex"
				:data-chapter-id="chapter.id"
				:data-depth="depth"
				@focus="onNameFocus"
				@click="selectChapter(chapter)">
				<span v-if="chapter.number" class="chapter-number">{{ chapter.number }}</span>
				<span class="chapter-name-text">{{ chapter.name }}</span>
			</button>
			<div v-if="chapterAssets.length > 0" class="chapter-asset-chips" @click.stop>
				<div
					v-for="asset in chapterAssets"
					:key="asset.id"
					class="asset-chip"
					:class="{ 'asset-chip--missing': !fileUrl(asset) }">
					<span
						v-if="asset.tags.length > 0"
						class="asset-chip-tags"
						:title="assetTagNames(asset)">
						<span
							v-for="tag in asset.tags"
							:key="tag.id"
							class="asset-chip-tag"
							:style="{ backgroundColor: normalizeTagColor(tag.color) }" />
					</span>
					<button
						type="button"
						class="asset-chip-name"
						:title="asset.fileName"
						:disabled="!fileUrl(asset)"
						@click="openAsset(asset)">
						📄 {{ asset.fileName }}
					</button>
				</div>
			</div>
			<NcLoadingIcon
				v-else-if="chapterAssetsLoading"
				class="chapter-asset-chips-loading"
				name="Loading assets"
				:size="16" />
			<div class="chapter-actions" @click.stop>
				<NcActions :aria-label="`Actions for ${chapter.name}`" :force-menu="true">
					<NcActionButton
						:disabled="loading.mutation"
						@click="openDialog({ type: 'chapter-create', parentId: chapter.id })">
						Add subchapter
					</NcActionButton>
					<NcActionButton
						:disabled="loading.mutation"
						@click="openAssetManager(chapter)">
						Edit assets
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
					<NcActionButton
						:disabled="!capabilities.versions"
						@click="openDialog({ type: 'chapter-history', chapter })">
						History
					</NcActionButton>
					<NcActionButton disabled>
						Agent (coming soon)
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
		<ul v-if="hasChildren && isExpanded" class="chapter-children" role="group">
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
	display: flex;
	align-items: baseline;
	gap: 6px;
	flex: 1;
	min-width: 0;
	padding: 4px 2px;
	border: none;
	background: transparent;
	color: var(--color-main-text);
	font-weight: 600;
	text-align: start;
	overflow: hidden;
	white-space: nowrap;
	cursor: pointer;
}

.chapter-number {
	flex-shrink: 0;
	color: var(--color-text-maxcontrast);
	font-variant-numeric: tabular-nums;
}

.chapter-name-text {
	min-width: 0;
	text-overflow: ellipsis;
	overflow: hidden;
	white-space: nowrap;
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

.chapter-asset-chips {
	display: flex;
	align-items: center;
	gap: 4px;
	flex-shrink: 0;
	overflow: hidden;
	max-width: 50%;
}

.chapter-asset-chips-loading {
	flex-shrink: 0;
}

.asset-chip {
	display: inline-flex;
	align-items: center;
	max-width: 180px;
	border-radius: 12px;
	background: var(--color-background-dark);
	color: var(--color-main-text);
	font-size: 0.75rem;
	line-height: 1.6;
	overflow: hidden;
}

.asset-chip:hover {
	background: var(--color-primary-element-light);
}

.asset-chip-tags {
		display: inline-flex;
		align-items: center;
		gap: 2px;
		padding-inline-start: 7px;
		flex-shrink: 0;
	}

.asset-chip-tag {
	width: 7px;
	height: 7px;
	border-radius: 50%;
	box-shadow: 0 0 0 1px var(--color-main-background);
	flex-shrink: 0;
}

.asset-chip-name {
	display: inline-flex;
	align-items: center;
	padding: 1px 4px 1px 6px;
	border: none;
	background: transparent;
	color: var(--color-main-text);
	font-size: inherit;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	cursor: pointer;
}

.asset-chip-name:hover:not(:disabled) {
	text-decoration: underline;
}

.asset-chip-name:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 1px;
}

.asset-chip-delete {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 18px;
	height: 18px;
	margin-inline-end: 2px;
	padding: 0;
	border: none;
	border-radius: 50%;
	background: transparent;
	color: var(--color-text-maxcontrast);
	font-size: 0.9rem;
	line-height: 1;
	cursor: pointer;
}

.asset-chip-delete:hover:not(:disabled) {
	background: var(--color-error);
	color: var(--color-main-background);
}

.asset-chip-delete:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 1px;
}

.asset-chip--missing {
	color: var(--color-text-maxcontrast);
}

.asset-chip--missing .asset-chip-name {
	cursor: default;
	color: var(--color-text-maxcontrast);
}
</style>
