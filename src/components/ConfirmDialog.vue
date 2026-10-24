<script setup lang="ts">
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'

withDefaults(defineProps<{
	title: string
	message: string
	confirmLabel?: string
	busy?: boolean
	error?: string | null
}>(), {
	confirmLabel: 'Delete',
	busy: false,
	error: null,
})

const emit = defineEmits<{
	(event: 'confirm'): void
	(event: 'close'): void
}>()

function close(): void {
	emit('close')
}

function confirm(): void {
	emit('confirm')
}
</script>

<template>
	<NcDialog
		:open="true"
		:name="title"
		size="small"
		@closing="close">
		<NcNoteCard v-if="error" type="error" :text="error" />
		<p class="confirm-message">
			{{ message }}
		</p>
		<template #actions>
			<NcButton variant="tertiary" :disabled="busy" @click="close">
				Cancel
			</NcButton>
			<NcButton variant="error" :disabled="busy" @click="confirm">
				{{ confirmLabel }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<style scoped>
.confirm-message {
	margin: 0;
	max-width: 420px;
	white-space: pre-line;
}
</style>
