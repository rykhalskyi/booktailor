<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { useLibrary } from '../stores/useLibrary'
import type { CompositionRule, Structure, Tag } from '../types'

interface RuleDraft {
	tagId: number | null
	fallback: boolean
}

const {
	closeCompose,
	compositionRules,
	createComposition,
	error,
	loading,
	selectedStructureId,
	setCompositionRules,
	structures,
	tags,
} = useLibrary()

const structureId = ref<number | null>(selectedStructureId.value)
const outputName = ref('')
const rules = ref<RuleDraft[]>(
	[...compositionRules.value]
		.sort((left, right) => left.priority - right.priority)
		.map((rule) => ({ tagId: rule.tagId, fallback: rule.fallback })),
)

watch(rules, (value) => {
	setCompositionRules(value.flatMap((rule, index) => rule.tagId === null
		? []
		: [{ tagId: rule.tagId, priority: index + 1, fallback: rule.fallback }]))
}, { deep: true })

const selectedStructure = computed<Structure | null>(() => (
	structures.value.find((structure) => structure.id === structureId.value) ?? null
))

const tagById = computed<Map<number, Tag>>(() => new Map(
	tags.value.map((tag) => [tag.id, tag]),
))

const hasMissingTag = computed<boolean>(() => rules.value.some((rule) => rule.tagId === null))

const hasDuplicateTag = computed<boolean>(() => {
	const ids = rules.value
		.map((rule) => rule.tagId)
		.filter((id): id is number => id !== null)

	return new Set(ids).size !== ids.length
})

const canSubmit = computed<boolean>(() => (
	structureId.value !== null
	&& !loading.mutation
	&& !hasMissingTag.value
	&& !hasDuplicateTag.value
))

function onStructureChange(structure: Structure | null): void {
	structureId.value = structure?.id ?? null
}

function onTagChange(index: number, tag: Tag | null): void {
	setRuleTag(index, tag)
}

function tagFor(rule: RuleDraft): Tag | null {
	return rule.tagId === null ? null : tagById.value.get(rule.tagId) ?? null
}

function setRuleTag(index: number, tag: Tag | null): void {
	rules.value[index] = {
		...rules.value[index],
		tagId: tag?.id ?? null,
	}
}

function setFallback(index: number, event: Event): void {
	rules.value[index] = {
		...rules.value[index],
		fallback: (event.target as HTMLInputElement).checked,
	}
}

function addRule(): void {
	rules.value.push({ tagId: null, fallback: false })
}

function removeRule(index: number): void {
	rules.value.splice(index, 1)
}

function moveRule(index: number, direction: number): void {
	const target = index + direction
	if (target < 0 || target >= rules.value.length) {
		return
	}

	const reordered = [...rules.value]
	const [moved] = reordered.splice(index, 1)
	reordered.splice(target, 0, moved)
	rules.value = reordered
}

async function submit(): Promise<void> {
	if (!canSubmit.value || structureId.value === null) {
		return
	}

	const payload: CompositionRule[] = rules.value.map((rule, index) => ({
		tagId: rule.tagId as number,
		priority: index + 1,
		fallback: rule.fallback,
	}))

	await createComposition(
		structureId.value,
		payload,
		outputName.value.trim() === '' ? undefined : outputName.value.trim(),
	)
}
</script>

