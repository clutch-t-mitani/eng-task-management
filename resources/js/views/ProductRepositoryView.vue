<template>
    <section class="repo-view">
        <header class="repo-header">
            <router-link class="back-link" to="/products">← プロダクト管理</router-link>
            <h1>リポジトリ連携設定</h1>
        </header>

        <p v-if="pageError" class="alert alert-error">{{ pageError }}</p>
        <div v-if="loading" class="empty-state">読み込み中…</div>

        <template v-else>
            <div class="card">
                <h2>GitHubリポジトリ</h2>

                <div v-if="repo && !repo.is_active" class="notice notice-warn">
                    このリポジトリ連携は無効化されています。同じ owner/repo で保存すると再有効化されます。
                </div>

                <form @submit.prevent="saveRepo">
                    <div class="form-row">
                        <label class="form-label">
                            オーナー名
                            <input
                                v-model.trim="form.owner"
                                type="text"
                                class="form-input"
                                placeholder="例: octocat"
                                autocomplete="off"
                                required
                            />
                        </label>
                        <label class="form-label">
                            リポジトリ名
                            <input
                                v-model.trim="form.repo"
                                type="text"
                                class="form-input"
                                placeholder="例: my-project"
                                autocomplete="off"
                                required
                            />
                        </label>
                    </div>

                    <p v-if="formError" class="field-error">{{ formError }}</p>
                    <p v-if="formSuccess" class="field-success">{{ formSuccess }}</p>

                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit" :disabled="saving">
                            {{ saving ? '保存中…' : '保存' }}
                        </button>
                        <button
                            v-if="repo && repo.is_active"
                            class="btn btn-danger"
                            type="button"
                            :disabled="deactivating"
                            @click="deactivate"
                        >
                            {{ deactivating ? '無効化中…' : '連携を無効化' }}
                        </button>
                    </div>
                </form>

                <div v-if="repo" class="sync-status">
                    <span class="sync-label">最終同期:</span>
                    <span>{{ repo.last_synced_at ? formatDate(repo.last_synced_at) : '未同期' }}</span>
                    <span
                        v-if="repo.last_sync_status"
                        :class="`status-badge status-${repo.last_sync_status}`"
                    >
                        {{ statusLabel(repo.last_sync_status) }}
                    </span>
                </div>
            </div>

            <div class="card">
                <h2>Webhook設定</h2>
                <p class="help-text">
                    GitHubリポジトリの <strong>Settings → Webhooks</strong> に以下のURLを登録してください。<br>
                    Content type: <code>application/json</code>　　Events: <code>Issues</code> のみ選択
                </p>
                <div class="url-row">
                    <input :value="webhookUrl" readonly class="form-input url-input" />
                    <button class="btn btn-secondary" type="button" @click="copyUrl">
                        {{ copied ? 'コピー済み' : 'コピー' }}
                    </button>
                </div>
                <p class="help-text mt">
                    Secret には <code>GITHUB_WEBHOOK_SECRET</code> 環境変数に設定した値を入力してください。
                </p>
            </div>

            <div class="card">
                <div class="card-header-row">
                    <h2>同期ログ（直近20件）</h2>
                    <button class="btn btn-secondary" type="button" :disabled="logsLoading" @click="loadLogs">
                        {{ logsLoading ? '読込中…' : '更新' }}
                    </button>
                </div>

                <div v-if="logsLoading" class="empty-state">読み込み中…</div>
                <p v-else-if="logs.length === 0" class="empty-state">同期ログはありません。</p>
                <div v-else class="log-scroll">
                    <table class="log-table">
                        <thead>
                            <tr>
                                <th>トリガー</th>
                                <th>ステータス</th>
                                <th class="num-col">作成</th>
                                <th class="num-col">更新</th>
                                <th class="num-col">スキップ</th>
                                <th class="num-col">失敗</th>
                                <th>開始日時</th>
                                <th>備考</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="log in logs" :key="log.id">
                                <td>{{ triggerLabel(log.trigger) }}</td>
                                <td>
                                    <span :class="`status-badge status-${log.status}`">
                                        {{ statusLabel(log.status) }}
                                    </span>
                                </td>
                                <td class="num-col">{{ log.created_count }}</td>
                                <td class="num-col">{{ log.updated_count }}</td>
                                <td class="num-col">{{ log.skipped_count }}</td>
                                <td class="num-col">{{ log.failed_count }}</td>
                                <td class="nowrap">{{ formatDate(log.started_at) }}</td>
                                <td class="log-note">
                                    <span v-if="log.attempt_count > 1" class="retry-badge">
                                        試行 {{ log.attempt_count }} 回
                                    </span>
                                    <span
                                        v-if="log.github_delivery_id"
                                        class="delivery-id"
                                        :title="log.github_delivery_id"
                                    >
                                        {{ log.github_delivery_id.slice(0, 8) }}…
                                    </span>
                                    <span v-if="log.error_message" class="error-msg">
                                        {{ log.error_message }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useGitHubSyncStore } from '../stores/githubSync';

