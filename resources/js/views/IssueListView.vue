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

        <div
            v-if="successMessage"
            class="issue-toast issue-toast-success"
            role="status"
            aria-live="polite"
        >
            {{ successMessage }}
        </div>
        <p v-if="errorMessage" class="alert alert-error">{{ errorMessage }}</p>

        <form class="issue-filter-bar" @submit.prevent="fetchIssues">
            <div class="filter-row">
                <label class="filter-product">
                    <span>プロダクト</span>
                    <select v-model="filters.product_id" multiple>
                        <option v-for="product in productStore.products" :key="product.id" :value="String(product.id)">
                            {{ product.name }}
                        </option>
                    </select>
                </label>
                <label class="filter-member">
                    <span>エンジニア</span>
                    <select v-model="filters.engineer_id" multiple>
                        <option v-for="engineer in engineerStore.engineers" :key="engineer.id" :value="String(engineer.id)">
                            {{ engineer.name }}
                        </option>
                    </select>
                </label>
                <label class="filter-member">
                    <span>ディレクター</span>
                    <select v-model="filters.director_id" multiple>
                        <option v-for="user in userStore.users" :key="user.id" :value="String(user.id)">
                            {{ user.name }}
                        </option>
                    </select>
                </label>
                <label class="filter-status">
                    <span>ステータス</span>
                    <select v-model="filters.status" multiple>
                        <option v-for="status in statuses" :key="status" :value="status">{{ status }}</option>
                    </select>
                </label>
                <label class="filter-mode">
                    <span>管理表</span>
                    <select v-model="filters.mode">
                        <option value="all">すべて</option>
                        <option value="managed">表示中のみ</option>
                        <option value="unmanaged_imports">未追加のみ</option>
                    </select>
                </label>
            </div>
            <div class="filter-row filter-row-dates">
                <div class="filter-date-range">
                    <span>予定開始</span>
                    <div class="filter-date-inputs">
                        <input type="date" v-model="filters.planned_start_from">
                        <span class="filter-date-sep">〜</span>
                        <input type="date" v-model="filters.planned_start_to">
                    </div>
                </div>
                <div class="filter-date-range">
                    <span>予定終了</span>
                    <div class="filter-date-inputs">
                        <input type="date" v-model="filters.planned_end_from">
                        <span class="filter-date-sep">〜</span>
                        <input type="date" v-model="filters.planned_end_to">
                    </div>
                </div>
                <div class="filter-date-range">
                    <span>実績開始</span>
                    <div class="filter-date-inputs">
                        <input type="date" v-model="filters.actual_start_from">
                        <span class="filter-date-sep">〜</span>
                        <input type="date" v-model="filters.actual_start_to">
                    </div>
                </div>
                <div class="filter-date-range">
                    <span>実績終了</span>
                    <div class="filter-date-inputs">
                        <input type="date" v-model="filters.actual_end_from">
                        <span class="filter-date-sep">〜</span>
                        <input type="date" v-model="filters.actual_end_to">
                    </div>
                </div>
                <label class="filter-flags">
                    <span>フラグ</span>
                    <select v-model="filters.flags" multiple>
                        <option value="overdue">期限超過</option>
                        <option value="due_soon">期限近い</option>
                    </select>
                </label>
                <div class="issue-filter-actions">
                    <button class="btn btn-secondary" type="button" @click="resetFilters">リセット</button>
                    <button class="btn btn-primary" type="submit">絞り込み</button>
                </div>
            </div>
        </form>

        <div class="master-panel issue-panel">
            <p v-if="issueStore.loading" class="empty-state">読み込み中です。</p>
            <p v-else-if="issueStore.issues.length === 0" class="empty-state">
                {{ emptyStateMessage }}
            </p>
            <div v-else class="issue-table-wrap">
                <table class="issue-table">
                    <colgroup>
                        <col
                            v-for="column in sortableColumns"
                            :key="column.key"
                            :style="{ width: column.width }"
                        >
                    </colgroup>
                    <thead>
                        <tr>
                            <th
                                v-for="column in sortableColumns"
                                :key="column.key"
                                :aria-sort="sortState.key === column.key ? sortAriaValue : 'none'"
                            >
                                <button
                                    class="issue-sort-button"
                                    type="button"
                                    :aria-label="`${column.label}で並び替え`"
                                    @click="toggleSort(column.key)"
                                >
                                    <span>{{ column.label }}</span>
                                    <span class="issue-sort-icon" aria-hidden="true">{{ sortIcon(column.key) }}</span>
                                </button>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="issue in sortedIssues" :key="issue.id">
                            <td class="issue-title-cell">
                                <a :href="issue.github_url" target="_blank" rel="noreferrer">
                                    #{{ issue.github_issue_number }} {{ issue.title }}
                                </a>
                                <div class="issue-title-meta">
                                    <span :class="['github-state', `github-state-${issue.github_state}`]">
                                        {{ issue.github_state }}
                                    </span>
                                    <span>同期: {{ formatDateTime(issue.github_synced_at) }}</span>
                                </div>
                            </td>
                            <td>{{ productName(issue.product_id) }}</td>
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
                                    {{ issue.is_managed ? '表示中' : '追加' }}
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
                                    :value="issue.schedule?.planned_end ?? ''"
                                    type="date"
                                    @change="updateSchedule(issue, { planned_end: emptyToNull($event.target.value) })"
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
                                    :value="issue.schedule?.actual_end ?? ''"
                                    type="date"
                                    @change="updateSchedule(issue, { actual_end: emptyToNull($event.target.value) })"
                                >
                            </td>
                            <td>
                                <div class="issue-flags">
                                    <span v-if="issue.is_overdue" class="flag flag-danger">期日超過</span>
                                    <span v-if="issue.is_due_soon" class="flag flag-warning">期日間近</span>
                                    <span v-if="!issue.is_overdue && !issue.is_due_soon" class="issue-muted">-</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { useEngineerStore } from '../stores/engineers';
