<template>
    <section class="master-view issue-view">
        <header class="master-header">
            <div>
                <h1>ISSUE一覧</h1>
                <p>GitHubから取り込まれたISSUEの管理項目とスケジュールを確認・更新します。</p>
            </div>
            <button class="btn btn-primary" type="button" :disabled="issueStore.loading" @click="fetchIssues">
                再読み込み
            </button>
        </header>

        <p v-if="successMessage" class="alert alert-success">{{ successMessage }}</p>
        <p v-if="errorMessage" class="alert alert-error">{{ errorMessage }}</p>

        <form class="issue-filter-bar" @submit.prevent="fetchIssues">
            <label>
                <span>プロダクト</span>
                <select v-model="filters.product_id" multiple>
                    <option v-for="product in productStore.products" :key="product.id" :value="String(product.id)">
                        {{ product.name }}
                    </option>
                </select>
            </label>
            <label>
                <span>エンジニア</span>
                <select v-model="filters.engineer_id" multiple>
                    <option v-for="engineer in engineerStore.engineers" :key="engineer.id" :value="String(engineer.id)">
                        {{ engineer.name }}
                    </option>
                </select>
            </label>
            <label>
                <span>ディレクター</span>
                <select v-model="filters.director_id" multiple>
                    <option v-for="user in userStore.users" :key="user.id" :value="String(user.id)">
                        {{ user.name }}
                    </option>
                </select>
            </label>
            <label>
                <span>ステータス</span>
                <select v-model="filters.status" multiple>
                    <option v-for="status in statuses" :key="status" :value="status">{{ status }}</option>
                </select>
            </label>
            <label>
                <span>管理対象</span>
                <select v-model="filters.mode">
                    <option value="">管理対象のみ</option>
                    <option value="unmanaged">未管理のみ</option>
                    <option value="unmanaged_imports">未追加リスト</option>
                </select>
            </label>
            <div class="issue-filter-actions">
                <button class="btn btn-secondary" type="button" @click="resetFilters">リセット</button>
                <button class="btn btn-primary" type="submit">絞り込み</button>
            </div>
        </form>

        <div class="master-panel issue-panel">
            <p v-if="issueStore.loading" class="empty-state">読み込み中です。</p>
            <p v-else-if="issueStore.issues.length === 0" class="empty-state">
                ISSUEは未登録です。開発データが必要な場合は `php artisan migrate:fresh --seed` を実行してください。
            </p>
            <div v-else class="issue-table-wrap">
                <table class="issue-table">
                    <thead>
                        <tr>
                            <th>Issue</th>
                            <th>プロダクト</th>
                            <th>GitHub状態</th>
                            <th>ディレクター</th>
                            <th>エンジニア</th>
                            <th>ステータス</th>
                            <th>管理対象</th>
                            <th>予定開始</th>
                            <th>実績開始</th>
                            <th>予定終了</th>
                            <th>実績終了</th>
                            <th>予定工数</th>
                            <th>実績工数</th>
                            <th>フラグ</th>
                            <th>同期日時</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="issue in issueStore.issues" :key="issue.id">
                            <td class="issue-title-cell">
                                <a :href="issue.github_url" target="_blank" rel="noreferrer">
                                    #{{ issue.github_issue_number }} {{ issue.title }}
                                </a>
                            </td>
                            <td>{{ productName(issue.product_id) }}</td>
                            <td>
                                <span :class="['github-state', `github-state-${issue.github_state}`]">
                                    {{ issue.github_state }}
                                </span>
                            </td>
                            <td>
                                <select
                                    :value="issue.director?.id ?? ''"
                                    @change="updateIssue(issue, { director_id: normalizeNullableId($event.target.value) })"
                                >
                                    <option value="">未割当</option>
                                    <option v-for="user in userStore.users" :key="user.id" :value="user.id">
                                        {{ user.name }}
                                    </option>
                                </select>
                            </td>
                            <td>
                                <select
                                    :value="issue.engineer?.id ?? ''"
                                    @change="updateIssue(issue, { engineer_id: normalizeNullableId($event.target.value) })"
                                >
                                    <option value="">未割当</option>
                                    <option v-for="engineer in engineerStore.engineers" :key="engineer.id" :value="engineer.id">
                                        {{ engineer.name }}
                                    </option>
                                </select>
                            </td>
                            <td>
                                <select :value="issue.status" @change="updateStatus(issue, $event.target.value)">
                                    <option v-for="status in statuses" :key="status" :value="status">{{ status }}</option>
                                </select>
                            </td>
                            <td>
                                <button
                                    :class="['btn', issue.is_managed ? 'btn-secondary' : 'btn-primary']"
                                    type="button"
                                    @click="toggleManaged(issue)"
                                >
                                    {{ issue.is_managed ? '管理対象' : '追加' }}
                                </button>
                            </td>
                            <td>
                                <input
                                    :value="issue.schedule?.planned_start ?? ''"
                                    type="date"
                                    @change="updateSchedule(issue, { planned_start: emptyToNull($event.target.value) })"
                                >
                            </td>
                            <td>
                                <input
                                    :value="issue.schedule?.actual_start ?? ''"
                                    type="date"
                                    @change="updateSchedule(issue, { actual_start: emptyToNull($event.target.value) })"
                                >
                            </td>
                            <td>
                                <input
                                    :value="issue.schedule?.planned_end ?? ''"
                                    type="date"
                                    @change="updateSchedule(issue, { planned_end: emptyToNull($event.target.value) })"
                                >
                            </td>
                            <td>
                                <input
                                    :value="issue.schedule?.actual_end ?? ''"
                                    type="date"
                                    @change="updateSchedule(issue, { actual_end: emptyToNull($event.target.value) })"
                                >
                            </td>
                            <td>
                                <input
                                    :value="issue.schedule?.planned_hours ?? ''"
                                    min="0"
                                    step="0.25"
                                    type="number"
                                    @change="updateSchedule(issue, { planned_hours: emptyToNull($event.target.value) })"
                                >
                            </td>
                            <td>
                                <input
                                    :value="issue.schedule?.actual_hours ?? ''"
                                    min="0"
                                    step="0.25"
                                    type="number"
                                    @change="updateSchedule(issue, { actual_hours: emptyToNull($event.target.value) })"
                                >
                            </td>
                            <td>
                                <div class="issue-flags">
                                    <span v-if="issue.is_overdue" class="flag flag-danger">期日超過</span>
                                    <span v-if="issue.is_due_soon" class="flag flag-warning">期日間近</span>
                                    <span v-if="!issue.is_overdue && !issue.is_due_soon" class="issue-muted">-</span>
                                </div>
                            </td>
                            <td>{{ formatDateTime(issue.github_synced_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</template>

<script setup>
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { useEngineerStore } from '../stores/engineers';
import { useIssueStore } from '../stores/issues';
import { useProductStore } from '../stores/products';
import { useUserStore } from '../stores/users';

const statuses = ['未着手', '作業中', 'テスト中', '完了', '保留'];
const defaultVisibleStatuses = statuses.filter((status) => status !== '完了');
const filterStorageKey = 'issue-list-filters';
const issueStore = useIssueStore();
const productStore = useProductStore();
const engineerStore = useEngineerStore();
const userStore = useUserStore();
const errorMessage = ref('');
const successMessage = ref('');
let successMessageTimer = null;

const filters = reactive({
    product_id: [],
    engineer_id: [],
    director_id: [],
    status: [...defaultVisibleStatuses],
    mode: '',
});

onMounted(async () => {
    restoreFilters();

    await Promise.all([
        productStore.fetchProducts(),
        engineerStore.fetchEngineers(),
        userStore.fetchUsers(),
    ]);
    await fetchIssues();
});

onBeforeUnmount(() => {
    clearSuccessMessageTimer();
});

async function fetchIssues() {
    errorMessage.value = '';
    saveFilters();

    try {
        await issueStore.fetchIssues(filterParams());
    } catch (error) {
        errorMessage.value = formatError(error, 'ISSUEの取得に失敗しました。');
    }
}

function filterParams() {
    const params = {};

    for (const key of ['product_id', 'engineer_id', 'director_id', 'status']) {
        if (filters[key].length > 0) {
            params[key] = [...filters[key]];
        }
    }

    if (filters.mode === 'managed') {
        params.is_managed = true;
    }

    if (filters.mode === 'unmanaged') {
        params.is_managed = false;
    }

    if (filters.mode === 'unmanaged_imports') {
        params.unmanaged_imports = true;
    }

    return params;
}

async function resetFilters() {
    applyFilters(defaultFilters());
    saveFilters();
    await fetchIssues();
}

function defaultFilters() {
    return {
        product_id: [],
        engineer_id: [],
        director_id: [],
        status: [...defaultVisibleStatuses],
        mode: '',
    };
}

function restoreFilters() {
    try {
        const savedFilters = JSON.parse(window.localStorage.getItem(filterStorageKey) ?? 'null');

        if (savedFilters && typeof savedFilters === 'object') {
            applyFilters(savedFilters);
        }
    } catch {
        applyFilters(defaultFilters());
    }
}

function saveFilters() {
    window.localStorage.setItem(filterStorageKey, JSON.stringify({
        product_id: [...filters.product_id],
        engineer_id: [...filters.engineer_id],
        director_id: [...filters.director_id],
        status: [...filters.status],
        mode: filters.mode,
    }));
}

function applyFilters(nextFilters) {
    filters.product_id = normalizeFilterArray(nextFilters.product_id);
    filters.engineer_id = normalizeFilterArray(nextFilters.engineer_id);
    filters.director_id = normalizeFilterArray(nextFilters.director_id);
    filters.status = normalizeFilterArray(nextFilters.status).filter((status) => statuses.includes(status));
    filters.mode = '';

    if (['', 'unmanaged', 'unmanaged_imports'].includes(nextFilters.mode)) {
        filters.mode = nextFilters.mode;
    }
}

function normalizeFilterArray(value) {
    if (!Array.isArray(value)) {
        return value ? [String(value)] : [];
    }

    return value.map((item) => String(item)).filter((item) => item !== '');
}

async function updateIssue(issue, payload) {
    try {
        await issueStore.updateIssue(issue.id, payload);
        showSuccessMessage('ISSUEを更新しました。');
    } catch (error) {
        errorMessage.value = formatError(error, 'ISSUEの更新に失敗しました。');
        await fetchIssues();
    }
}

async function updateStatus(issue, status) {
    try {
        await issueStore.updateStatus(issue.id, status);
        showSuccessMessage('ステータスを更新しました。');
    } catch (error) {
        errorMessage.value = formatError(error, 'ステータスの更新に失敗しました。');
    }
}

async function toggleManaged(issue) {
    try {
        await issueStore.toggleManaged(issue.id);
        showSuccessMessage('管理対象を更新しました。');
    } catch (error) {
        errorMessage.value = formatError(error, '管理対象の更新に失敗しました。');
    }
}

async function updateSchedule(issue, patch) {
    const schedule = {
        planned_start: issue.schedule?.planned_start ?? null,
        actual_start: issue.schedule?.actual_start ?? null,
        planned_end: issue.schedule?.planned_end ?? null,
        actual_end: issue.schedule?.actual_end ?? null,
        planned_hours: issue.schedule?.planned_hours ?? null,
        actual_hours: issue.schedule?.actual_hours ?? null,
        ...patch,
    };

    try {
        await issueStore.updateSchedule(issue.id, schedule);
        showSuccessMessage('スケジュールを更新しました。');
    } catch (error) {
        errorMessage.value = formatError(error, 'スケジュールの更新に失敗しました。');
        await fetchIssues();
    }
}

function productName(productId) {
    return productStore.products.find((product) => product.id === productId)?.name ?? `Product ${productId}`;
}

function normalizeNullableId(value) {
    return value === '' ? null : Number(value);
}

function emptyToNull(value) {
    return value === '' ? null : value;
}

function formatDateTime(value) {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('ja-JP', {
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function formatError(error, fallbackMessage) {
    const errors = error.response?.data?.errors;

    if (errors) {
        return Object.values(errors).flat().join(' ');
    }

    return error.response?.data?.message ?? fallbackMessage;
}

function showSuccessMessage(message) {
    errorMessage.value = '';
    clearSuccessMessageTimer();
    successMessage.value = message;
    successMessageTimer = window.setTimeout(() => {
        successMessage.value = '';
        successMessageTimer = null;
    }, 3000);
}

function clearSuccessMessageTimer() {
    if (successMessageTimer) {
        window.clearTimeout(successMessageTimer);
        successMessageTimer = null;
    }
}
</script>
