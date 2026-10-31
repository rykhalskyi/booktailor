<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { api } from '../api/client'
import { useLibrary } from '../stores/useLibrary'
import type { Asset, CompositionPreviewGroup, Structure } from '../types'
import { defaultCompositionName } from '../utils/compositions'

const {
	closeCompose,
	createComposition,
	error,
	loading,
	selectedBookId,
	selectedStructureId,
	structures,
} = useLibrary()

const structureId = ref<number | null>(selectedStructureId.value)
const outputName = ref(defaultCompositionName())
const previewGroups = ref<CompositionPreviewGroup[]>([])
const previewLoading = ref(false)
const previewError = ref<string | null>(null)
let previewRequest = 0

const selectedStructure = computed<Structure | null>(() => (
	structures.value.find((structure) => structure.id === structureId.value) ?? null
))

const previewAssetCount = computed<number>(() => (
	previewGroups.value.reduce((total, group) => total + group.assets.length, 0)
))

const nameMissing = computed<boolean>(() => outputName.value.trim() === '')

const canSubmit = computed<boolean>(() => (
	structureId.value !== null
	&& !nameMissing.value
	&& !loading.mutation
	&& !previewLoading.value
	&& previewError.value === null
	&& previewAssetCount.value > 0
))

function onStructureChange(structure: Structure | null): void {
	structureId.value = structure?.id ?? null
}

function assetTitle(asset: Asset): string {
	return asset.name === '' ? asset.fileName : asset.name
}

function showAssetFileName(asset: Asset): boolean {
	return asset.name !== '' && asset.name !== asset.fileName
}

function previewMessage(errorValue: unknown): string {
	if (errorValue instanceof Error) {
		return errorValue.message
	}

	return 'Could not load the assets for this structure.'
}

async function loadPreview(): Promise<void> {
	const request = ++previewRequest
	const bookId = selectedBookId.value
	const selectedId = structureId.value
	previewError.value = null

	if (bookId === null || selectedId === null) {
		previewGroups.value = []
		previewLoading.value = false
		return
	}

	previewLoading.value = true
	previewGroups.value = []

	try {
		const groups = await api.compositions.preview(bookId, selectedId)
		if (request !== previewRequest) {
			return
		}

		previewGroups.value = groups
	} catch (errorValue) {
		if (request !== previewRequest) {
			return
		}

		previewGroups.value = []
		previewError.value = previewMessage(errorValue)
	} finally {
		if (request === previewRequest) {
			previewLoading.value = false
		}
	}
}

watch([selectedBookId, structureId], () => {
	loadPreview()
}, { immediate: true })

async function submit(): Promise<void> {
	if (!canSubmit.value || structureId.value === null) {
		return
	}

	await createComposition(
		structureId.value,
		outputName.value.trim(),
	)
}
</script>

<template>
	<div class="compose-screen">
		<header class="compose-header">
			<div>
				<h1 class="compose-title">
					Compose
				</h1>
				<p class="compose-subtitle">
					Turn a structure into one Markdown document using the assets marked
					for composition in the chapter tree.
				</p>
			</div>
			<NcButton @click="closeCompose">
				Back to chapters
			</NcButton>
		</header>

		<NcNoteCard v-if="error" type="error" :text="error" />

		<section class="compose-form" aria-label="Composition settings">
			<div class="compose-row">
				<NcSelect
					:model-value="selectedStructure"
					:options="structures"
					label="name"
					input-label="Structure"
					aria-label="Structure"
					:clearable="false"
					:searchable="false"
					:disabled="loading.mutation"
					@update:model-value="onStructureChange" />
				<NcTextField
					v-model="outputName"
					label="Composition name"
					:error="nameMissing"
					:helper-text="nameMissing ? 'A composition name is required.' : ''"
					:disabled="loading.mutation"
					:maxlength="255" />
			</div>

			<NcNoteCard
				type="info"
				text="Select the assets to include by checking them in the asset table of each chapter." />

			<section class="compose-preview" aria-label="Assets to compose">
				<div class="preview-header">
					<div>
						<h2 class="preview-title">
							Assets to compose
						</h2>
						<p class="preview-subtitle">
							Chapters included in the selected structure and the assets marked
							for composition.
						</p>
					</div>
					<span
						v-if="!previewLoading && previewAssetCount > 0"
						class="preview-count">
						{{ previewAssetCount }}
						{{ previewAssetCount === 1 ? 'asset' : 'assets' }}
						from
						{{ previewGroups.length }}
						{{ previewGroups.length === 1 ? 'chapter' : 'chapters' }}
					</span>
				</div>

				<div v-if="previewLoading" class="preview-state">
					<NcLoadingIcon name="Loading assets to compose" :size="24" />
				</div>
				<NcNoteCard
					v-else-if="previewError"
					type="error"
					:text="previewError" />
				<p v-else-if="previewGroups.length === 0" class="preview-empty">
					No assets are marked for composition in this structure.
				</p>
				<ul v-else class="preview-list">
					<li
						v-for="group in previewGroups"
						:key="group.chapter.id"
						class="preview-chapter">
						<div class="preview-chapter-heading">
							<span v-if="group.chapter.number" class="preview-chapter-number">
								{{ group.chapter.number }}
							</span>
							<span class="preview-chapter-name">{{ group.chapter.name }}</span>
							<span class="preview-chapter-count">{{ group.assets.length }}</span>
						</div>
						<ul class="preview-assets">
							<li
								v-for="asset in group.assets"
								:key="asset.id"
								class="preview-asset">
								<span class="preview-asset-title">{{ assetTitle(asset) }}</span>
								<span v-if="showAssetFileName(asset)" class="preview-asset-file">
									{{ asset.fileName }}
								</span>
							</li>
						</ul>
					</li>
				</ul>
			</section>

			<div class="compose-actions">
				<NcButton
					variant="primary"
					:disabled="!canSubmit"
					@click="submit">
					<NcLoadingIcon
						v-if="loading.mutation"
						name="Creating composition"
						:size="18" />
					Create composition
				</NcButton>
			</div>
		</section>
	</div>