import { useIssueStore } from '../stores/issues';
import { useProductStore } from '../stores/products';
import { useUserStore } from '../stores/users';

const statuses = ['未着手', '作業中', 'テスト中', '完了', '保留'];
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
    status: [],
    mode: 'all',
    planned_start_from: '',
    planned_start_to: '',
    planned_end_from: '',
    planned_end_to: '',
    actual_start_from: '',
    actual_start_to: '',
    actual_end_from: '',
    actual_end_to: '',
    flags: [],
});
const sortState = reactive({
    key: 'default',
    direction: 'asc',
});
const sortableColumns = [
    { key: 'issue', label: 'Issue', width: '280px' },
    { key: 'product', label: 'プロダクト', width: '92px' },
    { key: 'director', label: 'ディレクター', width: '108px' },
    { key: 'engineer', label: 'エンジニア', width: '108px' },
    { key: 'status', label: 'ステータス', width: '92px' },
    { key: 'is_managed', label: '管理表', width: '68px' },
    { key: 'planned_start', label: '予定開始', width: '104px' },
    { key: 'planned_end', label: '予定終了', width: '104px' },
    { key: 'actual_start', label: '実績開始', width: '104px' },
    { key: 'actual_end', label: '実績終了', width: '104px' },
    { key: 'flags', label: 'フラグ', width: '80px' },
];
const collator = new Intl.Collator('ja-JP', { numeric: true, sensitivity: 'base' });

const sortedIssues = computed(() => {
    const direction = sortState.direction === 'desc' ? -1 : 1;

    return [...issueStore.issues].sort((a, b) => {
        if (sortState.key === 'default') {
            return defaultIssueCompare(a, b);
        }

        const leftValue = sortValue(a, sortState.key);
        const rightValue = sortValue(b, sortState.key);
        const leftEmpty = isSortEmpty(leftValue);
        const rightEmpty = isSortEmpty(rightValue);

        if (leftEmpty || rightEmpty) {
            if (leftEmpty && rightEmpty) {
                return defaultIssueCompare(a, b);
            }

            return leftEmpty ? 1 : -1;
        }

        const compared = compareValues(leftValue, rightValue);

        if (compared !== 0) {
            return compared * direction;
        }

        return defaultIssueCompare(a, b);
    });
});
const hasActiveFilters = computed(() => Object.keys(filterParams()).length > 0);
const emptyStateMessage = computed(() => (
    hasActiveFilters.value
        ? '条件に一致するISSUEはありません。絞り込み条件を変更してください。'
        : 'ISSUEは未登録です。GitHub連携または同期設定を確認してください。'
));
const sortAriaValue = computed(() => (sortState.direction === 'asc' ? 'ascending' : 'descending'));

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

    if (filters.mode === 'unmanaged_imports') {
        params.unmanaged_imports = true;
    }

    const dateKeys = [
        'planned_start_from', 'planned_start_to',
        'planned_end_from', 'planned_end_to',
        'actual_start_from', 'actual_start_to',
        'actual_end_from', 'actual_end_to',
    ];
    for (const key of dateKeys) {
        if (filters[key]) params[key] = filters[key];
    }

    if (filters.flags.length > 0) {
        params.flags = [...filters.flags];
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
        status: [],
        mode: 'all',
        planned_start_from: '',
        planned_start_to: '',
        planned_end_from: '',
        planned_end_to: '',
        actual_start_from: '',
        actual_start_to: '',
        actual_end_from: '',
        actual_end_to: '',
        flags: [],
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
        planned_start_from: filters.planned_start_from,
        planned_start_to: filters.planned_start_to,
        planned_end_from: filters.planned_end_from,
        planned_end_to: filters.planned_end_to,
        actual_start_from: filters.actual_start_from,
        actual_start_to: filters.actual_start_to,
        actual_end_from: filters.actual_end_from,
        actual_end_to: filters.actual_end_to,
        flags: [...filters.flags],
    }));
}