const route = useRoute();
const syncStore = useGitHubSyncStore();

const productId = computed(() => Number(route.params.id));

const loading = ref(true);
const logsLoading = ref(false);
const saving = ref(false);
const deactivating = ref(false);
const pageError = ref('');
const formError = ref('');
const formSuccess = ref('');
const copied = ref(false);

const form = reactive({ owner: '', repo: '' });

const repo = computed(() => syncStore.getRepository(productId.value));
const logs = computed(() => syncStore.getLogs(productId.value));
const webhookUrl = computed(() => `${window.location.origin}/api/v1/github/webhook`);

onMounted(async () => {
    try {
        await syncStore.fetchRepository(productId.value);
        if (repo.value) {
            form.owner = repo.value.owner;
            form.repo = repo.value.repo;
        }
    } catch {
        pageError.value = 'リポジトリ情報の取得に失敗しました。';
    } finally {
        loading.value = false;
    }
    loadLogs();
});

async function loadLogs() {
    logsLoading.value = true;
    try {
        await syncStore.fetchLogs(productId.value);
    } catch {
        // ログ取得失敗は非致命的
    } finally {
        logsLoading.value = false;
    }
}

async function saveRepo() {
    saving.value = true;
    formError.value = '';
    formSuccess.value = '';
    try {
        await syncStore.saveRepository(productId.value, { owner: form.owner, repo: form.repo });
        formSuccess.value = 'リポジトリを保存しました。';
    } catch (error) {
        const errors = error.response?.data?.errors;
        formError.value = errors
            ? Object.values(errors).flat().join(' ')
            : (error.response?.data?.message ?? '保存に失敗しました。');
    } finally {
        saving.value = false;
    }
}

async function deactivate() {
    if (!window.confirm('連携を無効化しますか？WebhookからのISSUE自動更新が停止します。')) return;
    deactivating.value = true;
    pageError.value = '';
    try {
        await syncStore.deactivateRepository(productId.value);
    } catch {
        pageError.value = '無効化に失敗しました。';
    } finally {
        deactivating.value = false;
    }
}

function copyUrl() {
    navigator.clipboard.writeText(webhookUrl.value);
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 2000);
}

function formatDate(iso) {
    if (!iso) return '—';
    return new Intl.DateTimeFormat('ja-JP', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(iso));
}

function statusLabel(status) {
    const labels = { success: '成功', partial: '一部失敗', failed: '失敗', skipped: 'スキップ', running: '実行中' };
    return labels[status] ?? status;
}

function triggerLabel(trigger) {
    return { webhook: 'Webhook', manual_resync: '手動再同期' }[trigger] ?? trigger;
}
</script>

<style scoped>
.repo-view {
    padding: 24px 32px;
    max-width: 860px;
    margin: 0 auto;
}
.repo-header { margin-bottom: 20px; }
.back-link { color: #4299e1; font-size: 13px; text-decoration: none; }
.back-link:hover { text-decoration: underline; }
.repo-header h1 { font-size: 20px; font-weight: 700; margin-top: 8px; }

.card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 20px 24px;
    margin-bottom: 20px;
}
.card h2 { font-size: 15px; font-weight: 700; margin-bottom: 14px; }

