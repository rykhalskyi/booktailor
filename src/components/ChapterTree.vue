<script setup lang="ts">
import { provide, ref, watch } from 'vue'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { useLibrary } from '../stores/useLibrary'
import { findChapter } from '../utils/tree'
import { treeFocusKey } from '../utils/treeFocus'
import ChapterNode from './ChapterNode.vue'

const { chapters, expandedChapterIds, loading, toggleChapter } = useLibrary()

const treeRoot = ref<HTMLElement | null>(null)
const focusedChapterId = ref<number | null>(null)

provide(treeFocusKey, {
	focusedChapterId,
	setFocusedChapter: (chapterId: number): void => {
		focusedChapterId.value = chapterId
	},
})

watch(chapters, (value) => {
	if (focusedChapterId.value !== null && findChapter(value, focusedChapterId.value) !== null) {
		return
	}

	focusedChapterId.value = value[0]?.id ?? null
}, { immediate: true })

function chapterButtons(): HTMLButtonElement[] {
	return treeRoot.value === null
		? []
		: Array.from(treeRoot.value.querySelectorAll<HTMLButtonElement>('.chapter-name[data-chapter-id]'))
}

function focusChapter(chapterId: number): void {
	treeRoot.value
		?.querySelector<HTMLButtonElement>(`.chapter-name[data-chapter-id="${chapterId}"]`)
		?.focus()
}

function onKeydown(event: KeyboardEvent): void {
	const target = event.target
	if (!(target instanceof HTMLElement) || !target.classList.contains('chapter-name')) {
		return
	}

	const chapterId = Number(target.dataset.chapterId)
	const chapter = findChapter(chapters.value, chapterId)
	if (chapter === null) {
		return
	}

	const buttons = chapterButtons()
	const index = buttons.indexOf(target as HTMLButtonElement)
	const depth = Number(target.dataset.depth ?? 0)
	const isExpanded = expandedChapterIds.value[chapterId] ?? depth === 0

	switch (event.key) {
	case 'ArrowDown':
		event.preventDefault()
		buttons[index + 1]?.focus()
		break
	case 'ArrowUp':
		event.preventDefault()
		buttons[index - 1]?.focus()
		break
	case 'ArrowRight':
		event.preventDefault()
		if (chapter.children.length === 0) {
			break
		}
		if (!isExpanded) {
			toggleChapter(chapterId, false)
			break
		}
		focusChapter(chapter.children[0].id)
		break
	case 'ArrowLeft':
		event.preventDefault()
		if (chapter.children.length > 0 && isExpanded) {
			toggleChapter(chapterId, true)
			break
		}
		if (chapter.parentId !== null) {
			focusChapter(chapter.parentId)
		}
		break
	case 'Home':
		event.preventDefault()
		buttons[0]?.focus()
		break
	case 'End':
		event.preventDefault()
		buttons[buttons.length - 1]?.focus()
		break
	}
}
</script>

<template>
	<div ref="treeRoot" class="chapter-tree" @keydown="onKeydown">
		<div v-if="loading.tree" class="chapter-tree-state">
			<NcLoadingIcon name="Loading chapters" />
		</div>
		<NcEmptyContent
			v-else-if="chapters.length === 0"
			name="No chapters"
			description="Add a root chapter to start building the book." />
		<ul
			v-else
			class="chapter-tree-list"
			role="tree"
			aria-label="Chapters">
			<ChapterNode
				v-for="chapter in chapters"
				:key="chapter.id"
				:chapter="chapter"
				:depth="0" />
		</ul>
	</div>
</template>

<style scoped>
.chapter-tree {
	height: 100%;
	padding: 8px;
	overflow: auto;
}

.chapter-tree-list {
	list-style: none;
	margin: 0;
	padding: 0;
}

.chapter-tree-state {
	display: flex;
	align-items: center;
	justify-content: center;
	min-height: 120px;
}
</style>
