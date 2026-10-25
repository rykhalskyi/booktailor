<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { useLibrary } from '../stores/useLibrary'
import type { Version } from '../types'

const {
	capabilities,
	checkInCurrentRevision,
	closeHistory,
	historyAsset,
	historyBusy,
	historyError,
	historyLoading,
	historyPreview,
	historyPreviewLoading,
	historyVersions,
	previewVersion,
	restoreVersion,
} = useLibrary()

const message = ref('')
const pendingRestore = ref<string | null>(null)

const currentVersion = computed<Version | null>(() => historyVersions.value.find((version) => version.isCurrent) ?? null)
const historicalVersions = computed<Version[]>(() => historyVersions.value.filter((version) => !version.isCurrent))

watch(historyAsset, () => {
	message.value = ''
	pendingRestore.value = null
})

function formatDate(value: string): string {
	const date = new Date(value)
	if (Number.isNaN(date.getTime())) {
		return value
	}

	return date.toLocaleString()
}

function formatSize(bytes: number): string {
	if (bytes < 1024) {
		return `${bytes} B`
	}
	if (bytes < 1024 * 1024) {
		return `${(bytes / 1024).toFixed(1)} KB`
	}

	return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

async function submitCheckIn(): Promise<void> {
	const asset = historyAsset.value
	if (asset === null) {
		return
	}

	if (await checkInCurrentRevision(asset, message.value)) {
		message.value = ''
	}
}

async function togglePreview(version: Version): Promise<void> {
	const asset = historyAsset.value
	if (asset !== null) {
		await previewVersion(asset, version.revisionId)
	}
}

async function confirmRestore(version: Version): Promise<void> {
	const asset = historyAsset.value
	if (asset === null) {
		return
	}

	if (await restoreVersion(asset, version.revisionId)) {
		pendingRestore.value = null
	}
}
</script>

<template>
	<NcDialog
		:open="historyAsset !== null"
		:name="historyAsset ? `History — ${historyAsset.fileName}` : 'History'"
		size="large"
		@closing="closeHistory">
		<div class="history">
			<NcNoteCard v-if="historyError" type="error" :text="historyError" />
			<NcNoteCard
				v-if="!capabilities.versions"
				type="warning"
				text="File versions are not available on this server. History and check-in messages are disabled." />

			<div v-if="historyAsset" class="history-section">
				<h3 class="history-heading">
					Current revision
				</h3>
				<div v-if="currentVersion" class="version-current">
					<div class="version-meta">
						<span class="version-badge">Current</span>
						<span>{{ formatDate(currentVersion.createdAt) }}</span>
						<span v-if="currentVersion.size > 0">{{ formatSize(currentVersion.size) }}</span>
					</div>
					<p v-if="currentVersion.message" class="version-message">
						{{ currentVersion.message }}
					</p>
					<p v-else class="version-message version-message--empty">
						No check-in message on the current revision.
					</p>
				</div>
				<p v-else class="version-message version-message--empty">
					The current revision is not available.
				</p>
				<form class="checkin-form" @submit.prevent="submitCheckIn">
					<NcTextField
						v-model="message"
						label="Check-in message (optional)"
						:disabled="historyBusy || !capabilities.versions" />
					<NcButton
						variant="primary"
						:disabled="historyBusy || !capabilities.versions"
						@click="submitCheckIn">
						Check in
					</NcButton>
				</form>
			</div>

			<div v-if="capabilities.versions" class="history-section">
				<h3 class="history-heading">
					Previous versions
				</h3>
				<div v-if="historyLoading" class="history-state">
					<NcLoadingIcon name="Loading versions" />
				</div>
				<p v-else-if="historicalVersions.length === 0" class="history-empty">
					No previous versions yet. A version is created when an edited asset is saved.
				</p>
				<ul v-else class="version-list">
					<li v-for="version in historicalVersions" :key="version.revisionId" class="version-row">
						<div class="version-meta">
							<span>{{ formatDate(version.createdAt) }}</span>
							<span v-if="version.author">{{ version.author }}</span>
							<span>{{ formatSize(version.size) }}</span>
						</div>
						<p v-if="version.message" class="version-message">
							{{ version.message }}
						</p>
						<div class="version-actions">
							<NcButton :disabled="historyPreviewLoading" @click="togglePreview(version)">
								{{ historyPreview?.revisionId === version.revisionId ? 'Hide preview' : 'Preview' }}
							</NcButton>
							<NcButton
								variant="error"
								:disabled="historyBusy || pendingRestore !== null"
								@click="pendingRestore = version.revisionId">
								Restore
							</NcButton>
						</div>
						<div v-if="pendingRestore === version.revisionId" class="restore-confirm">
							<span>Restore this version and make it the current content?</span>
							<div class="restore-actions">
								<NcButton :disabled="historyBusy" @click="pendingRestore = null">
									Cancel
								</NcButton>
								<NcButton variant="error" :disabled="historyBusy" @click="confirmRestore(version)">
									Restore version
								</NcButton>
							</div>
						</div>
						<pre v-if="historyPreview?.revisionId === version.revisionId" class="version-preview">{{ historyPreview?.content }}</pre>
					</li>
				</ul>
			</div>
		</div>
	</NcDialog>
</template>

<style scoped>
.history {
	display: flex;
	flex-direction: column;
	gap: 20px;
	min-width: min(640px, 80vw);
}

.history-section {
	display: flex;
	flex-direction: column;
	gap: 10px;
}

.history-heading {
	margin: 0;
	font-size: 1rem;
}

.version-current {
	padding: 10px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-background-hover);
}

.version-meta {
	display: flex;
	flex-wrap: wrap;
	gap: 10px;
	color: var(--color-text-maxcontrast);
	font-size: 0.9rem;
}

.version-badge {
	padding: 0 8px;
	border-radius: 10px;
	background: var(--color-primary-element-light);
	color: var(--color-main-text);
	font-weight: 600;
}

.version-message {
	margin: 6px 0 0;
	white-space: pre-line;
}

.version-message--empty {
	color: var(--color-text-maxcontrast);
}

.checkin-form {
	display: flex;
	align-items: flex-end;
	gap: 8px;
}

.checkin-form :deep(.nc-text-field) {
	flex: 1;
}

.version-list {
	list-style: none;
	margin: 0;
	padding: 0;
	display: flex;
	flex-direction: column;
	gap: 10px;
}

.version-row {
	display: flex;
	flex-direction: column;
	gap: 6px;
	padding: 10px 12px;
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius);
}

.version-actions,
.restore-actions {
	display: flex;
	gap: 8px;
	flex-wrap: wrap;
}

.restore-confirm {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	padding: 8px 10px;
	border-radius: var(--border-radius);
	background: var(--color-error-hover);
	flex-wrap: wrap;
}

.version-preview {
	max-height: 240px;
	margin: 6px 0 0;
	padding: 10px;
	border-radius: var(--border-radius);
	background: var(--color-background-dark);
	font-size: 0.85rem;
	white-space: pre-wrap;
	overflow: auto;
}

.history-state {
	display: flex;
	justify-content: center;
	padding: 16px;
}

.history-empty {
	margin: 0;
	color: var(--color-text-maxcontrast);
}
</style>