.card-header-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
}
.card-header-row h2 { margin-bottom: 0; }

.notice {
    border-radius: 6px;
    font-size: 13px;
    margin-bottom: 14px;
    padding: 10px 14px;
}
.notice-warn { background: #fffbeb; border: 1px solid #f6ad55; color: #744210; }

.form-row { display: flex; gap: 16px; flex-wrap: wrap; }
.form-label {
    display: flex;
    flex-direction: column;
    font-size: 12px;
    font-weight: 600;
    color: #718096;
    gap: 5px;
    flex: 1;
    min-width: 180px;
}
.form-input {
    border: 1px solid #e2e8f0;
    border-radius: 7px;
    color: #2d3748;
    font-size: 13px;
    padding: 8px 10px;
}
.form-input:focus { outline: none; border-color: #4299e1; }
.form-input[readonly] { background: #f7fafc; cursor: default; }

.form-actions { display: flex; gap: 10px; margin-top: 16px; }

.field-error { color: #c53030; font-size: 12px; margin-top: 8px; }
.field-success { color: #276749; font-size: 12px; margin-top: 8px; }

.sync-status {
    margin-top: 14px;
    font-size: 12px;
    color: #718096;
    display: flex;
    align-items: center;
    gap: 8px;
}
.sync-label { font-weight: 600; }

.help-text { font-size: 12px; color: #718096; line-height: 1.6; }
.help-text.mt { margin-top: 12px; }
.help-text code { background: #f7fafc; border: 1px solid #e2e8f0; border-radius: 3px; padding: 1px 4px; font-size: 11px; }

.url-row { display: flex; gap: 8px; margin-top: 10px; }
.url-input { flex: 1; font-family: monospace; font-size: 12px; }

.log-scroll { overflow-x: auto; }
.log-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.log-table th {
    background: #f7fafc;
    border-bottom: 2px solid #e2e8f0;
    color: #718096;
    font-weight: 700;
    padding: 7px 10px;
    text-align: left;
    white-space: nowrap;
}
.log-table td { border-bottom: 1px solid #e2e8f0; padding: 7px 10px; vertical-align: top; }
.log-table tr:last-child td { border-bottom: none; }
.num-col { text-align: right; }
.nowrap { white-space: nowrap; }

.log-note { display: flex; flex-wrap: wrap; gap: 4px; align-items: center; }
.delivery-id { font-family: monospace; color: #718096; font-size: 11px; }
.error-msg { color: #c53030; }

.retry-badge {
    background: #fefcbf;
    border: 1px solid #f6e05e;
    border-radius: 10px;
    color: #744210;
    font-size: 10px;
    padding: 1px 6px;
    white-space: nowrap;
}

.status-badge {
    border-radius: 10px;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 8px;
    white-space: nowrap;
}
.status-success { background: #c6f6d5; color: #276749; }
.status-partial { background: #fefcbf; color: #744210; }
.status-failed { background: #fed7d7; color: #9b2c2c; }
.status-skipped { background: #e2e8f0; color: #4a5568; }
.status-running { background: #bee3f8; color: #2a4365; }

.empty-state { color: #718096; font-size: 13px; padding: 16px 0; text-align: center; }

.alert { border-radius: 6px; font-size: 13px; margin-bottom: 16px; padding: 10px 14px; }
.alert-error { background: #fff5f5; border: 1px solid #fc8181; color: #9b2c2c; }

.btn {
    border-radius: 7px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    padding: 7px 16px;
    border: none;
}
.btn:disabled { cursor: not-allowed; opacity: 0.6; }
.btn-primary { background: #4299e1; color: #fff; }
.btn-primary:hover:not(:disabled) { background: #3182ce; }
.btn-secondary { background: #fff; border: 1px solid #e2e8f0; color: #4a5568; }
.btn-secondary:hover:not(:disabled) { background: #f7fafc; }
.btn-danger { background: #fff; border: 1px solid #fc8181; color: #c53030; }
.btn-danger:hover:not(:disabled) { background: #fff5f5; }
</style>
