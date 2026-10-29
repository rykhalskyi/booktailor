<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { api, ApiError } from '../api/client'
import { useLibrary } from '../stores/useLibrary'
import type { ChapterImage } from '../types'
import { formatFileSize, imageMarkdownSnippet, imagePreviewUrl, imageReferenceLabel } from '../utils/images'

const { selectedChapter } = useLibrary()

const images = ref<ChapterImage[]>([])
const loading = ref(false)
const busy = ref(false)
const error = ref<string | null>(null)
const copiedName = ref<string | null>(null)
const deleteTarget = ref<ChapterImage | null>(null)
const deleteError = ref<string | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)
const replaceTarget = ref<ChapterImage | null>(null)

const chapterId = computed(() => selectedChapter.value?.id ?? null)

let loadEpoch = 0

function messageFor(errorValue: unknown, fallback: string): string {
	return errorValue instanceof ApiError || errorValue instanceof Error
		? errorValue.message
		: fallback
}

async function load(): Promise<void> {
	const id = chapterId.value
	const epoch = ++loadEpoch
	error.value = null
	if (id === null) {
		images.value = []
		loading.value = false
		return
	}

	loading.value = true
	try {
		const loaded = await api.images.list(id)
		if (epoch === loadEpoch && chapterId.value === id) {
			images.value = loaded
		}
	} catch (errorValue) {
		if (epoch === loadEpoch && chapterId.value === id) {
			images.value = []
			error.value = messageFor(errorValue, t('booktailor', 'Could not load images.'))
		}
	} finally {
		if (epoch === loadEpoch) {
			loading.value = false
		}
	}
}

watch(chapterId, () => {
	copiedName.value = null
	deleteTarget.value = null
	deleteError.value = null
	load()
}, { immediate: true })

function chooseUpload(): void {
	if (busy.value) {
		return
	}
	replaceTarget.value = null
	fileInput.value?.click()
}

function chooseReplace(image: ChapterImage): void {
	if (busy.value) {
		return
	}
	replaceTarget.value = image
	fileInput.value?.click()
}

async function onFileChange(event: Event): Promise<void> {
	const input = event.target as HTMLInputElement
	const file = input.files?.[0]
	const replacement = replaceTarget.value
	input.value = ''
	replaceTarget.value = null

	const id = chapterId.value
	if (!file || id === null) {
		return
	}

	busy.value = true
	error.value = null
	try {
		if (replacement === null) {
			await api.images.upload(id, file)
		} else {
			await api.images.replace(id, replacement.fileId, file)
		}
		await load()
	} catch (errorValue) {
		error.value = messageFor(errorValue, t('booktailor', 'Could not save the image.'))
	} finally {
		busy.value = false
	}
}

function askDelete(image: ChapterImage): void {
	deleteError.value = null
	deleteTarget.value = image
}

function closeDelete(): void {
	deleteTarget.value = null
	deleteError.value = null
}

async function confirmDelete(): Promise<void> {
	const target = deleteTarget.value
	const id = chapterId.value
	if (target === null || id === null || busy.value) {
		return
	}

	busy.value = true
	deleteError.value = null
	try {
		await api.images.remove(id, target.fileId)
		deleteTarget.value = null
		await load()
	} catch (errorValue) {
		deleteError.value = messageFor(errorValue, t('booktailor', 'Could not delete the image.'))
	} finally {
		busy.value = false
	}
}

async function copySnippet(image: ChapterImage): Promise<void> {
	error.value = null
	try {
		await navigator.clipboard.writeText(imageMarkdownSnippet(image))
		copiedName.value = image.name
		window.setTimeout(() => {
			if (copiedName.value === image.name) {
				copiedName.value = null
			}
		}, 2000)
	} catch {
		error.value = t('booktailor', 'Could not copy the Markdown snippet.')
	}
}
</script>

