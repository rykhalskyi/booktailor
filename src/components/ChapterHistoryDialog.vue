<script setup lang="ts">
import { computed, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { useLibrary } from '../stores/useLibrary'
import type { Asset } from '../types'

const {
	assetsByChapter,
	closeDialog,
	dialog,
	error,
	loadAssets,
	loadingAssets,
	openHistory,
} = useLibrary()

const chapter = computed(() => dialog.value?.type === 'chapter-history' ? dialog.value.chapter : null)
const chapterAssets = computed<Asset[]>(() => chapter.value === null
	? []
	: assetsByChapter.value[chapter.value.id] ?? [])
const loading = computed<boolean>(() => chapter.value === null
	? false
	: loadingAssets.value[chapter.value.id] ?? false)

watch(chapter, (current) => {
	if (current !== null && assetsByChapter.value[current.id] === undefined) {
		loadAssets(current.id)
	}
}, { immediate: true })

async function selectAsset(asset: Asset): Promise<void> {
	closeDialog()
	await openHistory(asset)
}
</script>

<template>
	<NcDialog
		:open="chapter !== null"
		:name="chapter ? `History — ${chapter.name}` : 'History'"
		size="small"
		@closing="closeDialog">
		<div class="chapter-history">
			<NcNoteCard v-if="error" type="error" :text="error" />
			<div v-if="loading" class="chapter-history-state">
				<NcLoadingIcon name="Loading assets" />
			</div>
			<p v-else-if="chapterAssets.length === 0" class="chapter-history-empty">
				This chapter has no assets yet.
			</p>
			<ul v-else class="chapter-history-list">
				<li v-for="asset in chapterAssets" :key="asset.id">
					<button type="button" class="chapter-history-asset" @click="selectAsset(asset)">
						{{ asset.fileName }}
					</button>
				</li>
			</ul>
		</div>
		<template #actions>
			<NcButton variant="tertiary" @click="closeDialog">
				Cancel
			</NcButton>
		</template>
	</NcDialog>
</template>

<style scoped>
.chapter-history {
	display: flex;
	flex-direction: column;
	gap: 8px;
	min-width: 240px;
}

.chapter-history-state {
	display: flex;
	justify-content: center;
	padding: 12px;
}

.chapter-history-empty {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.chapter-history-list {
	list-style: none;
	margin: 0;
	padding: 0;
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.chapter-history-asset {
	width: 100%;
	padding: 8px 10px;
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius);
	background: transparent;
	color: var(--color-main-text);
	text-align: start;
	cursor: pointer;
}

.chapter-history-asset:hover {
	background: var(--color-background-hover);
}

.chapter-history-asset:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
}
</style>
