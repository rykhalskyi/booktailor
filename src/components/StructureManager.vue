<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import ConfirmDialog from './ConfirmDialog.vue'
import MoveChapterDialog from './MoveChapterDialog.vue'
import TextInputDialog from './TextInputDialog.vue'
import { useLibrary } from '../stores/useLibrary'
import type { Chapter, Structure, StructureEntry } from '../types'
import { moveTargetsFor } from '../utils/movement'
import { flattenChapters } from '../utils/tree'

interface EditorRow {
	chapter: Chapter
	depth: number
	included: boolean
}

interface PreviewRow {
	chapter: Chapter
	depth: number
}

type ManagerDialog =
	| { type: 'create' }
	| { type: 'rename', structure: Structure }
	| { type: 'delete', structure: Structure }
	| null

const {
	chapters,
	closeStructureManager,
	clearError,
	createStructure,
	deleteStructure,
	editorChapters,
	editorEntries,
	editorLoading,
	editorStructureId,
	error,
	loadStructureEditor,
	loading,
	moveChapter,
	renameStructure,
	saveStructureEntries,
	selectedBookId,
	selectedStructure,
	selectedStructureId,
	selectStructure,
	structures,
} = useLibrary()

const managerDialog = ref<ManagerDialog>(null)
const moveChapterState = ref<Chapter | null>(null)

const editorReady = computed<boolean>(() => (
	selectedStructureId.value !== null
	&& editorStructureId.value === selectedStructureId.value
))

const editorFailed = computed<boolean>(() => (
	!editorLoading.chapters
	&& !editorLoading.entries
	&& selectedStructureId.value !== null
	&& !editorReady.value
))

function retryEditor(): void {
	if (selectedStructureId.value !== null) {
		loadStructureEditor(selectedStructureId.value)
	}
}

const entryByChapter = computed<Map<number, StructureEntry>>(() => new Map(
	editorEntries.value.map((entry) => [entry.chapterId, entry]),
))

function flattenEditorRows(nodes: Chapter[], depth = 0, rows: EditorRow[] = []): EditorRow[] {
	for (const chapter of nodes) {
		rows.push({
			chapter,
			depth,
			included: entryByChapter.value.get(chapter.id)?.included ?? false,
		})
		flattenEditorRows(chapter.children, depth + 1, rows)
	}

	return rows
}

const rows = computed<EditorRow[]>(() => flattenEditorRows(editorChapters.value))

function flattenPreviewRows(nodes: Chapter[], depth = 0, rows: PreviewRow[] = []): PreviewRow[] {
	for (const chapter of nodes) {
		rows.push({ chapter, depth })
		flattenPreviewRows(chapter.children, depth + 1, rows)
	}

	return rows
}

const previewRows = computed<PreviewRow[]>(() => flattenPreviewRows(chapters.value))

const numbersByChapter = computed<Map<number, string>>(() => {
	const numbers = new Map<number, string>()
	const walk = (nodes: Chapter[]): void => {
		for (const chapter of nodes) {
			if (chapter.number) {
				numbers.set(chapter.id, chapter.number)
			}
			walk(chapter.children)
		}
	}
	walk(chapters.value)

	return numbers
})

watch([selectedBookId, selectedStructureId], ([bookId, structureId]) => {
	if (bookId !== null && structureId !== null) {
		loadStructureEditor(structureId)
	}
}, { immediate: true })

function onStructureChange(structure: Structure | null): void {
	managerDialog.value = null
	if (structure !== null) {
		selectStructure(structure.id)
	}
}

function positionOf(chapterId: number): number {
	return entryByChapter.value.get(chapterId)?.position ?? 0
}

function siblingsOf(chapterId: number): Chapter[] {
	const all = flattenChapters(editorChapters.value)
	const current = all.find((chapter) => chapter.id === chapterId)
	if (current === undefined) {
		return []
	}

	return all
		.filter((chapter) => chapter.parentId === current.parentId)
		.sort((a, b) => positionOf(a.id) - positionOf(b.id) || a.id - b.id)
}

function canMove(chapterId: number, direction: number): boolean {
	const siblings = siblingsOf(chapterId)
	const index = siblings.findIndex((chapter) => chapter.id === chapterId)
	const target = index + direction

	return index !== -1 && target >= 0 && target < siblings.length
}

function canMoveChapter(chapter: Chapter): boolean {
	return moveTargetsFor(chapter, editorChapters.value).length > 0
}

function openMoveChapter(chapter: Chapter): void {
	moveChapterState.value = chapter
}

async function submitMove(parentId: number | null): Promise<void> {
	const chapter = moveChapterState.value
	if (chapter === null) {
		return
	}
	if (await moveChapter(chapter.id, parentId)) {
		moveChapterState.value = null
	}
}

