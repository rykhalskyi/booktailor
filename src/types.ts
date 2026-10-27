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

export type CompositionStatus = 'queued' | 'running' | 'succeeded' | 'failed'

export interface CompositionRule {
	tagId: number
	priority: number
	fallback: boolean
}

export interface Composition {
	id: number
	bookId: number
	structureId: number | null
	structureName: string
	status: CompositionStatus
	rules: CompositionRule[]
	outputFileId: number | null
	outputPath: string | null
	error: string | null
	stale: boolean
	createdAt: string
	startedAt: string | null
	completedAt: string | null
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

export type AiProvider = 'deepseek' | 'siliconflow'

export interface AiProviderOption {
	id: AiProvider
	label: string
	defaultModel: string
	supported: boolean
}

export interface AiProfile {
	id: number
	name: string
	provider: AiProvider
	apiKeyMasked: string
	model: string | null
	connectTimeoutSeconds: number
	readTimeoutSeconds: number
	maxTokens: number
	isActive: boolean
	createdAt: string
	updatedAt?: string | null
}

export interface AiProfilePayload {
	name: string
	provider: AiProvider
	apiKey?: string
	model?: string | null
	connectTimeoutSeconds?: number
	readTimeoutSeconds?: number
	maxTokens?: number
	isActive?: boolean
}

export interface LangfuseStatus {
	configured: boolean
	url: string | null
}

export interface AiSettings {
	profiles: AiProfile[]
	contentLogging: boolean
	langfuse: LangfuseStatus
	providers: AiProviderOption[]
}

export type AiActionStatus = 'proposed' | 'applied' | 'rejected' | 'failed'

export type AiActionName =
	| 'create_asset'
	| 'update_asset'
	| 'delete_asset'
	| 'create_chapter'
	| 'rename_chapter'
	| 'delete_chapter'
	| 'create_structure'
	| 'rename_structure'
	| 'delete_structure'

export interface AiAction {
	id: number
	userId: string
	bookId: number | null
	action: AiActionName | string
	payload: Record<string, unknown>
	status: AiActionStatus
	createdAt: string
	decidedAt: string | null
	result: string | null
}

export interface AiChatHistoryMessage {
	role: 'user' | 'assistant'
	content: string
}

export interface AiChatRequest {
	bookId: number
	message: string
	structureId?: number | null
	chapterId?: number | null
	contextAssetIds?: number[]
	promptKey?: string | null
	promptId?: number | null
	history?: AiChatHistoryMessage[]
}

export interface AiChatResponse {
	message: string
	proposedActions: AiAction[]
	traceId: string | null
	warnings: string[]
	usage: Record<string, unknown>
}

export interface AiPrompt {
	id: number
	bookId: number
	name: string
	text: string
	source: 'user'
	createdAt: string
	updatedAt: string
}
