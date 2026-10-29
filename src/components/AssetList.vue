<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { useLibrary } from '../stores/useLibrary'
import ChapterImages from './ChapterImages.vue'
import type { Asset, Tag } from '../types'
import { filterAssetsByTag } from '../utils/assets'
import { assetEditorUrl, assetFileUrl } from '../utils/files'
import { tagChipStyle } from '../utils/tags'

withDefaults(defineProps<{
	showBack?: boolean
}>(), {
	showBack: false,
})

const emit = defineEmits<{
	(event: 'back'): void
}>()

const {
	capabilities,
	loading,
	openDialog,
	openHistory,
	selectedAssets,
	selectedAssetsLoading,
	selectedChapter,
	tags,
	uploadAsset,
} = useLibrary()

const fileInput = ref<HTMLInputElement | null>(null)
const selectedTag = ref<Tag | null>(null)

const filteredAssets = computed<Asset[]>(() => filterAssetsByTag(
	selectedAssets.value,
	selectedTag.value?.id ?? null,
))

watch(tags, (available) => {
	const current = selectedTag.value
	if (current !== null && !available.some((tag) => tag.id === current.id)) {
		selectedTag.value = null
	}
})

function chooseFile(): void {
	if (selectedChapter.value) {
		fileInput.value?.click()
	}
}

async function onFileChange(event: Event): Promise<void> {
	const input = event.target as HTMLInputElement
	const file = input.files?.[0]
	input.value = ''
	const chapter = selectedChapter.value
	if (file && chapter) {
		await uploadAsset(chapter.id, file)
	}
}

function fileUrl(asset: Asset): string | null {
	return assetFileUrl(asset.fileId)
}

function openAsset(asset: Asset): void {
	const url = assetEditorUrl(asset)
	if (url !== null) {
		window.open(url, '_blank', 'noopener')
	}
}
</script>

<template>
	<div class="asset-list">
		<header class="asset-header">
			<div class="asset-header-main">
				<NcButton v-if="showBack" @click="emit('back')">
					{{ t('booktailor', 'Back to chapters') }}
				</NcButton>
				<h2 class="asset-title">
					{{ selectedChapter?.name ?? t('booktailor', 'Assets') }}
				</h2>
			</div>
			<div v-if="selectedChapter" class="asset-header-actions">
				<NcButton
					:disabled="loading.mutation"
					@click="openDialog({ type: 'asset-create', chapterId: selectedChapter.id })">
					{{ t('booktailor', 'New asset') }}
				</NcButton>
				<NcButton :disabled="loading.mutation" @click="chooseFile">
					{{ t('booktailor', 'Upload') }}
				</NcButton>
			</div>
		</header>

		<div v-if="selectedChapter && tags.length > 0" class="asset-filter-row">
			<NcSelect
				v-model="selectedTag"
				class="asset-filter"
				:options="tags"
				label="name"
				:input-label="t('booktailor', 'Filter by tag')"
				:aria-label="t('booktailor', 'Filter assets by tag')"
				:clearable="true"
				:searchable="false"
				:placeholder="t('booktailor', 'All tags')" />
		</div>

		<div v-if="!selectedChapter" class="asset-state">
			<NcEmptyContent
				:name="t('booktailor', 'Select a chapter')"
				:description="t('booktailor', 'Choose a chapter in the tree to see its Markdown assets.')" />
		</div>
		<div v-else-if="selectedAssetsLoading" class="asset-state">
			<NcLoadingIcon :name="t('booktailor', 'Loading assets')" />
		</div>
		<NcEmptyContent
			v-else-if="selectedAssets.length === 0"
			class="asset-state"
			:name="t('booktailor', 'No assets yet')"
			:description="t('booktailor', 'Create an empty Markdown asset or upload an existing file.')">
			<template #action>
				<NcButton
					variant="primary"
					@click="openDialog({ type: 'asset-create', chapterId: selectedChapter.id })">
					{{ t('booktailor', 'New asset') }}
				</NcButton>
			</template>
		</NcEmptyContent>
		<NcEmptyContent
			v-else-if="filteredAssets.length === 0"
			class="asset-state"
			:name="t('booktailor', 'No assets match this tag')"
			:description="t('booktailor', 'Clear the tag filter to see all assets in this chapter.')">
			<template #action>
				<NcButton @click="selectedTag = null">
					{{ t('booktailor', 'Clear filter') }}
				</NcButton>
			</template>
		</NcEmptyContent>
		<ul v-else class="asset-rows">
			<li v-for="asset in filteredAssets" :key="asset.id" class="asset-row">
				<div class="asset-info">
					<button
						v-if="fileUrl(asset)"
						type="button"
						class="asset-link"
						:title="asset.fileName"
						@click="openAsset(asset)">
						{{ asset.fileName }}
					</button>
					<span v-else class="asset-link asset-link--missing">{{ asset.fileName }}</span>
					<div v-if="asset.tags.length > 0" class="asset-tags">
						<button
							v-for="tag in asset.tags"
							:key="tag.id"
							type="button"
							class="asset-tag"
							:style="tagChipStyle(tag.color)"
							:title="t('booktailor', 'Filter by {tag}', { tag: tag.name })"
							@click="selectedTag = tag">
							{{ tag.name }}
						</button>
					</div>
				</div>
				<div class="asset-row-actions">
					<NcButton
						:disabled="loading.mutation"
						@click="openDialog({ type: 'asset-tags', asset })">
						{{ t('booktailor', 'Tags') }}
					</NcButton>
					<NcActions :aria-label="t('booktailor', 'Actions for {name}', { name: asset.fileName })" :force-menu="true">
						<NcActionButton v-if="fileUrl(asset)" @click="openAsset(asset)">
							{{ t('booktailor', 'Open in editor') }}
						</NcActionButton>
						<NcActionButton
							:disabled="!capabilities.versions"
							@click="openHistory(asset)">
							{{ t('booktailor', 'History') }}
						</NcActionButton>
						<NcActionButton
							:disabled="loading.mutation"
							@click="openDialog({ type: 'asset-delete', asset })">
							{{ t('booktailor', 'Delete') }}
						</NcActionButton>
					</NcActions>
				</div>
			</li>
		</ul>

		<ChapterImages v-if="selectedChapter" />

		<input
			ref="fileInput"
			class="hidden-file-input"
			type="file"
			accept=".md,text/markdown"
			@change="onFileChange">
	</div>
