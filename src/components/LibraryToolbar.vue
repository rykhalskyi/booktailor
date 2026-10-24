<script setup lang="ts">
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { useLibrary } from '../stores/useLibrary'
import type { Structure } from '../types'

const { selectedStructure, selectStructure, structures } = useLibrary()

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
	</div>
</template>

<style scoped>
.library-toolbar {
	display: flex;
	align-items: center;
	justify-content: flex-end;
	gap: 12px;
	flex-wrap: wrap;
}

.structure-selector {
	min-width: 220px;
	flex: 0 1 320px;
}
</style>
