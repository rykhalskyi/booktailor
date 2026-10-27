<script setup lang="ts">
import type { AiAction, AiPrompt } from '../types'
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import AiProposalCard from './AiProposalCard.vue'
import { api } from '../api/client'
import { createAiChat } from '../composables/useAiChat'
import { useLibrary } from '../stores/useLibrary'

const {
	loadAssets,
	assetsByChapter,
	loadTags,
	loadTree,
	selectedAssets,
	selectedBook,
	selectedBookId,
	selectedChapter,
	selectedChapterId,
	selectedStructure,
	selectedStructureId,
} = useLibrary()

const chat = createAiChat()
const input = ref('')
const localError = ref<string | null>(null)
const messagesEl = ref<HTMLElement | null>(null)
interface PromptOption {
	key?: string
	id?: number
	label: string
	promptText: string
}

const prompts = ref<AiPrompt[]>([])
const selectedPrompt = ref<PromptOption>({ key: 'chat', label: 'Free-form chat', promptText: '' })

const promptOptions = computed<PromptOption[]>(() => [
	{ key: 'chat', label: 'Free-form chat', promptText: '' },
	{ key: 'summarize', label: 'Summarize chapter', promptText: 'Summarize the selected chapter.' },
	{ key: 'extract-terms', label: 'Extract terms', promptText: 'Extract and define the key terms in the selected chapter.' },
	{ key: 'ask-book', label: 'Ask the book', promptText: 'Answer my question using the book as context.' },
	...prompts.value.map((prompt) => ({ id: prompt.id, label: prompt.name, promptText: prompt.text })),
])

function onPromptChange(prompt: PromptOption | null): void {
	if (prompt === null) {
		return
	}
	selectedPrompt.value = prompt
	input.value = prompt.promptText
}

async function loadPrompts(): Promise<void> {
	if (selectedBookId.value === null) {
		prompts.value = []
		return
	}
	try {
		prompts.value = await api.ai.prompts.list(selectedBookId.value)
	} catch {
		prompts.value = []
	}
}

onMounted(loadPrompts)

watch(selectedBookId, () => {
	chat.reset()
	loadPrompts()
	selectedPrompt.value = { key: 'chat', label: 'Free-form chat', promptText: '' }
})

watch(selectedStructureId, () => {
	chat.reset()
})

const contextLabel = computed(() => {
	const parts = [selectedBook.value?.name ?? 'No book']
	if (selectedStructure.value) {
		parts.push(selectedStructure.value.name)
	}
	if (selectedChapter.value) {
		parts.push(selectedChapter.value.name)
	}
	if (selectedAssets.value.length > 0) {
		parts.push(`${selectedAssets.value.length} asset${selectedAssets.value.length === 1 ? '' : 's'}`)
	}
	return parts.join(' / ')
})

watch(
	() => chat.messages.value.length,
	async () => {
		await nextTick()
		if (messagesEl.value) {
			messagesEl.value.scrollTop = messagesEl.value.scrollHeight
		}
	},
)

async function send(): Promise<void> {
	if (selectedBookId.value === null || input.value.trim() === '' || chat.loading.value) {
		return
	}
	localError.value = null
	const message = input.value.trim()
	input.value = ''
	await chat.send({
		bookId: selectedBookId.value,
		message,
		structureId: selectedStructureId.value,
		chapterId: selectedChapterId.value,
		promptKey: selectedPrompt.value.key ?? null,
		promptId: selectedPrompt.value.id ?? null,
		contextAssetIds: [],
	})
}

async function onDecided(action: AiAction): Promise<void> {
	chat.updateAction(action)
	if (action.status !== 'applied' || selectedBookId.value === null) {
		return
	}
	await loadTree(selectedBookId.value, selectedStructureId.value)
	await loadTags(selectedBookId.value)

	const chapterIds = new Set<number>()
	if (selectedChapterId.value !== null) {
		chapterIds.add(selectedChapterId.value)
	}
	const targetChapterId = Number(action.payload.chapterId ?? 0)
	if (targetChapterId > 0) {
		chapterIds.add(targetChapterId)
	}
	for (const chapterId of Object.keys(assetsByChapter.value)) {
		const parsed = Number(chapterId)
		if (parsed > 0) {
			chapterIds.add(parsed)
		}
	}
	await Promise.all([...chapterIds].map((chapterId) => loadAssets(chapterId)))
}

function onKeydown(event: KeyboardEvent): void {
	if (event.key === 'Enter' && !event.shiftKey) {
		event.preventDefault()
		send()
	}
}
</script>