function applyFilters(nextFilters) {
    filters.product_id = normalizeFilterArray(nextFilters.product_id);
    filters.engineer_id = normalizeFilterArray(nextFilters.engineer_id);
    filters.director_id = normalizeFilterArray(nextFilters.director_id);
    filters.status = normalizeFilterArray(nextFilters.status).filter((status) => statuses.includes(status));
    filters.mode = 'all';

    if (nextFilters.mode === '' || nextFilters.mode === 'all') {
        filters.mode = 'all';
    }

    if (nextFilters.mode === 'managed') {
        filters.mode = 'managed';
    }

    if (nextFilters.mode === 'unmanaged' || nextFilters.mode === 'unmanaged_imports') {
        filters.mode = 'unmanaged_imports';
    }

    const dateKeys = [
        'planned_start_from', 'planned_start_to',
        'planned_end_from', 'planned_end_to',
        'actual_start_from', 'actual_start_to',
        'actual_end_from', 'actual_end_to',
    ];
    for (const key of dateKeys) {
        const v = nextFilters[key];
        filters[key] = (typeof v === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(v)) ? v : '';
    }

    filters.flags = normalizeFilterArray(nextFilters.flags).filter((f) => ['overdue', 'due_soon'].includes(f));
}

function normalizeFilterArray(value) {
    if (!Array.isArray(value)) {
        return value ? [String(value)] : [];
    }

    return value.map((item) => String(item)).filter((item) => item !== '');
}

function toggleSort(key) {
    if (sortState.key === key) {
        sortState.direction = sortState.direction === 'asc' ? 'desc' : 'asc';
        return;
    }

    sortState.key = key;
    sortState.direction = 'asc';
}

function sortIcon(key) {
    if (sortState.key !== key) {
        return '↕';
    }

    return sortState.direction === 'asc' ? '↑' : '↓';
}

function sortValue(issue, key) {
    switch (key) {
        case 'issue':
            return `${issue.github_issue_number ?? ''} ${issue.title ?? ''}`;
        case 'product':
            return productName(issue.product_id);
        case 'director':
            return issue.director?.name ?? '';
        case 'engineer':
            return issue.engineer?.name ?? '';
        case 'status':
            return statuses.indexOf(issue.status);
        case 'is_managed':
            return issue.is_managed ? 1 : 0;
        case 'planned_start':
        case 'actual_start':
        case 'planned_end':
        case 'actual_end':
            return issue.schedule?.[key] ?? null;
        case 'flags':
            if (issue.is_overdue) {
                return 2;
            }

            if (issue.is_due_soon) {
                return 1;
            }

            return 0;
        default:
            return null;
    }
}

function compareValues(left, right) {
    if (typeof left === 'number' && typeof right === 'number') {
        return left - right;
    }

    return collator.compare(String(left), String(right));
}

function isSortEmpty(value) {
    return value === null || value === undefined || value === '';
}

function defaultIssueCompare(left, right) {
    return compareValues(left.product_id, right.product_id)
        || compareValues(left.display_order, right.display_order)
        || compareValues(left.id, right.id);
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
        await fetchIssues();
        showSuccessMessage('管理表を更新しました。');
    } catch (error) {
        errorMessage.value = formatError(error, '管理表の更新に失敗しました。');
    }
}

async function updateSchedule(issue, patch) {
    const schedule = {
        planned_start: issue.schedule?.planned_start ?? null,
        planned_end: issue.schedule?.planned_end ?? null,
        actual_start: issue.schedule?.actual_start ?? null,
        actual_end: issue.schedule?.actual_end ?? null,
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
