<template>
    <button
        class="btn-secondary"
        type="button"
        :disabled="isDisabled"
        :title="disabledReason"
        @click="resync"
    >
        {{ isSyncing ? '同期中…' : 'GitHubから再同期' }}
    </button>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useFilterStore } from '../stores/filters';
import { useGitHubSyncStore } from '../stores/githubSync';

const emit = defineEmits(['success', 'error']);

const filterStore = useFilterStore();
const syncStore = useGitHubSyncStore();
const repoFetching = ref(false);

const productId = computed(() => (filterStore.product_id != null ? Number(filterStore.product_id) : null));
const repo = computed(() => (productId.value != null ? syncStore.getRepository(productId.value) : null));
const repoLoaded = computed(() => productId.value != null && syncStore.isRepositoryLoaded(productId.value));
const isSyncing = computed(() => (productId.value != null ? syncStore.isSyncing(productId.value) : false));

watch(productId, async (id) => {
    if (id == null) return;
    if (syncStore.isRepositoryLoaded(id)) return;
    repoFetching.value = true;
    try {
        await syncStore.fetchRepository(id);
    } finally {
        repoFetching.value = false;
    }
}, { immediate: true });

const isDisabled = computed(() => {
    if (!productId.value) return true;
    if (repoFetching.value || !repoLoaded.value) return true;
    if (!repo.value) return true;
    if (!repo.value.is_active) return true;
    if (isSyncing.value) return true;
    return false;
});

const disabledReason = computed(() => {
    if (!productId.value) return 'プロダクトを選択してください';
    if (repoFetching.value) return '読み込み中…';
    if (!repo.value) return 'リポジトリが未登録です';
    if (!repo.value.is_active) return 'リポジトリ連携が無効化されています';
    if (isSyncing.value) return '同期中です';
    return '';
});

async function resync() {
    if (isDisabled.value || !productId.value) return;
    try {
        const result = await syncStore.resyncNow(productId.value);
        if (!result) return;
        if (result.status === 'failed') {
            emit('error', result.error_message ?? '同期に失敗しました。');
            return;
        }
        emit(
            'success',
            `作成 ${result.created_count} / 更新 ${result.updated_count} / スキップ ${result.skipped_count} / 失敗 ${result.failed_count}`,
        );
    } catch (error) {
        const msg = error.response?.status === 409
            ? '同期が既に実行中です。'
            : (error.response?.data?.message ?? '同期に失敗しました。');
        emit('error', msg);
    }
}
</script>

<style scoped>
.btn-secondary {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 7px;
    color: #4a5568;
    cursor: pointer;
    font-size: 13px;
    padding: 7px 16px;
}
.btn-secondary:hover:not(:disabled) { background: #f7fafc; }
.btn-secondary:disabled { cursor: not-allowed; opacity: 0.5; }
</style>
