export interface Book {
	id: number
	name: string
	description: string | null
	rootFileId: number | null
	path: string | null
	createdAt: string
	updatedAt: string
}

export interface Chapter {
	id: number
	bookId: number
	parentId: number | null
	name: string
	fileId: number | null
	number?: string | null
	children: Chapter[]
	createdAt: string
	updatedAt: string
}

export interface Tag {
	id: number
	bookId: number
	name: string
	slug: string
	color: string
}

export interface Asset {
	id: number
	bookId: number
	chapterId: number
	name: string
	fileName: string
	fileId: number | null
	path: string | null
	tags: Tag[]
	createdAt: string
	updatedAt: string
}

export interface Version {
	revisionId: string
	author: string | null
	message: string | null
	size: number
	createdAt: string
	isCurrent: boolean
}

export interface Capabilities {
	editor: 'text' | null
	versions: boolean
}

export interface Structure {
	id: number
	bookId: number
	name: string
	isDefault: boolean
	createdAt: string
	updatedAt: string
}

export interface StructureEntry {
	chapterId: number
	included: boolean
	position: number
	effectiveParentId: number | null
}

export type DialogState =
	| { type: 'book-create' }
	| { type: 'chapter-create'; parentId: number | null }
	| { type: 'chapter-rename'; chapter: Chapter }
	| { type: 'chapter-delete'; chapter: Chapter }
	| { type: 'chapter-history'; chapter: Chapter }
	| { type: 'asset-create'; chapterId: number }
	| { type: 'asset-delete'; asset: Asset }
	| { type: 'asset-tags'; asset: Asset }
