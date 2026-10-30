<script setup lang="ts">
import type { AiAction } from '../types'
import type { DiffLine } from '../utils/diff'
import { computed, onMounted, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { api, ApiError } from '../api/client'
import { lineDiff } from '../utils/diff'

const props = defineProps<{
	action: AiAction
}>()

const emit = defineEmits<{
	decided: [action: AiAction]
	applied: [action: AiAction]
}>()

const busy = ref(false)
const error = ref<string | null>(null)
const oldContent = ref<string | null>(null)
const loadingOld = ref(false)

const payload = computed<Record<string, unknown>>(() => props.action.payload ?? {})
const summary = computed(() => typeof payload.value.summary === 'string' && payload.value.summary !== ''
	? payload.value.summary
	: props.action.action.replaceAll('_', ' '))
const content = computed(() => typeof payload.value.content === 'string' ? payload.value.content : '')
const diff = computed<DiffLine[]>(() => oldContent.value === null ? [] : lineDiff(oldContent.value, content.value))
const isUpdateWithContent = computed(() => props.action.action === 'update_asset' && content.value !== '')
const isProposed = computed(() => props.action.status === 'proposed')

onMounted(async () => {
	if (!isUpdateWithContent.value) {
		return
	}
	const assetId = Number(payload.value.assetId ?? 0)
	if (assetId <= 0) {
		return
	}
	loadingOld.value = true
	try {
		oldContent.value = (await api.assets.content(assetId)).content
	} catch {
		oldContent.value = null
	} finally {
		loadingOld.value = false
	}
})

function messageFor(errorValue: unknown, fallback: string): string {
	return errorValue instanceof ApiError ? errorValue.message : fallback
}

async function confirm(): Promise<void> {
	if (busy.value || !isProposed.value) {
		return
	}
	busy.value = true
	error.value = null
	try {
		const decided = await api.ai.actions.confirm(props.action.id)
		emit('decided', decided)
		if (decided.status === 'applied') {
			emit('applied', decided)
		}
	} catch (errorValue) {
		error.value = messageFor(errorValue, 'The AI action could not be applied.')
	} finally {
		busy.value = false
	}
}

async function reject(): Promise<void> {
	if (busy.value || !isProposed.value) {
		return
	}
	busy.value = true
	error.value = null
	try {
		emit('decided', await api.ai.actions.reject(props.action.id))
	} catch (errorValue) {
		error.value = messageFor(errorValue, 'The AI action could not be rejected.')
	} finally {
		busy.value = false
	}
}

function preview(value: unknown, max = 1600): string {
	if (typeof value !== 'string') {
		return ''
	}
	return value.length > max ? `${value.slice(0, max)}\n…` : value
}
</script>

<template>
	<div :class="$style.card">
		<div :class="$style.header">
			<span :class="[$style.badge, $style[`status${props.action.status}`]]">{{ props.action.status }}</span>
			<strong :class="$style.title">{{ summary }}</strong>
		</div>

		<NcNoteCard v-if="error" type="error" :text="error" />

		<div v-if="props.action.action === 'create_asset'" :class="$style.preview">
			<p><strong>New asset:</strong> {{ payload.name }} (chapter {{ payload.chapterId }})</p>
			<pre v-if="content">{{ preview(content) }}</pre>
		</div>
		<div v-else-if="isUpdateWithContent" :class="$style.preview">
			<p><strong>Update asset:</strong> #{{ payload.assetId }}</p>
			<div v-if="loadingOld" :class="$style.loadingRow">
				<NcLoadingIcon :size="20" />
				<span>Loading current content…</span>
			</div>
			<div v-else-if="diff.length === 0" :class="$style.loadingRow">
				<span>Current content is unavailable; the proposed content is shown below.</span>
			</div>
			<pre v-if="diff.length > 0"><span
				v-for="(line, index) in diff.slice(0, 300)"
				:key="index"
				:class="{
					[$style.added]: line.type === 'added',
					[$style.removed]: line.type === 'removed',
				}">{{ line.type === 'added' ? '+' : line.type === 'removed' ? '-' : ' ' }}{{ line.text }}
</span></pre>
			<pre v-else>{{ preview(content) }}</pre>
		</div>
		<div v-else :class="$style.preview">
			<p><strong>{{ props.action.action.replaceAll('_', ' ') }}:</strong> {{ preview(JSON.stringify(payload, null, 2), 1200) }}</p>
		</div>

		<div v-if="isProposed" :class="$style.actions">
			<NcButton variant="secondary" :disabled="busy" @click="reject">
				Reject
			</NcButton>
			<NcButton variant="primary" :disabled="busy" @click="confirm">
				<NcLoadingIcon v-if="busy" :size="18" />
				<span v-else>Approve</span>
			</NcButton>
		</div>
	</div>
</template>

<style module>
.card {
	display: flex;
	flex-direction: column;
	gap: 10px;
	padding: 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large, 12px);
	background: var(--color-main-background);
}

.header {
	display: flex;
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
}

.title {
	font-size: var(--font-size-small, 13px);
}

.badge {
	padding: 2px 6px;
	border-radius: 8px;
	font-size: 10px;
	font-weight: 700;
	letter-spacing: 0.04em;
	text-transform: uppercase;
	background: var(--color-background-darker);
}

.statusapplied {
	background: var(--color-success-background, rgba(45, 194, 107, 0.15));
	color: var(--color-success);
}

.statusrejected {
	color: var(--color-text-maxcontrast);
}

.preview {
	font-size: var(--font-size-small, 13px);
	color: var(--color-text-maxcontrast);
}

.preview p {
	margin: 0 0 6px;
}

.preview pre {
	margin: 0;
	max-height: 260px;
	overflow: auto;
	padding: 8px;
	border-radius: var(--border-radius);
	background: var(--color-background-dark);
	color: var(--color-main-text);
	white-space: pre-wrap;
	overflow-wrap: anywhere;
}

.added {
	color: var(--color-success);
}

.removed {
	color: var(--color-error);
}

.loadingRow {
	display: flex;
	align-items: center;
	gap: 8px;
}

.actions {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
}
</style>
