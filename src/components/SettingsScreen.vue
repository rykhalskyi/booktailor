<script setup lang="ts">
import type { AiAction, AiProfile, AiPrompt, AiSettings, SettingsInfo } from '../types'
import { computed, onMounted, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import AiProfileCard from './settings/AiProfileCard.vue'
import AiProfileDialog from './settings/AiProfileDialog.vue'
import AiPromptDialog from './settings/AiPromptDialog.vue'
import { api, ApiError } from '../api/client'
import { formatFileSize } from '../utils/images'
import { useLibrary } from '../stores/useLibrary'

const { selectedBook, selectedBookId } = useLibrary()

const settings = ref<AiSettings | null>(null)
const general = ref<SettingsInfo | null>(null)
const actions = ref<AiAction[]>([])
const prompts = ref<AiPrompt[]>([])
const promptsLoading = ref(false)
const loading = ref(true)
const actionsLoading = ref(false)
const busy = ref(false)
const error = ref<string | null>(null)
const dialogOpen = ref(false)
const editing = ref<AiProfile | undefined>(undefined)
const promptDialogOpen = ref(false)
const editingPrompt = ref<AiPrompt | undefined>(undefined)

const profiles = computed(() => settings.value?.profiles ?? [])

function messageFor(errorValue: unknown, fallback: string): string {
	return errorValue instanceof ApiError ? errorValue.message : fallback
}

async function load(): Promise<void> {
	loading.value = true
	error.value = null
	try {
		const [ai, info] = await Promise.all([api.ai.settings(), api.settings()])
		settings.value = ai
		general.value = info
	} catch (errorValue) {
		error.value = messageFor(errorValue, t('booktailor', 'Could not load settings.'))
	} finally {
		loading.value = false
	}
}

function openStorageFolder(): void {
	const root = general.value?.storage.root
	if (root) {
		window.open(`${generateUrl('/apps/files/')}?dir=${encodeURIComponent(`/${root}`)}`, '_blank', 'noopener')
	}
}

async function loadActions(): Promise<void> {
	if (selectedBookId.value === null) {
		actions.value = []
		return
	}
	actionsLoading.value = true
	try {
		actions.value = await api.ai.actions.list(selectedBookId.value)
	} catch {
		actions.value = []
	} finally {
		actionsLoading.value = false
	}
}

async function loadPrompts(): Promise<void> {
	if (selectedBookId.value === null) {
		prompts.value = []
		return
	}
	promptsLoading.value = true
	try {
		prompts.value = await api.ai.prompts.list(selectedBookId.value)
	} catch {
		prompts.value = []
	} finally {
		promptsLoading.value = false
	}
}

onMounted(async () => {
	await Promise.all([load(), loadActions(), loadPrompts()])
})

watch(selectedBookId, () => {
	loadActions()
	loadPrompts()
})

function addProfile(): void {
	editing.value = undefined
	dialogOpen.value = true
}

function editProfile(profile: AiProfile): void {
	editing.value = profile
	dialogOpen.value = true
}

function addPrompt(): void {
	if (selectedBookId.value === null) {
		return
	}
	editingPrompt.value = undefined
	promptDialogOpen.value = true
}

function editPrompt(prompt: AiPrompt): void {
	editingPrompt.value = prompt
	promptDialogOpen.value = true
}

async function onPromptSaved(): Promise<void> {
	promptDialogOpen.value = false
	await loadPrompts()
}

async function selectProfile(profile: AiProfile): Promise<void> {
	if (busy.value) {
		return
	}
	busy.value = true
	error.value = null
	try {
		const updated = await api.ai.profiles.activate(profile.id, !profile.isActive)
		if (settings.value !== null) {
			settings.value = { ...settings.value, profiles: updated }
		}
	} catch (errorValue) {
		error.value = messageFor(errorValue, t('booktailor', 'Could not update the active profile.'))
	} finally {
		busy.value = false
	}
}

async function onSaved(): Promise<void> {
	dialogOpen.value = false
	await load()
}

async function onDeleted(): Promise<void> {
	dialogOpen.value = false
	await load()
}

async function onContentLoggingChange(value: boolean | string): Promise<void> {
	if (settings.value === null || busy.value) {
		return
	}
	const enabled = value === true || value === 'true'
	busy.value = true
	error.value = null
	try {
		const updated = await api.ai.updateSettings(enabled)
		settings.value = { ...settings.value, contentLogging: updated.contentLogging }
	} catch (errorValue) {
		error.value = messageFor(errorValue, t('booktailor', 'Could not update AI privacy settings.'))
	} finally {
		busy.value = false
	}
}

function actionLabel(action: AiAction): string {
	const summary = action.payload.summary
	if (typeof summary === 'string' && summary !== '') {
		return summary
	}
	return action.action.replaceAll('_', ' ')
}

function actionDate(action: AiAction): string {
	return new Date(action.createdAt).toLocaleString()
}
</script>

<template>
	<div :class="$style.settingsView">
		<div :class="$style.container">
			<header :class="$style.header">
				<div>
					<h2 :class="$style.title">
						{{ t('booktailor', 'Settings') }}
					</h2>
					<p :class="$style.subtitle">
						{{ t('booktailor', 'Manage AI providers, privacy, and observability for BookTailor.') }}
					</p>
				</div>
			</header>

			<NcNoteCard v-if="error" type="error" :text="error" />

			<div v-if="loading" :class="$style.loading">
				<NcLoadingIcon :size="32" />
				<span>{{ t('booktailor', 'Loading AI settings…') }}</span>
			</div>

			<template v-else>
				<section :class="$style.section">
					<div :class="$style.sectionHeader">
						<div>
							<h3 :class="$style.sectionTitle">
								{{ t('booktailor', 'Storage') }}
							</h3>
							<p :class="$style.sectionDesc">
								{{ t('booktailor', 'Books, chapters, assets, images, and compositions live in your Nextcloud files.') }}
							</p>
						</div>
						<NcButton :disabled="general === null" @click="openStorageFolder">
							{{ t('booktailor', 'Open in Files') }}
						</NcButton>
					</div>

					<dl :class="$style.infoGrid">
						<div :class="$style.infoItem">
							<dt :class="$style.infoLabel">
								{{ t('booktailor', 'Storage location') }}
							</dt>
							<dd :class="$style.infoValue">
								{{ t('booktailor', '{root}/ (in your files)', { root: general?.storage.root ?? 'BookTailor' }) }}
							</dd>
						</div>
						<div :class="$style.infoItem">
							<dt :class="$style.infoLabel">
								{{ t('booktailor', 'Upload limit') }}
							</dt>
							<dd :class="$style.infoValue">
								{{ general ? formatFileSize(general.limits.uploadBytes) : '—' }}
							</dd>
						</div>
						<div :class="$style.infoItem">
							<dt :class="$style.infoLabel">
								{{ t('booktailor', 'Image limit') }}
							</dt>
							<dd :class="$style.infoValue">
								{{ general ? formatFileSize(general.limits.imageUploadBytes) : '—' }}
								<span v-if="general" :class="$style.infoDetail">
									{{ general.limits.imageTypes.map((type) => type.replace('image/', '')).join(', ') }}
								</span>
							</dd>
						</div>
					</dl>
				</section>

				<section :class="$style.section">
					<div :class="$style.sectionHeader">
						<div>
							<h3 :class="$style.sectionTitle">
								{{ t('booktailor', 'Defaults and about') }}
							</h3>
							<p :class="$style.sectionDesc">
								{{ t('booktailor', 'These are the effective defaults in this release. No separate preference is stored.') }}
							</p>
						</div>
					</div>

					<dl :class="$style.infoGrid">
						<div :class="$style.infoItem">
							<dt :class="$style.infoLabel">
								{{ t('booktailor', 'Editor') }}
							</dt>
							<dd :class="$style.infoValue">
								{{ general?.defaults.editor === 'text' ? 'Nextcloud Text' : (general?.defaults.editor ?? 'Nextcloud Text') }}
							</dd>
						</div>
						<div :class="$style.infoItem">
							<dt :class="$style.infoLabel">
								{{ t('booktailor', 'Composition') }}
							</dt>
							<dd :class="$style.infoValue">
								{{ general?.defaults.compositionFormat === 'markdown' ? 'Markdown in compositions/' : (general?.defaults.compositionFormat ?? 'Markdown') }}
							</dd>
						</div>
						<div :class="$style.infoItem">
							<dt :class="$style.infoLabel">
								{{ t('booktailor', 'AI transport') }}
							</dt>
							<dd :class="$style.infoValue">
								{{ general?.defaults.aiTransport === 'json' ? 'Synchronous JSON' : (general?.defaults.aiTransport ?? 'JSON') }}
							</dd>
						</div>
						<div :class="$style.infoItem">
							<dt :class="$style.infoLabel">
								{{ t('booktailor', 'BookTailor') }}
							</dt>
							<dd :class="$style.infoValue">
								{{ general?.app.version ?? '—' }}
							</dd>
						</div>
						<div :class="$style.infoItem">
							<dt :class="$style.infoLabel">
								{{ t('booktailor', 'Nextcloud') }}
							</dt>
							<dd :class="$style.infoValue">
								{{ general?.app.nextcloudVersion ?? '—' }}
							</dd>
						</div>
					</dl>
				</section>

				<section :class="$style.section">
					<div :class="$style.sectionHeader">
						<div>
							<h3 :class="$style.sectionTitle">
								{{ t('booktailor', 'AI provider profiles') }}
							</h3>
							<p :class="$style.sectionDesc">
								{{ t('booktailor', 'Add a DeepSeek or SiliconFlow key and choose one active profile.') }}
							</p>
						</div>
						<NcButton variant="primary" :disabled="busy" @click="addProfile">
							{{ t('booktailor', 'Add AI profile') }}
						</NcButton>
					</div>

					<NcEmptyContent
						v-if="profiles.length === 0"
						:name="t('booktailor', 'No AI profiles configured')"
						:description="t('booktailor', 'Add an API key to enable the assistant. Keys are encrypted and never returned to the browser.')">
						<template #action>
							<NcButton variant="primary" :disabled="busy" @click="addProfile">
								{{ t('booktailor', 'Add AI profile') }}
							</NcButton>
						</template>
					</NcEmptyContent>

					<div v-else :class="$style.profileList">
						<AiProfileCard
							v-for="profile in profiles"
							:key="profile.id"
							:profile="profile"
							:disabled="busy"
							@select="selectProfile"
							@edit="editProfile" />
					</div>
				</section>

				<section :class="$style.section">
					<div :class="$style.sectionHeader">
						<div>
							<h3 :class="$style.sectionTitle">
								{{ t('booktailor', 'Saved prompts') }}
							</h3>
							<p :class="$style.sectionDesc">
								{{ t('booktailor', 'Reusable instructions for {book}.', { book: selectedBook?.name ?? t('booktailor', 'the selected book') }) }}
							</p>
						</div>
						<NcButton variant="primary" :disabled="selectedBookId === null || promptsLoading" @click="addPrompt">
							{{ t('booktailor', 'Add prompt') }}
						</NcButton>
					</div>

					<p v-if="selectedBookId === null" :class="$style.hint">
						{{ t('booktailor', 'Select a book to manage saved prompts.') }}
					</p>
					<div v-else-if="promptsLoading" :class="$style.loading">
						<NcLoadingIcon :size="24" />
						<span>{{ t('booktailor', 'Loading prompts…') }}</span>
					</div>
					<NcEmptyContent
						v-else-if="prompts.length === 0"
						:name="t('booktailor', 'No saved prompts')"
						:description="t('booktailor', 'Save a reusable instruction for this book.')" />
					<ul v-else :class="$style.promptList">
						<li v-for="prompt in prompts" :key="prompt.id" :class="$style.promptItem">
							<div :class="$style.promptMain">
								<strong>{{ prompt.name }}</strong>
								<span :class="$style.promptText">{{ prompt.text }}</span>
							</div>
							<NcButton variant="secondary" @click="editPrompt(prompt)">
								{{ t('booktailor', 'Edit') }}
							</NcButton>
						</li>
					</ul>
				</section>

				<section :class="$style.section">
					<div :class="$style.sectionHeader">
						<div>
							<h3 :class="$style.sectionTitle">
								{{ t('booktailor', 'Privacy and observability') }}
							</h3>
							<p :class="$style.sectionDesc">
								{{ t('booktailor', 'Langfuse traces are best-effort. Prompt and response content is sent only when you opt in.') }}
							</p>
						</div>
					</div>

					<NcCheckboxRadioSwitch
						:model-value="settings?.contentLogging ?? false"
						type="switch"
						:disabled="busy"
						@update:model-value="onContentLoggingChange">
						{{ t('booktailor', 'Send prompt and response content to Langfuse') }}
					</NcCheckboxRadioSwitch>

					<NcNoteCard
						:type="settings?.langfuse.configured ? 'success' : 'info'"
						:text="settings?.langfuse.configured
							? t('booktailor', 'Langfuse is configured for this app. Trace ids appear in chat responses.')
							: t('booktailor', 'Langfuse is not configured. Set the app-level URL, public key, and secret key in Nextcloud app config.')" />
					<p v-if="settings?.langfuse.url" :class="$style.hint">
						Langfuse: <a :href="settings.langfuse.url" target="_blank" rel="noopener noreferrer">{{ settings.langfuse.url }}</a>
					</p>
				</section>

				<section :class="$style.section">
					<div :class="$style.sectionHeader">
						<div>
							<h3 :class="$style.sectionTitle">
								{{ t('booktailor', 'AI action history') }}
							</h3>
							<p :class="$style.sectionDesc">
								{{ t('booktailor', 'Proposed, applied, rejected, and failed actions for {book}.', { book: selectedBook?.name ?? t('booktailor', 'the selected book') }) }}
							</p>
						</div>
					</div>

					<p v-if="selectedBookId === null" :class="$style.hint">
						{{ t('booktailor', 'Select a book to view its AI action history.') }}
					</p>
					<div v-else-if="actionsLoading" :class="$style.loading">
						<NcLoadingIcon :size="24" />
						<span>{{ t('booktailor', 'Loading history…') }}</span>
					</div>
					<NcEmptyContent
						v-else-if="actions.length === 0"
						:name="t('booktailor', 'No AI actions yet')"
						:description="t('booktailor', 'Approved, rejected, and proposed AI changes will appear here.')" />
					<ul v-else :class="$style.actionList">
						<li v-for="action in actions" :key="action.id" :class="$style.actionItem">
							<span :class="[$style.actionStatus, $style[`status${action.status}`]]">{{ action.status }}</span>
							<span :class="$style.actionLabel">{{ actionLabel(action) }}</span>
							<span :class="$style.actionDate">{{ actionDate(action) }}</span>
						</li>
					</ul>
				</section>
			</template>
		</div>

		<AiProfileDialog
			:open="dialogOpen"
			:entity="editing"
			@update:open="dialogOpen = $event"
			@created="onSaved"
			@updated="onSaved"
			@deleted="onDeleted" />
		<AiPromptDialog
			v-if="selectedBookId !== null"
			:open="promptDialogOpen"
			:book-id="selectedBookId"
			:entity="editingPrompt"
			@update:open="promptDialogOpen = $event"
			@created="onPromptSaved"
			@updated="onPromptSaved"
			@deleted="onPromptSaved" />
	</div>
</template>

<style module>
.settingsView {
	box-sizing: border-box;
	width: 100%;
	height: 100%;
	overflow: auto;
	padding: 16px;
}

.container {
	width: 100%;
	max-width: 860px;
	margin: 0 auto;
	display: flex;
	flex-direction: column;
	gap: 20px;
}

.header {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
}

.title {
	margin: 0;
	font-size: 1.5rem;
	font-weight: 700;
}

.subtitle {
	margin: 4px 0 0;
	color: var(--color-text-maxcontrast);
}

.section {
	display: flex;
	flex-direction: column;
	gap: 16px;
	padding: 20px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large, 12px);
	background: var(--color-main-background);
}

