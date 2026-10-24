import type { InjectionKey, Ref } from 'vue'

export interface TreeFocus {
	focusedChapterId: Ref<number | null>
	setFocusedChapter: (chapterId: number) => void
}

export const treeFocusKey: InjectionKey<TreeFocus> = Symbol('booktailor-tree-focus')