async function persist(next: StructureEntry[]): Promise<void> {
	const structureId = selectedStructureId.value
	if (structureId === null || !editorReady.value) {
		return
	}

	const previous = editorEntries.value
	editorEntries.value = next
	const saved = await saveStructureEntries(structureId, next)
	if (selectedStructureId.value !== structureId) {
		return
	}
	if (!saved) {
		editorEntries.value = previous
	}
}

function toggleIncluded(chapterId: number, event: Event): void {
	if (selectedStructure.value?.isDefault) {
		return
	}

	const included = (event.target as HTMLInputElement).checked
	const next = editorEntries.value.map((entry) => (
		entry.chapterId === chapterId ? { ...entry, included } : entry
	))
	persist(next)
}

function moveSibling(chapterId: number, direction: number): void {
	const siblings = siblingsOf(chapterId)
	const index = siblings.findIndex((chapter) => chapter.id === chapterId)
	const target = index + direction
	if (index === -1 || target < 0 || target >= siblings.length) {
		return
	}

	const reordered = [...siblings]
	const [moved] = reordered.splice(index, 1)
	reordered.splice(target, 0, moved)
	const positions = new Map(reordered.map((chapter, position) => [chapter.id, position + 1]))
	const next = editorEntries.value.map((entry) => (
		positions.has(entry.chapterId)
			? { ...entry, position: positions.get(entry.chapterId) ?? entry.position }
			: entry
	))
	persist(next)
}

function openRename(): void {
	if (selectedStructure.value !== null) {
		managerDialog.value = { type: 'rename', structure: selectedStructure.value }
	}
}

function openDelete(): void {
	if (selectedStructure.value !== null && !selectedStructure.value.isDefault) {
		managerDialog.value = { type: 'delete', structure: selectedStructure.value }
	}
}

async function submitCreate(name: string): Promise<void> {
	if (await createStructure(name)) {
		managerDialog.value = null
	}
}

async function submitRename(name: string): Promise<void> {
	const state = managerDialog.value
	if (state?.type !== 'rename') {
		return
	}
	if (await renameStructure(state.structure, name)) {
		managerDialog.value = null
	}
}

async function submitDelete(): Promise<void> {
	const state = managerDialog.value
	if (state?.type !== 'delete') {
		return
	}
	if (await deleteStructure(state.structure)) {
		managerDialog.value = null
	}
}

function closeManagerDialog(): void {
	managerDialog.value = null
	clearError()
}

function closeMoveChapter(): void {
	moveChapterState.value = null
	clearError()
}
</script>

<template>
	<div class="structure-manager">
		<header class="manager-header">
			<NcButton @click="closeStructureManager">
				Back to book
			</NcButton>
			<h2 class="manager-title">
				Structures
			</h2>
		</header>

		<NcNoteCard
			v-if="error && !managerDialog"
			type="error"
			:text="error"
			class="manager-error" />

		<div class="manager-toolbar">
			<div class="structure-picker">
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
			<NcButton :disabled="loading.mutation" @click="managerDialog = { type: 'create' }">
				New structure
			</NcButton>
			<NcButton
				:disabled="loading.mutation || selectedStructure === null"
				@click="openRename">
				Rename
			</NcButton>
			<NcButton
				variant="error"
				:disabled="loading.mutation || selectedStructure === null || selectedStructure.isDefault"
				@click="openDelete">
				Delete
			</NcButton>
		</div>

		<div v-if="editorLoading.chapters || editorLoading.entries" class="manager-loading">
			<NcLoadingIcon name="Loading structure" :size="32" />
		</div>

		<div v-else-if="editorFailed" class="manager-loading manager-loading--failed">
			<NcNoteCard
				type="error"
				text="The structure could not be loaded. No changes can be saved until it loads." />
			<NcButton @click="retryEditor">
				Retry
			</NcButton>
		</div>

		<div v-else class="manager-layout">
			<section class="manager-panel" aria-label="Structure chapters">
				<h3 class="panel-title">
					Chapters
				</h3>
				<p v-if="selectedStructure?.isDefault" class="panel-hint">
					The default structure always includes every chapter.
				</p>
				<ul class="entry-list">
					<li
						v-for="row in rows"
						:key="row.chapter.id"
						class="entry-row"
						:style="{ paddingInlineStart: `${12 + row.depth * 18}px` }">
						<input
							type="checkbox"
							class="entry-toggle"
							:checked="row.included"
							:disabled="loading.mutation || !editorReady || selectedStructure?.isDefault"
							:aria-label="`Include ${row.chapter.name}`"
							@change="toggleIncluded(row.chapter.id, $event)">
						<span v-if="numbersByChapter.get(row.chapter.id)" class="entry-number">
							{{ numbersByChapter.get(row.chapter.id) }}
						</span>
						<span class="entry-name">{{ row.chapter.name }}</span>
						<div class="entry-actions">
							<NcButton
								variant="tertiary"
								:disabled="loading.mutation || !editorReady || !canMoveChapter(row.chapter)"
								@click="openMoveChapter(row.chapter)">
								Move
							</NcButton>
							<NcButton
								variant="tertiary"
								:disabled="loading.mutation || !editorReady || !canMove(row.chapter.id, -1)"
								@click="moveSibling(row.chapter.id, -1)">
								Up
							</NcButton>
							<NcButton
								variant="tertiary"
								:disabled="loading.mutation || !editorReady || !canMove(row.chapter.id, 1)"
								@click="moveSibling(row.chapter.id, 1)">
								Down
							</NcButton>
						</div>
					</li>
				</ul>
			</section>

			<section class="manager-panel manager-panel--preview" aria-label="Structure preview">
				<h3 class="panel-title">
					Numbering preview
				</h3>
				<ul class="preview-list">
					<li
						v-for="row in previewRows"
						:key="row.chapter.id"
						class="preview-row"
						:style="{ paddingInlineStart: `${12 + row.depth * 18}px` }">
						<span class="preview-number">{{ row.chapter.number }}</span>
						<span class="preview-name">{{ row.chapter.name }}</span>
					</li>
				</ul>
			</section>
		</div>

		<TextInputDialog
			v-if="managerDialog?.type === 'create'"
			title="New structure"
			label="Structure name"
			confirm-label="Create structure"
			:busy="loading.mutation"
			:error="error"
			@confirm="submitCreate"
			@close="closeManagerDialog" />
		<TextInputDialog
			v-else-if="managerDialog?.type === 'rename'"
			title="Rename structure"
			label="Structure name"
			:initial-value="managerDialog.structure.name"
			confirm-label="Rename structure"
			:busy="loading.mutation"
			:error="error"
			@confirm="submitRename"
			@close="closeManagerDialog" />
		<ConfirmDialog
			v-else-if="managerDialog?.type === 'delete'"
			title="Delete structure"
			:message="`Delete the “${managerDialog.structure.name}” structure? Chapters and assets are not deleted.`"
			confirm-label="Delete structure"
			:busy="loading.mutation"
			:error="error"
			@confirm="submitDelete"
			@close="closeManagerDialog" />

		<MoveChapterDialog
			v-if="moveChapterState !== null"
			:key="moveChapterState.id"
			:chapter="moveChapterState"
			:chapters="editorChapters"
			:busy="loading.mutation"
			:error="error"
			@confirm="submitMove"
			@close="closeMoveChapter" />
	</div>
