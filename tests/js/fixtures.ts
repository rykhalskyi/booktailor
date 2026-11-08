import type { Book, BookRole } from '../../src/types'

export function testBook(id = 1, role: BookRole = 'owner'): Book {
	return {
		id,
		name: `Book ${id}`,
		description: null,
		rootFileId: id,
		path: `/BookTailor/Book ${id}`,
		role,
		owner: {
			id: 'owner',
			displayName: 'Owner',
		},
		createdAt: '2026-01-01T00:00:00+00:00',
		updatedAt: '2026-01-01T00:00:00+00:00',
	}
}