</template>

<style scoped>
.asset-list {
	display: flex;
	flex-direction: column;
	height: 100%;
	min-height: 0;
}

.asset-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	flex-wrap: wrap;
	padding: 14px 16px;
	border-bottom: 1px solid var(--color-border);
}

.asset-header-main {
	display: flex;
	align-items: center;
	gap: 12px;
	min-width: 0;
}

.asset-title {
	margin: 0;
	font-size: 1.1rem;
	text-overflow: ellipsis;
	overflow: hidden;
	white-space: nowrap;
}

.asset-header-actions {
	display: flex;
	gap: 8px;
	flex-shrink: 0;
}

.asset-state {
	display: flex;
	align-items: center;
	justify-content: center;
	flex: 1;
	min-height: 180px;
}

.asset-rows {
	list-style: none;
	margin: 0;
	padding: 0;
	overflow: auto;
	flex: 1;
}

.asset-row {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	padding: 10px 16px;
	border-bottom: 1px solid var(--color-border-dark);
}

.asset-row:hover {
	background: var(--color-background-hover);
}

.asset-row-actions {
	display: flex;
	align-items: center;
	gap: 6px;
	flex-shrink: 0;
}

.asset-info {
	display: flex;
	flex-direction: column;
	gap: 4px;
	min-width: 0;
}

.asset-link {
	padding: 0;
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

.asset-link:hover {
	text-decoration: underline;
}

.asset-link--missing {
	color: var(--color-text-maxcontrast);
	cursor: default;
}

.asset-tags {
	display: flex;
	flex-wrap: wrap;
	gap: 4px;
}

.asset-filter-row {
	padding: 10px 16px;
	border-bottom: 1px solid var(--color-border);
}

.asset-filter {
	width: 220px;
	max-width: 100%;
}

.asset-tag {
	padding: 1px 8px;
	border: none;
	border-radius: 12px;
	font-size: 0.8rem;
	cursor: pointer;
	opacity: 0.92;
}

.asset-tag:hover,
.asset-tag:focus-visible {
	opacity: 1;
}

.asset-tag:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 1px;
}

.asset-link:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
}

.hidden-file-input {
	display: none;
}
</style>
