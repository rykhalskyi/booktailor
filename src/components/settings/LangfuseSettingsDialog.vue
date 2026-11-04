<script setup lang="ts">
import type { LangfuseSettings, LangfuseSettingsPayload } from '../../types'
import { computed, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { api, ApiError } from '../../api/client'

const props = defineProps<{
	open: boolean
	config: LangfuseSettings | null
}>()

const emit = defineEmits<{
	'update:open': [open: boolean]
	saved: [config: LangfuseSettings]
	remove: []
}>()

const url = ref('')
const publicKey = ref('')
const secretKey = ref('')
const promptLabel = ref('production')
const saving = ref(false)
const error = ref<string | null>(null)

const hasStoredSecret = computed(() => props.config?.secretConfigured ?? false)
const canSubmit = computed(() => !saving.value
	&& url.value.trim() !== ''
	&& publicKey.value.trim() !== ''
	&& (hasStoredSecret.value || secretKey.value.trim() !== ''))

function messageFor(errorValue: unknown, fallback: string): string {
	return errorValue instanceof ApiError ? errorValue.message : fallback
}

watch(
	() => props.open,
	(open) => {
		if (!open) {
			return
		}
		error.value = null
		saving.value = false
		const config = props.config
		url.value = config?.url ?? ''
		publicKey.value = config?.publicKey ?? ''
		secretKey.value = ''
		promptLabel.value = config?.promptLabel === '' || config?.promptLabel === undefined
			? 'production'
			: config.promptLabel
	},
	{ immediate: true },
)

async function submit(): Promise<void> {
	if (!canSubmit.value) {
		return
	}
	saving.value = true
	error.value = null
	try {
		const payload: LangfuseSettingsPayload = {
			url: url.value.trim(),
			publicKey: publicKey.value.trim(),
			promptLabel: promptLabel.value.trim(),
		}
		if (secretKey.value.trim() !== '') {
			payload.secretKey = secretKey.value.trim()
		}
		emit('saved', await api.ai.langfuse.update(payload))
		emit('update:open', false)
	} catch (errorValue) {
		error.value = messageFor(errorValue, t('booktailor', 'Could not save Langfuse settings.'))
	} finally {
		saving.value = false
	}
}

function requestRemove(): void {
	emit('remove')
	emit('update:open', false)
}
</script>

<template>
	<NcDialog
		:name="props.config?.configured ? t('booktailor', 'Edit Langfuse configuration') : t('booktailor', 'Configure Langfuse')"
		:open="props.open"
		size="normal"
		is-form
		@submit="submit"
		@update:open="emit('update:open', $event)">
		<div :class="$style.form">
			<NcNoteCard v-if="error" type="error" :text="error" />

			<NcTextField
				v-model="url"
				:label="t('booktailor', 'Langfuse URL')"
				placeholder="https://langfuse.example.com"
				:disabled="saving"
				:helper-text="t('booktailor', 'Base URL of your Langfuse instance. Use http:// or https://.')" />
			<NcTextField
				v-model="publicKey"
				:label="t('booktailor', 'Public key')"
				placeholder="pk-lf-…"
				:disabled="saving"
				:helper-text="t('booktailor', 'Project public key from Langfuse.')" />
			<NcTextField
				v-model="secretKey"
				type="password"
				:label="t('booktailor', 'Secret key')"
				:placeholder="hasStoredSecret ? '••••••••' : 'sk-lf-…'"
				:disabled="saving"
				:helper-text="hasStoredSecret
					? t('booktailor', 'A secret key is stored. Leave empty to keep it.')
					: t('booktailor', 'Project secret key from Langfuse.')" />
			<NcTextField
				v-model="promptLabel"
				:label="t('booktailor', 'Prompt label')"
				placeholder="production"
				:disabled="saving"
				:helper-text="t('booktailor', 'Label used when fetching prompts, for example production.')" />
		</div>

		<template #actions>
			<NcButton
				v-if="props.config?.configured"
				type="button"
				variant="error"
				:disabled="saving"
				@click="requestRemove">
				{{ t('booktailor', 'Remove configuration') }}
			</NcButton>
			<NcButton
				type="button"
				variant="secondary"
				:disabled="saving"
				@click="emit('update:open', false)">
				{{ t('booktailor', 'Cancel') }}
			</NcButton>
			<NcButton type="submit" variant="primary" :disabled="!canSubmit">
				{{ t('booktailor', 'Save') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<style module>
.form {
	display: flex;
	flex-direction: column;
	gap: 16px;
}
</style>