</template>

<style scoped>
.structure-manager {
	display: flex;
	flex-direction: column;
	gap: 16px;
	box-sizing: border-box;
	width: 100%;
	height: 100%;
	padding: 16px;
	min-height: 0;
	overflow: auto;
}

.manager-header {
	display: flex;
	align-items: center;
	gap: 16px;
}

.manager-title {
	margin: 0;
	font-size: 1.2rem;
}

.manager-error {
	flex-shrink: 0;
}

.manager-toolbar {
	display: flex;
	align-items: center;
	justify-content: flex-start;
	gap: 12px;
	flex-wrap: wrap;
}

.structure-picker {
	min-width: 220px;
	flex: 0 1 320px;
}

.manager-loading {
	display: flex;
	align-items: center;
	justify-content: center;
	flex: 1;
	min-height: 240px;
}

.manager-loading--failed {
	flex-direction: column;
	gap: 16px;
	padding: 24px;
}

.manager-layout {
	display: grid;
	grid-template-columns: minmax(0, 1fr) minmax(240px, 0.6fr);
	gap: 16px;
	flex: 1;
	min-height: 0;
}

.manager-panel {
	display: flex;
	flex-direction: column;
	min-height: 0;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	overflow: hidden;
}

.panel-title {
	margin: 0;
	padding: 14px 16px;
	border-bottom: 1px solid var(--color-border);
	font-size: 1rem;
}

.panel-hint {
	margin: 0;
	padding: 10px 16px;
	color: var(--color-text-maxcontrast);
	font-size: 0.9rem;
}

.entry-list,
.preview-list {
	list-style: none;
	margin: 0;
	padding: 0;
	overflow: auto;
}

.entry-row,
.preview-row {
	display: flex;
	align-items: center;
	gap: 8px;
	min-height: 42px;
	padding-block: 4px;
	padding-inline-end: 12px;
	border-bottom: 1px solid var(--color-border);
}

.entry-row:hover,
.preview-row:hover {
	background: var(--color-background-hover);
}

.entry-toggle {
	flex-shrink: 0;
}

.entry-number,
.preview-number {
	flex-shrink: 0;
	min-width: 42px;
	color: var(--color-text-maxcontrast);
	font-variant-numeric: tabular-nums;
}

.entry-name,
.preview-name {
	flex: 1;
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.entry-actions {
	display: flex;
	gap: 4px;
	flex-shrink: 0;
}

@media (max-width: 900px) {
	.manager-layout {
		grid-template-columns: minmax(0, 1fr);
		grid-template-rows: minmax(240px, 1fr) minmax(180px, 0.5fr);
	}
}
</style>