<template>
	<section class="chapter-images" aria-labelledby="chapter-images-title">
		<header class="images-header">
			<div>
				<h3 id="chapter-images-title">
					{{ t('booktailor', 'Images') }}
				</h3>
				<p class="images-hint">
					{{ t('booktailor', 'Upload images here, then insert the Markdown snippet into an asset.') }}
				</p>
			</div>
			<NcButton :disabled="busy || chapterId === null" @click="chooseUpload">
				{{ t('booktailor', 'Upload image') }}
			</NcButton>
		</header>

		<NcNoteCard v-if="error" type="error" :text="error" />

		<div v-if="loading" class="images-state">
			<NcLoadingIcon :name="t('booktailor', 'Loading images')" />
		</div>
		<div v-else-if="images.length === 0" class="images-state">
			<NcEmptyContent
				:name="t('booktailor', 'No images yet')"
				:description="t('booktailor', 'Upload a PNG, JPEG, GIF, or WebP image for this chapter.')">
				<template #action>
					<NcButton :disabled="busy || chapterId === null" @click="chooseUpload">
						{{ t('booktailor', 'Upload image') }}
					</NcButton>
				</template>
			</NcEmptyContent>
		</div>
		<ul v-else class="images-grid">
			<li v-for="image in images" :key="image.fileId" class="image-card">
				<img
					class="image-preview"
					:src="imagePreviewUrl(image.fileId)"
					:alt="image.name"
					loading="lazy">
				<div class="image-info">
					<span class="image-name" :title="image.name">{{ image.name }}</span>
					<span class="image-meta">
						{{ formatFileSize(image.size) }} · {{ imageReferenceLabel(image.references) }}
					</span>
				</div>
				<div class="image-actions">
					<NcButton :disabled="busy" @click="copySnippet(image)">
						{{ copiedName === image.name ? t('booktailor', 'Copied') : t('booktailor', 'Copy snippet') }}
					</NcButton>
					<NcButton :disabled="busy" @click="chooseReplace(image)">
						{{ t('booktailor', 'Replace') }}
					</NcButton>
					<NcButton variant="error" :disabled="busy" @click="askDelete(image)">
						{{ t('booktailor', 'Delete') }}
					</NcButton>
				</div>
			</li>
		</ul>

		<input
			ref="fileInput"
			class="hidden-file-input"
			type="file"
			accept="image/png,image/jpeg,image/gif,image/webp"
			@change="onFileChange">

		<NcDialog
			v-if="deleteTarget"
			:open="true"
			:name="t('booktailor', 'Delete image')"
			size="small"
			@closing="closeDelete">
			<NcNoteCard v-if="deleteError" type="error" :text="deleteError" />
			<p class="delete-message">
				{{ t('booktailor', 'Delete “{name}”?', { name: deleteTarget.name }) }}
				<span v-if="deleteTarget.references > 0">
					{{ t('booktailor', 'It is referenced by {count} asset(s), so deletion is blocked until you remove those references.', { count: deleteTarget.references }) }}
				</span>
			</p>
			<template #actions>
				<NcButton variant="tertiary" :disabled="busy" @click="closeDelete">
					{{ t('booktailor', 'Cancel') }}
				</NcButton>
				<NcButton variant="error" :disabled="busy" @click="confirmDelete">
					{{ t('booktailor', 'Delete image') }}
				</NcButton>
			</template>
		</NcDialog>
	</section>
</template>

<style scoped>
.chapter-images {
	display: flex;
	flex-direction: column;
	gap: 10px;
	flex: 0 1 auto;
	max-height: 42%;
	min-height: 130px;
	padding: 14px 16px;
	border-top: 1px solid var(--color-border);
	overflow: auto;
}

.images-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 12px;
	flex-wrap: wrap;
}

.images-header h3 {
	margin: 0;
	font-size: 1rem;
}

.images-hint {
	margin: 2px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 0.85rem;
}

.images-state {
	display: flex;
	align-items: center;
	justify-content: center;
	min-height: 100px;
}

.images-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
	gap: 10px;
	list-style: none;
	margin: 0;
	padding: 0;
}

.image-card {
	display: flex;
	flex-direction: column;
	gap: 6px;
	min-width: 0;
	padding: 8px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
}

.image-preview {
	display: block;
	width: 100%;
	height: 90px;
	object-fit: contain;
	background: var(--color-background-dark);
	border-radius: var(--border-radius-small);
}

.image-info {
	display: flex;
	flex-direction: column;
	min-width: 0;
}

.image-name {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	font-weight: 600;
}

.image-meta {
	color: var(--color-text-maxcontrast);
	font-size: 0.8rem;
}

.image-actions {
	display: flex;
	gap: 6px;
	flex-wrap: wrap;
}

.delete-message {
	margin: 0;
	max-width: 440px;
}

.hidden-file-input {
	display: none;
}
</style>
