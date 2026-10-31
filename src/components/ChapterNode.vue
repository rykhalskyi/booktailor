<script setup lang="ts">
import { computed, inject, ref, watch } from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { mdiChevronDown, mdiChevronRight } from '../icons'
import { useLibrary } from '../stores/useLibrary'
import type { Asset, Chapter, Tag } from '../types'
import { assetEditorUrl } from '../utils/files'
import { treeFocusKey } from '../utils/treeFocus'
import ChapterAssetSummary from './ChapterAssetSummary.vue'
import ChapterAssetTable from './ChapterAssetTable.vue'

defineOptions({ name: 'ChapterNode' })

const props = defineProps<{
	chapter: Chapter
	depth: number
}>()

const {
	assetsByChapter,
	capabilities,
	expandedAssetChapterIds,
	expandedChapterIds,
	loading,
	loadingAssets,
	loadAssets,
	openAssetManager,
	openDialog,
	selectChapter,
	selectedChapterId,
	setAssetIncluded,
	toggleAssets,
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
const assetsExpanded = computed<boolean>(() => expandedAssetChapterIds.value[props.chapter.id] ?? false)
const chapterTags = computed<Tag[]>(() => {
	const byId = new Map<number, Tag>()
	for (const asset of chapterAssets.value) {
		for (const tag of asset.tags) {
			byId.set(tag.id, tag)
		}
	}
	return [...byId.values()]
})
const includedCount = computed<number>(() => chapterAssets.value.filter(
	(asset) => asset.includeInComposition,
).length)
const assetTableIndent = computed<number>(() => (
	8 + props.depth * 16 + 26 + 4 + 2 - 8
))

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

function openAsset(asset: Asset): void {
	const url = assetEditorUrl(asset)
	if (url !== null) {
		window.open(url, '_blank', 'noopener')
	}
}

function editAssetTags(asset: Asset): void {
	openDialog({ type: 'asset-tags', asset })
}

function deleteAsset(asset: Asset): void {
	openDialog({ type: 'asset-delete', asset })
}

function includeAsset(asset: Asset, includeInComposition: boolean): void {
	setAssetIncluded(asset, includeInComposition)
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
				<svg
					class="chapter-chevron-icon"
					viewBox="0 0 24 24"
					width="24"
					height="24"
					aria-hidden="true">
					<path :d="isExpanded ? mdiChevronDown : mdiChevronRight" />
				</svg>
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
			<ChapterAssetSummary
				v-if="chapterAssets.length > 0"
				:asset-count="chapterAssets.length"
				:tags="chapterTags"
				:included-count="includedCount"
				:expanded="assetsExpanded"
				@toggle="toggleAssets(chapter.id, assetsExpanded)" />
			<NcLoadingIcon
				v-else-if="chapterAssetsLoading"
				class="chapter-asset-summary-loading"
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
		<ChapterAssetTable
			v-if="assetsExpanded && chapterAssets.length > 0"
			:assets="chapterAssets"
			:indent="assetTableIndent"
			:busy="loading.mutation"
			@open="openAsset"
			@tags="editAssetTags"
			@delete="deleteAsset"
			@include="includeAsset" />
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
	flex-shrink: 0;
	width: 26px;
	height: 26px;
	padding: 0;
	border: none;
	background: transparent;
	color: var(--color-main-text);
	line-height: 0;
	cursor: pointer;
}

.chapter-chevron--empty {
	cursor: default;
}

.chapter-chevron-icon {
	display: block;
	fill: currentColor;
	pointer-events: none;
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

.chapter-asset-summary-loading {
	flex-shrink: 0;
}
</style>
