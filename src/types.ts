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
	children: Chapter[]
	createdAt: string
	updatedAt: string
}

export interface Tag {
	id: number
	bookId: number
	name: string
	slug: string
}

export interface Asset {
	id: number
	bookId: number
	chapterId: number
	name: string
	fileName: string
	fileId: number | null
	tags: Tag[]
	createdAt: string
	updatedAt: string
}

export interface Structure {
	id: number
	bookId: number
	name: string
	isDefault: boolean
	createdAt: string
	updatedAt: string
}

export type DialogState =
	| { type: 'book-create' }
	| { type: 'chapter-create'; parentId: number | null }
	| { type: 'chapter-rename'; chapter: Chapter }
	| { type: 'chapter-delete'; chapter: Chapter }
	| { type: 'asset-create'; chapterId: number }
	| { type: 'asset-delete'; asset: Asset }
