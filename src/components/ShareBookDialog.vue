<script setup lang="ts">
import { computed, ref } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcSelectUsers from '@nextcloud/vue/components/NcSelectUsers'
import { useLibrary } from '../stores/useLibrary'
import type { BookRole, BookShare, ShareeCandidate } from '../types'

type ShareRole = Exclude<BookRole, 'owner'>

interface ShareRoleOption {
	id: ShareRole
	label: string
}

interface CandidateOption extends ShareeCandidate {
	subname: string
	user: string
}

const {
	bookShares,
	closeShareDialog,
	createBookShare,
	revokeBookShare,
	searchShareCandidates,
	shareBusy,
	shareCandidates,
	shareCandidatesLoading,
	shareError,
	sharesLoading,
	updateBookShare,
} = useLibrary()

const roleOptions: ShareRoleOption[] = [
	{ id: 'read', label: t('booktailor', 'Read only') },
	{ id: 'write', label: t('booktailor', 'Read & write') },
]

const selectedUser = ref<CandidateOption | undefined>(undefined)
const selectedRole = ref<ShareRoleOption>(roleOptions[0])
const pendingRevoke = ref<BookShare | null>(null)

const candidateOptions = computed<CandidateOption[]>(() => shareCandidates.value.map((candidate) => ({
	...candidate,
	subname: candidate.id,
	user: candidate.id,
})))

function roleOption(role: string): ShareRoleOption {
	return roleOptions.find((option) => option.id === role) ?? roleOptions[0]
}

async function onSearch(query: string): Promise<void> {
	await searchShareCandidates(query)
}

async function submit(): Promise<void> {
	if (selectedUser.value === undefined || shareBusy.value) {
		return
	}

	if (await createBookShare(selectedUser.value.id, selectedRole.value.id)) {
		selectedUser.value = undefined
		selectedRole.value = roleOptions[0]
	}
}

async function changeRole(share: BookShare, option: ShareRoleOption | null): Promise<void> {
	if (option === null || option.id === share.role) {
		return
	}

	await updateBookShare(share, option.id)
}

async function confirmRevoke(): Promise<void> {
	const share = pendingRevoke.value
	if (share === null) {
		return
	}

	if (await revokeBookShare(share)) {
		pendingRevoke.value = null
	}
}
</script>

<template>
	<div>
		<NcDialog
			:open="true"
			:name="t('booktailor', 'Share book')"
			size="normal"
			@closing="closeShareDialog">
			<NcNoteCard v-if="shareError" type="error" :text="shareError" />

			<section class="share-add">
				<h3 class="share-section-title">
					{{ t('booktailor', 'Add a Nextcloud user') }}
				</h3>
				<div class="share-add-row">
					<NcSelectUsers
						v-model="selectedUser"
						class="share-user-select"
						:options="candidateOptions"
						:loading="shareCandidatesLoading"
						:disabled="shareBusy"
						:input-label="t('booktailor', 'User')"
						:placeholder="t('booktailor', 'Search for a user')"
						@search="onSearch" />
					<NcSelect
						v-model="selectedRole"
						class="share-role-select"
						:options="roleOptions"
						label="label"
						:input-label="t('booktailor', 'Access')"
						:clearable="false"
						:searchable="false" />
					<NcButton
						variant="primary"
						:disabled="selectedUser === undefined || shareBusy"
						@click="submit">
						{{ t('booktailor', 'Share') }}
					</NcButton>
				</div>
			</section>

			<section class="share-list">
				<h3 class="share-section-title">
					{{ t('booktailor', 'People with access') }}
				</h3>
				<div v-if="sharesLoading" class="share-state">
					<NcLoadingIcon :name="t('booktailor', 'Loading shares')" />
				</div>
				<p v-else-if="bookShares.length === 0" class="share-state">
					{{ t('booktailor', 'This book is not shared with anyone yet.') }}
				</p>
				<ul v-else class="share-rows">
					<li v-for="share in bookShares" :key="share.id" class="share-row">
						<div class="share-user">
							<span class="share-user-name">{{ share.displayName }}</span>
							<span class="share-user-id">{{ share.userId }}</span>
						</div>
						<NcSelect
							class="share-role-select"
							:model-value="roleOption(share.role)"
							:options="roleOptions"
							label="label"
							:input-label="t('booktailor', 'Access for {user}', { user: share.displayName })"
							:clearable="false"
							:searchable="false"
							:disabled="shareBusy"
							@update:model-value="changeRole(share, $event)" />
						<NcButton
							variant="tertiary"
							:disabled="shareBusy"
							@click="pendingRevoke = share">
							{{ t('booktailor', 'Revoke') }}
						</NcButton>
					</li>
				</ul>
			</section>

			<template #actions>
				<NcButton variant="tertiary" @click="closeShareDialog">
					{{ t('booktailor', 'Close') }}
				</NcButton>
			</template>
		</NcDialog>

		<NcDialog
			v-if="pendingRevoke"
			:open="true"
			:name="t('booktailor', 'Revoke access')"
			size="small"
			@closing="pendingRevoke = null">
			<NcNoteCard v-if="shareError" type="error" :text="shareError" />
			<p class="share-revoke-message">
				{{ t('booktailor', 'Remove {user} from this book? They will lose access immediately.', { user: pendingRevoke.displayName }) }}
			</p>
			<template #actions>
				<NcButton variant="tertiary" :disabled="shareBusy" @click="pendingRevoke = null">
					{{ t('booktailor', 'Cancel') }}
				</NcButton>
				<NcButton variant="error" :disabled="shareBusy" @click="confirmRevoke">
					{{ t('booktailor', 'Revoke access') }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<style scoped>
.share-add,
.share-list {
	display: flex;
	flex-direction: column;
	gap: 12px;
	margin-block-end: 24px;
}

.share-section-title {
	margin: 0;
	font-size: 1rem;
}

.share-add-row {
	display: flex;
	align-items: flex-end;
	gap: 8px;
	flex-wrap: wrap;
}

.share-user-select {
	flex: 1 1 240px;
	min-width: 200px;
}

.share-role-select {
	min-width: 150px;
}

.share-state {
	display: flex;
	align-items: center;
	justify-content: center;
	min-height: 72px;
	color: var(--color-text-maxcontrast);
}

.share-rows {
	display: flex;
	flex-direction: column;
	gap: 8px;
	margin: 0;
	padding: 0;
	list-style: none;
}

.share-row {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 8px 0;
	border-bottom: 1px solid var(--color-border);
}

.share-user {
	display: flex;
	flex-direction: column;
	flex: 1 1 auto;
	min-width: 0;
}

.share-user-name {
	overflow: hidden;
	font-weight: 600;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.share-user-id {
	overflow: hidden;
	color: var(--color-text-maxcontrast);
	font-size: 0.85rem;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.share-revoke-message {
	margin: 0;
	max-width: 420px;
}
</style>
