<script setup lang="ts">
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { useLibrary } from '../stores/useLibrary'
import type { Structure } from '../types'

const props = defineProps<{
	chatOpen: boolean
}>()

const emit = defineEmits<{
	(event: 'update-chat-open', value: boolean): void
}>()

const { loading, openStructureManager, selectedStructure, selectStructure, structures } = useLibrary()

function onStructureChange(structure: Structure | null): void {
	selectStructure(structure?.id ?? null)
}
</script>

<template>
	<div class="library-toolbar">
		<div class="structure-selector">
			<NcSelect
				:model-value="selectedStructure"
				:options="structures"
				label="name"
				input-label="Structure"
				aria-label="Structure"
				:clearable="false"
				:searchable="false"
				@update:model-value="onStructureChange" />
		</div>
		<div class="library-toolbar-actions">
			<NcButton :disabled="loading.mutation" @click="openStructureManager">
				Manage structures
			</NcButton>
			<NcButton
				:variant="'secondary'"
				@click="emit('update-chat-open', !props.chatOpen)">
				{{ props.chatOpen ? 'Hide AI assistant' : 'AI assistant' }}
			</NcButton>
		</div>
	</div>
</template>

<style scoped>
.library-toolbar {
	display: flex;
	align-items: center;
	gap: 12px;
	flex-wrap: wrap;
}

.structure-selector {
	min-width: 220px;
	flex: 1 1 320px;
}

.library-toolbar-actions {
	display: flex;
	align-items: center;
	gap: 8px;
	margin-inline-start: auto;
}
</style>