.sectionHeader {
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: 16px;
	flex-wrap: wrap;
}

.sectionTitle {
	margin: 0;
	font-size: 1.1rem;
	font-weight: 600;
}

.sectionDesc {
	margin: 4px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: var(--font-size-small, 13px);
}

.profileList {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.infoGrid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
	gap: 12px;
	margin: 0;
}

.infoItem {
	display: flex;
	flex-direction: column;
	gap: 3px;
	padding: 10px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-background-hover);
}

.infoLabel {
	color: var(--color-text-maxcontrast);
	font-size: var(--font-size-small, 12px);
}

.infoValue {
	margin: 0;
	font-weight: 600;
	overflow-wrap: anywhere;
}

.infoDetail {
	display: block;
	color: var(--color-text-maxcontrast);
	font-size: var(--font-size-small, 12px);
	font-weight: 400;
}

.loading {
	display: flex;
	align-items: center;
	gap: 12px;
	color: var(--color-text-maxcontrast);
	padding: 24px;
}

.hint {
	margin: 0;
	color: var(--color-text-maxcontrast);
	font-size: var(--font-size-small, 13px);
}

.promptList {
	list-style: none;
	margin: 0;
	padding: 0;
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.promptItem {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 10px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
}

.promptMain {
	flex: 1;
	min-width: 0;
	display: flex;
	flex-direction: column;
	gap: 3px;
}

.promptText {
	color: var(--color-text-maxcontrast);
	font-size: var(--font-size-small, 12px);
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.actionList {
	list-style: none;
	margin: 0;
	padding: 0;
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.actionItem {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 8px 10px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
}

.actionStatus {
	text-transform: uppercase;
	font-size: 11px;
	font-weight: 700;
	letter-spacing: 0.04em;
	padding: 2px 6px;
	border-radius: 8px;
	background: var(--color-background-darker);
}

.statusapplied {
	background: var(--color-success-background, rgba(45, 194, 107, 0.15));
	color: var(--color-success);
}

.statusrejected {
	background: var(--color-background-darker);
	color: var(--color-text-maxcontrast);
}

.statusfailed {
	background: var(--color-error-background, rgba(233, 59, 59, 0.15));
	color: var(--color-error);
}

.actionLabel {
	flex: 1;
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.actionDate {
	color: var(--color-text-maxcontrast);
	font-size: var(--font-size-small, 12px);
}
</style>