</template>

<style scoped>
.compose-screen {
	display: flex;
	flex-direction: column;
	gap: 16px;
	box-sizing: border-box;
	width: 100%;
	height: 100%;
	padding: 16px;
	overflow: auto;
}

.compose-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 16px;
	flex-wrap: wrap;
}

.compose-title {
	margin: 0;
	font-size: 1.3rem;
}

.compose-subtitle {
	margin: 4px 0 0;
	color: var(--color-text-maxcontrast);
}

.compose-form {
	display: flex;
	flex-direction: column;
	gap: 16px;
	padding: 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.compose-row {
	display: grid;
	grid-template-columns: minmax(220px, 1fr) minmax(220px, 1fr);
	gap: 16px;
}

.compose-preview {
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding: 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-dark);
}

.preview-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 12px;
	flex-wrap: wrap;
}

.preview-title {
	margin: 0;
	font-size: 1rem;
}

.preview-subtitle {
	margin: 4px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 0.9rem;
}

.preview-count {
	flex-shrink: 0;
	padding: 2px 10px;
	border-radius: 12px;
	background: var(--color-primary-element-light);
	font-size: 0.8rem;
	white-space: nowrap;
}

.preview-state {
	display: flex;
	align-items: center;
	justify-content: center;
	min-height: 72px;
}

.preview-empty {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.preview-list,
.preview-assets {
	margin: 0;
	padding: 0;
	list-style: none;
}

.preview-list {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.preview-chapter {
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.preview-chapter-heading {
	display: flex;
	align-items: baseline;
	gap: 8px;
	font-weight: 600;
}

.preview-chapter-number {
	flex-shrink: 0;
	color: var(--color-text-maxcontrast);
	font-variant-numeric: tabular-nums;
}

.preview-chapter-name {
	min-width: 0;
	overflow-wrap: anywhere;
}

.preview-chapter-count {
	margin-inline-start: auto;
	padding: 0 8px;
	border: 1px solid var(--color-border-dark);
	border-radius: 10px;
	color: var(--color-text-maxcontrast);
	font-size: 0.75rem;
	font-weight: 400;
}

.preview-assets {
	display: flex;
	flex-direction: column;
	gap: 4px;
	padding-inline-start: 16px;
}

.preview-asset {
	display: flex;
	align-items: baseline;
	gap: 8px;
	flex-wrap: wrap;
	padding: 4px 8px;
	border-inline-start: 2px solid var(--color-border-dark);
}

.preview-asset-title {
	font-weight: 600;
	overflow-wrap: anywhere;
}

.preview-asset-file {
	color: var(--color-text-maxcontrast);
	font-size: 0.8rem;
	overflow-wrap: anywhere;
}

.compose-actions {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
	flex-wrap: wrap;
}

.compose-actions :deep(.button-vue) {
	gap: 6px;
}

@media (max-width: 720px) {
	.compose-row {
		grid-template-columns: 1fr;
	}
}
</style>