<template>
	<div class="compose-screen">
		<header class="compose-header">
			<div>
				<h1 class="compose-title">
					Compose
				</h1>
				<p class="compose-subtitle">
					Turn a structure into one Markdown document. Rules pick assets by tag,
					falling back to the next matching rule.
				</p>
			</div>
			<NcButton @click="closeCompose">
				Back to chapters
			</NcButton>
		</header>

		<NcNoteCard v-if="error" type="error" :text="error" />

		<section class="compose-form" aria-label="Composition settings">
			<div class="compose-row">
				<NcSelect
					:model-value="selectedStructure"
					:options="structures"
					label="name"
					input-label="Structure"
					aria-label="Structure"
					:clearable="false"
					:searchable="false"
					:disabled="loading.mutation"
					@update:model-value="onStructureChange" />
				<NcTextField
					v-model="outputName"
					label="Output name (optional)"
					:disabled="loading.mutation"
					:maxlength="255" />
			</div>

			<div class="rules-header">
				<div>
					<h2 class="rules-title">
						Tag rules
					</h2>
					<p class="rules-subtitle">
						Rules are evaluated top to bottom. Fallback rules are used only when
						no normal rule matches a chapter.
					</p>
				</div>
				<NcButton :disabled="loading.mutation || tags.length === 0" @click="addRule">
					Add rule
				</NcButton>
			</div>

			<NcNoteCard
				v-if="tags.length === 0"
				type="info"
				text="This book has no tags yet. With no rules, every asset in each included chapter is composed." />
			<NcNoteCard
				v-else-if="rules.length === 0"
				type="info"
				text="No rules yet. Every asset in each included chapter will be composed." />
			<NcNoteCard
				v-if="hasMissingTag"
				type="warning"
				text="Choose a tag for every rule before composing." />
			<NcNoteCard
				v-if="hasDuplicateTag"
				type="warning"
				text="Each tag may appear only once in the rule list." />

			<ol v-if="rules.length > 0" class="rule-list">
				<li v-for="(rule, index) in rules" :key="index" class="rule-row">
					<span class="rule-priority">{{ index + 1 }}</span>
					<div class="rule-tag">
						<NcSelect
							:model-value="tagFor(rule)"
							:options="tags"
							label="name"
							input-label="Tag"
							placeholder="Select a tag"
							aria-label="Tag"
							:clearable="false"
							:searchable="true"
							:disabled="loading.mutation"
							@update:model-value="(tag) => onTagChange(index, tag)" />
					</div>
					<label class="fallback-toggle">
						<input
							type="checkbox"
							:checked="rule.fallback"
							:disabled="loading.mutation"
							@change="(event) => setFallback(index, event)">
						Fallback
					</label>
					<div class="rule-actions">
						<NcButton
							:disabled="loading.mutation || index === 0"
							aria-label="Move rule up"
							@click="moveRule(index, -1)">
							Up
						</NcButton>
						<NcButton
							:disabled="loading.mutation || index === rules.length - 1"
							aria-label="Move rule down"
							@click="moveRule(index, 1)">
							Down
						</NcButton>
						<NcButton
							variant="error"
							:disabled="loading.mutation"
							aria-label="Remove rule"
							@click="removeRule(index)">
							Remove
						</NcButton>
					</div>
				</li>
			</ol>

			<div class="compose-actions">
				<NcButton
					variant="primary"
					:disabled="!canSubmit"
					@click="submit">
					Create composition
				</NcButton>
			</div>
		</section>
	</div>
</template>

<style scoped>
.compose-screen {
	display: flex;
	flex-direction: column;
	gap: 16px;
	box-sizing: border-box;
	width: 100%;
	height: 100%;
	padding: 16px;
	overflow: auto;
}

.compose-header,
.rules-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 16px;
	flex-wrap: wrap;
}

.compose-title,
.rules-title {
	margin: 0;
	font-size: 1.3rem;
}

.rules-title {
	font-size: 1.1rem;
}

.compose-subtitle,
.rules-subtitle {
	margin: 4px 0 0;
	color: var(--color-text-maxcontrast);
}

.compose-form {
	display: flex;
	flex-direction: column;
	gap: 16px;
	padding: 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.compose-row {
	display: grid;
	grid-template-columns: minmax(220px, 1fr) minmax(220px, 1fr);
	gap: 16px;
}

.rule-list {
	display: flex;
	flex-direction: column;
	gap: 8px;
	margin: 0;
	padding: 0;
	list-style: none;
}

.rule-row {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 8px 12px;
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius);
	flex-wrap: wrap;
}

.rule-priority {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 24px;
	height: 24px;
	border-radius: 50%;
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
	font-size: 0.85rem;
	font-weight: 600;
	flex-shrink: 0;
}

.rule-tag {
	min-width: 180px;
	flex: 1 1 220px;
}

.fallback-toggle {
	display: flex;
	align-items: center;
	gap: 6px;
	white-space: nowrap;
}

.rule-actions,
.compose-actions {
	display: flex;
	gap: 8px;
	flex-wrap: wrap;
}

.compose-actions {
	justify-content: flex-end;
}

@media (max-width: 720px) {
	.compose-row {
		grid-template-columns: 1fr;
	}
}
</style>
