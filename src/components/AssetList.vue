<script setup lang="ts">
import { ref } from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { useLibrary } from '../stores/useLibrary'
import type { Asset } from '../types'

const { loading, openDialog, selectedAssets, selectedChapter, uploadAsset } = useLibrary()

const fileInput = ref<HTMLInputElement | null>(null)

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
	return asset.fileId === null ? null : `/index.php/f/${asset.fileId}`
}

function openAsset(asset: Asset): void {
	const url = fileUrl(asset)
	if (url !== null) {
		window.open(url, '_blank', 'noopener')
	}
}
</script>

<template>
	<div class="asset-list">
		<header class="asset-header">
			<h2 class="asset-title">
				{{ selectedChapter?.name ?? 'Assets' }}
			</h2>
			<div v-if="selectedChapter" class="asset-header-actions">
				<NcButton
					:disabled="loading.mutation"
					@click="openDialog({ type: 'asset-create', chapterId: selectedChapter.id })">
					New asset
				</NcButton>
				<NcButton :disabled="loading.mutation" @click="chooseFile">
					Upload
				</NcButton>
			</div>
		</header>

		<div v-if="!selectedChapter" class="asset-state">
			<NcEmptyContent
				name="Select a chapter"
				description="Choose a chapter in the tree to see its Markdown assets." />
		</div>
		<div v-else-if="loading.assets" class="asset-state">
			<NcLoadingIcon name="Loading assets" />
		</div>
		<NcEmptyContent
			v-else-if="selectedAssets.length === 0"
			class="asset-state"
			name="No assets yet"
			description="Create an empty Markdown asset or upload an existing file.">
			<template #action>
				<NcButton
					variant="primary"
					@click="openDialog({ type: 'asset-create', chapterId: selectedChapter.id })">
					New asset
				</NcButton>
			</template>
		</NcEmptyContent>
		<ul v-else class="asset-rows">
			<li v-for="asset in selectedAssets" :key="asset.id" class="asset-row">
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
						<span v-for="tag in asset.tags" :key="tag.id" class="asset-tag">
							{{ tag.name }}
						</span>
					</div>
				</div>
				<NcActions :aria-label="`Actions for ${asset.fileName}`" :force-menu="true">
					<NcActionButton v-if="fileUrl(asset)" @click="openAsset(asset)">
						Open in editor
					</NcActionButton>
					<NcActionButton
						:disabled="loading.mutation"
						@click="openDialog({ type: 'asset-delete', asset })">
						Delete
					</NcActionButton>
				</NcActions>
			</li>
		</ul>

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
	padding: 14px 16px;
	border-bottom: 1px solid var(--color-border);
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

.asset-tag {
	padding: 1px 8px;
	border-radius: 12px;
	background: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
	font-size: 0.8rem;
}

.asset-link:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
}

.hidden-file-input {
	display: none;
}
</style>
