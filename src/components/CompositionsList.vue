<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import ConfirmDialog from './ConfirmDialog.vue'
import { useLibrary } from '../stores/useLibrary'
import type { Composition } from '../types'
import {
	compositionFileUrl,
	compositionStatusClass,
	compositionStatusLabel,
} from '../utils/compositions'

const {
	closeCompositions,
	compositions,
	compositionsLoading,
	deleteComposition,
	downloadComposition,
	error,
	loadCompositions,
	loading,
	openCompose,
	retryComposition,
} = useLibrary()

const deleteTarget = ref<Composition | null>(null)
let pollTimer: ReturnType<typeof setInterval> | null = null

const hasActive = computed<boolean>(() => compositions.value.some((composition) => (
	composition.status === 'queued' || composition.status === 'running'
)))

function isRunning(composition: Composition): boolean {
	return composition.status === 'running' && !composition.stale
}

function canRetry(composition: Composition): boolean {
	return composition.status === 'failed' || composition.stale
}

function canDelete(composition: Composition): boolean {
	return composition.status === 'succeeded' || composition.status === 'failed'
}

function formatDate(value: string): string {
	const date = new Date(value)

	return Number.isNaN(date.getTime()) ? value : date.toLocaleString()
}

function openOutput(composition: Composition): void {
	const url = compositionFileUrl(composition)
	if (url !== null) {
		window.open(url, '_blank', 'noopener')
	}
}

async function submitDelete(): Promise<void> {
	const composition = deleteTarget.value
	if (composition === null) {
		return
	}

	if (await deleteComposition(composition)) {
		deleteTarget.value = null
	}
}

onMounted(() => {
	loadCompositions()
	pollTimer = setInterval(() => {
		if (hasActive.value) {
			loadCompositions()
		}
	}, 3000)
})

onUnmounted(() => {
	if (pollTimer !== null) {
		clearInterval(pollTimer)
		pollTimer = null
	}
})
</script>

<template>
	<div class="compositions-screen">
		<header class="compositions-header">
			<div>
				<h1 class="compositions-title">
					Compositions
				</h1>
				<p class="compositions-subtitle">
					Generated Markdown outputs for this book.
				</p>
			</div>
			<div class="compositions-header-actions">
				<NcButton variant="primary" :disabled="loading.mutation" @click="openCompose">
					New composition
				</NcButton>
				<NcButton @click="closeCompositions">
					Back to chapters
				</NcButton>
			</div>
		</header>

		<NcNoteCard v-if="error && !deleteTarget" type="error" :text="error" />

		<div v-if="compositionsLoading && compositions.length === 0" class="compositions-state">
			<NcLoadingIcon name="Loading compositions" :size="32" />
		</div>
		<NcEmptyContent
			v-else-if="compositions.length === 0"
			class="compositions-state"
			name="No compositions yet"
			description="Compose a structure to generate the first Markdown output.">
			<template #action>
				<NcButton variant="primary" @click="openCompose">
					Create composition
				</NcButton>
			</template>
		</NcEmptyContent>
		<ul v-else class="composition-list">
			<li v-for="composition in compositions" :key="composition.id" class="composition-row">
				<div class="composition-main">
					<div class="composition-heading">
						<span class="composition-structure">{{ composition.structureName }}</span>
						<span class="composition-status" :class="compositionStatusClass(composition)">
							<NcLoadingIcon
								v-if="isRunning(composition)"
								name="Composition running"
								:size="14" />
							{{ compositionStatusLabel(composition) }}
						</span>
					</div>
					<div class="composition-meta">
						<span>Created {{ formatDate(composition.createdAt) }}</span>
						<span v-if="composition.outputPath" class="composition-path">
							{{ composition.outputPath }}
						</span>
					</div>
					<p v-if="composition.error" class="composition-error">
						{{ composition.error }}
					</p>
				</div>
				<div class="composition-actions">
					<NcButton
						:disabled="composition.outputFileId === null"
						@click="openOutput(composition)">
						Open
					</NcButton>
					<NcButton
						:disabled="composition.status !== 'succeeded' || loading.mutation"
						@click="downloadComposition(composition)">
						Download
					</NcButton>
					<NcButton
						v-if="canRetry(composition)"
						:disabled="loading.mutation"
						@click="retryComposition(composition)">
						Retry
					</NcButton>
					<NcButton
						v-if="canDelete(composition)"
						variant="error"
						:disabled="loading.mutation"
						@click="deleteTarget = composition">
						Delete
					</NcButton>
				</div>
			</li>
		</ul>

		<ConfirmDialog
			v-if="deleteTarget !== null"
			title="Delete composition"
			:message="`Delete the output for “${deleteTarget.structureName}”? This cannot be undone.`"
			confirm-label="Delete composition"
			:busy="loading.mutation"
			:error="error"
			@confirm="submitDelete"
			@close="deleteTarget = null" />
	</div>
</template>

<style scoped>
.compositions-screen {
	display: flex;
	flex-direction: column;
	gap: 16px;
	box-sizing: border-box;
	width: 100%;
	height: 100%;
	padding: 16px;
	overflow: auto;
}

.compositions-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 16px;
	flex-wrap: wrap;
}

.compositions-title {
	margin: 0;
	font-size: 1.3rem;
}

.compositions-subtitle {
	margin: 4px 0 0;
	color: var(--color-text-maxcontrast);
}

.compositions-header-actions,
.composition-actions {
	display: flex;
	gap: 8px;
	flex-wrap: wrap;
}

.compositions-state {
	display: flex;
	align-items: center;
	justify-content: center;
	min-height: 220px;
}

.composition-list {
	display: flex;
	flex-direction: column;
	margin: 0;
	padding: 0;
	list-style: none;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	overflow: hidden;
}

.composition-row {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
	padding: 12px 16px;
	border-bottom: 1px solid var(--color-border-dark);
	flex-wrap: wrap;
}

.composition-row:last-child {
	border-bottom: none;
}

.composition-main {
	display: flex;
	flex-direction: column;
	gap: 4px;
	min-width: 0;
}

.composition-heading {
	display: flex;
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
}

.composition-structure {
	font-weight: 600;
}

.composition-status {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 1px 8px;
	border: 1px solid currentColor;
	border-radius: 12px;
	font-size: 0.8rem;
}

.status-queued {
	color: var(--color-text-maxcontrast);
}

.status-running {
	color: var(--color-primary-element);
}

.status-succeeded {
	color: var(--color-success);
}

.status-failed,
.status-stale {
	color: var(--color-error);
}

.composition-meta {
	display: flex;
	gap: 12px;
	flex-wrap: wrap;
	color: var(--color-text-maxcontrast);
	font-size: 0.9rem;
}

.composition-path {
	overflow-wrap: anywhere;
}

.composition-error {
	margin: 0;
	color: var(--color-error);
}

@media (max-width: 720px) {
	.composition-row {
		align-items: flex-start;
	}
}
</style>