<template>
	<aside :class="$style.panel" aria-label="AI assistant">
		<header :class="$style.header">
			<div>
				<h2 :class="$style.title">
					AI assistant
				</h2>
				<p :class="$style.context">
					{{ contextLabel }}
				</p>
			</div>
		</header>

		<div ref="messagesEl" :class="$style.messages">
			<div
				v-for="message in chat.messages.value"
				:key="message.id"
				:class="[$style.message, message.role === 'user' ? $style.userMessage : $style.assistantMessage]">
				<div :class="$style.messageRole">
					{{ message.role === 'user' ? 'You' : 'BookTailor AI' }}
				</div>
				<div :class="$style.messageContent">
					{{ message.content }}
				</div>
				<NcNoteCard
					v-for="(warning, index) in message.warnings"
					:key="index"
					type="warning"
					:text="warning" />
				<div v-if="message.proposals.length > 0" :class="$style.proposals">
					<AiProposalCard
						v-for="proposal in message.proposals"
						:key="proposal.id"
						:action="proposal"
						@decided="onDecided" />
				</div>
				<div v-if="message.traceId" :class="$style.trace">
					Langfuse trace: <code>{{ message.traceId }}</code>
				</div>
			</div>

			<div v-if="chat.loading.value" :class="$style.loadingRow">
				<NcLoadingIcon :size="20" />
				<span>The assistant is thinking…</span>
				<NcButton variant="tertiary" @click="chat.abort">
					Cancel
				</NcButton>
			</div>
		</div>

		<NcNoteCard v-if="localError" type="error" :text="localError" />
		<NcNoteCard v-if="chat.error.value" type="error" :text="chat.error.value" />
		<NcButton
			v-if="chat.error.value"
			variant="secondary"
			:disabled="chat.loading.value || chat.lastRequest.value === null"
			@click="chat.retry">
			Retry
		</NcButton>

		<div :class="$style.composer">
			<textarea
				v-model="input"
				:class="$style.input"
				rows="3"
				placeholder="Ask about the book or request a change…"
				:disabled="chat.loading.value"
				@keydown="onKeydown" />
			<div :class="$style.composerActions">
				<div :class="$style.promptPicker">
					<NcSelect
						:model-value="selectedPrompt"
						:options="promptOptions"
						label="label"
						input-label="Prompt"
						:clearable="false"
						:disabled="chat.loading.value"
						@update:model-value="onPromptChange" />
				</div>
				<NcButton variant="primary" :disabled="chat.loading.value || input.trim() === ''" @click="send">
					Send
				</NcButton>
			</div>
		</div>
	</aside>
</template>

<style module>
.panel {
	display: flex;
	flex-direction: column;
	gap: 12px;
	box-sizing: border-box;
	width: 380px;
	min-width: 300px;
	height: 100%;
	padding: 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 8px;
}

.title {
	margin: 0;
	font-size: 1.05rem;
}

.context {
	margin: 4px 0 0;
	font-size: var(--font-size-small, 12px);
	color: var(--color-text-maxcontrast);
}

.quick {
	display: flex;
	gap: 6px;
	flex-wrap: wrap;
}

.messages {
	flex: 1;
	min-height: 0;
	overflow-y: auto;
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding-inline-end: 4px;
}

.empty {
	color: var(--color-text-maxcontrast);
	font-size: var(--font-size-small, 13px);
	padding: 12px 0;
}

.message {
	display: flex;
	flex-direction: column;
	gap: 6px;
	padding: 10px;
	border-radius: var(--border-radius-large, 12px);
	border: 1px solid var(--color-border);
}

.userMessage {
	background: var(--color-primary-element-light, rgba(0, 130, 201, 0.08));
}

.assistantMessage {
	background: var(--color-background-hover);
}

.messageRole {
	font-size: 11px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	color: var(--color-text-maxcontrast);
}

.messageContent {
	white-space: pre-wrap;
	overflow-wrap: anywhere;
	font-size: var(--font-size-small, 13px);
}

.proposals {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.trace {
	font-size: 11px;
	color: var(--color-text-maxcontrast);
	word-break: break-all;
}

.loadingRow {
	display: flex;
	align-items: center;
	gap: 8px;
	color: var(--color-text-maxcontrast);
	font-size: var(--font-size-small, 13px);
}

.promptPicker {
	flex-shrink: 0;
}

.composer {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.input {
	box-sizing: border-box;
	width: 100%;
	resize: vertical;
	min-height: 64px;
	padding: 8px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
	font: inherit;
}

.input:focus {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 1px;
}

.composerActions {
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: 8px;
}
</style>
