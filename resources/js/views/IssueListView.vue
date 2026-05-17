<template>
    <section class="master-view issue-view">
        <header class="master-header">
            <div>
                <h1>ISSUE一覧</h1>
                <!-- <p>GitHubから取り込まれたISSUEの管理項目とスケジュールを確認・更新します。</p> -->
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

        <form ref="filterPanelRef" class="issue-filter-panel" @submit.prevent="applyDraftFilters">
            <div class="filter-panel-grid">
                <div class="filter-section-toggle-row">
                    <button
                        class="filter-section-toggle"
                        type="button"
                        :aria-expanded="isMainFiltersOpen"
                        @click="toggleMainFilters"
                    >
                        <span>基本条件</span>
                        <span>{{ isMainFiltersOpen ? '基本条件を閉じる' : '基本条件を開く' }}</span>
                        <span class="filter-chevron" aria-hidden="true">{{ isMainFiltersOpen ? '⌃' : '⌄' }}</span>
                    </button>
                </div>

                <template v-if="isMainFiltersOpen">
                    <div class="filter-field filter-menu-field">
                        <span>プロダクト</span>
                        <button class="filter-select-button" type="button" @click="toggleFilterMenu('product_id')">
                            <span>{{ selectedOptionLabel(draftFilters.product_id, productStore.products) }}</span>
                            <span class="filter-chevron" aria-hidden="true">⌄</span>
                        </button>
                        <div v-if="openFilterMenu === 'product_id'" class="filter-menu">
                            <label class="filter-check-row">
                                <input
                                    type="checkbox"
                                    :checked="draftFilters.product_id.length === 0"
                                    @change="clearDraftSelection('product_id')"
                                >
                                <span>すべて</span>
                            </label>
                            <label v-for="product in productStore.products" :key="product.id" class="filter-check-row">
                                <input
                                    type="checkbox"
                                    :checked="draftFilters.product_id.includes(String(product.id))"
                                    @change="toggleDraftSelection('product_id', String(product.id))"
                                >
                                <span>{{ product.name }}</span>
                            </label>
                            <button class="filter-menu-close" type="button" @click="closeFilterMenu">閉じる</button>
                        </div>
                    </div>

                    <div class="filter-field filter-menu-field">
                        <span>エンジニア</span>
                        <button class="filter-select-button" type="button" @click="toggleFilterMenu('engineer_id')">
                            <span>{{ selectedOptionLabel(draftFilters.engineer_id, engineerStore.engineers, '未割当', true) }}</span>
                            <span class="filter-chevron" aria-hidden="true">⌄</span>
                        </button>
                        <div v-if="openFilterMenu === 'engineer_id'" class="filter-menu">
                            <label class="filter-check-row">
                                <input
                                    type="checkbox"
                                    :checked="draftFilters.engineer_id.length === 0"
                                    @change="clearDraftSelection('engineer_id')"
                                >
                                <span>すべて</span>
                            </label>
                            <label v-for="engineer in engineerStore.engineers" :key="engineer.id" class="filter-check-row">
                                <input
                                    type="checkbox"
                                    :checked="draftFilters.engineer_id.includes(String(engineer.id))"
                                    @change="toggleDraftSelection('engineer_id', String(engineer.id))"
                                >
                                <span>{{ engineer.name }}</span>
                            </label>
                            <label class="filter-check-row">
                                <input
                                    type="checkbox"
                                    :checked="draftFilters.engineer_id.includes(EMPTY_FILTER_VALUE)"
                                    @change="toggleDraftSelection('engineer_id', EMPTY_FILTER_VALUE)"
                                >
                                <span>未割当</span>
                            </label>
                            <button class="filter-menu-close" type="button" @click="closeFilterMenu">閉じる</button>
                        </div>
                    </div>

                    <div class="filter-field filter-menu-field">
                        <span>ステータス</span>
                        <button class="filter-select-button" type="button" @click="toggleFilterMenu('status')">
                            <span>{{ selectedStatusLabel(draftFilters.status_id) }}</span>
                            <span class="filter-chevron" aria-hidden="true">⌄</span>
                        </button>
                        <div v-if="openFilterMenu === 'status'" class="filter-menu">
                            <label class="filter-check-row">
                                <input
                                    type="checkbox"
                                    :checked="draftFilters.status_id.length === 0"
                                    @change="clearDraftSelection('status_id')"
                                >
                                <span>すべて</span>
                            </label>
                            <label v-for="status in statuses" :key="status.id" class="filter-check-row">
                                <input
                                    type="checkbox"
                                    :checked="draftFilters.status_id.includes(String(status.id))"
                                    @change="toggleDraftSelection('status_id', String(status.id))"
                                >
                                <span>{{ status.label }}</span>
                            </label>
                            <button class="filter-menu-close" type="button" @click="closeFilterMenu">閉じる</button>
                        </div>
                    </div>

                    <label class="filter-field">
                        <span>管理表</span>
                        <select v-model="draftFilters.mode">
                            <option value="all">すべて</option>
                            <option value="managed">表示中のみ</option>
                            <option value="unmanaged_imports">未追加のみ</option>
                        </select>
                    </label>
                </template>

                <div class="filter-section-toggle-row">
                    <button
                        class="filter-section-toggle"
                        type="button"
                        :aria-expanded="isDateFiltersOpen"
                        @click="toggleDateFilters"
                    >
                        <span>詳細条件</span>
                        <span>{{ isDateFiltersOpen ? '詳細条件を閉じる' : '詳細条件を開く' }}</span>
                        <span class="filter-chevron" aria-hidden="true">{{ isDateFiltersOpen ? '⌃' : '⌄' }}</span>
                    </button>
                </div>

                <template v-if="isDateFiltersOpen">
                    <div class="filter-field filter-menu-field">
                        <span>ディレクター</span>
                        <button class="filter-select-button" type="button" @click="toggleFilterMenu('director_id')">
                            <span>{{ selectedOptionLabel(draftFilters.director_id, userStore.users, '未割当', true) }}</span>
                            <span class="filter-chevron" aria-hidden="true">⌄</span>
                        </button>
                        <div v-if="openFilterMenu === 'director_id'" class="filter-menu">
                            <label class="filter-check-row">
                                <input
                                    type="checkbox"
                                    :checked="draftFilters.director_id.length === 0"
                                    @change="clearDraftSelection('director_id')"
                                >
                                <span>すべて</span>
                            </label>
                            <label v-for="user in userStore.users" :key="user.id" class="filter-check-row">
                                <input
                                    type="checkbox"
                                    :checked="draftFilters.director_id.includes(String(user.id))"
                                    @change="toggleDraftSelection('director_id', String(user.id))"
                                >
                                <span>{{ user.name }}</span>
                            </label>
                            <label class="filter-check-row">
                                <input
                                    type="checkbox"
                                    :checked="draftFilters.director_id.includes(EMPTY_FILTER_VALUE)"
                                    @change="toggleDraftSelection('director_id', EMPTY_FILTER_VALUE)"
                                >
                                <span>未割当</span>
                            </label>
                            <button class="filter-menu-close" type="button" @click="closeFilterMenu">閉じる</button>
                        </div>
                    </div>

                    <div class="filter-field filter-menu-field">
                        <span>フラグ</span>
                        <button class="filter-select-button" type="button" @click="toggleFilterMenu('flags')">
                            <span>{{ selectedFlagLabel(draftFilters.flags) }}</span>
                            <span class="filter-chevron" aria-hidden="true">⌄</span>
                        </button>
                        <div v-if="openFilterMenu === 'flags'" class="filter-menu">
                            <label class="filter-check-row">
                                <input
                                    type="checkbox"
                                    :checked="draftFilters.flags.length === 0"
                                    @change="clearDraftSelection('flags')"
                                >
                                <span>すべて</span>
                            </label>
                            <label v-for="flag in flagOptions" :key="flag.value" class="filter-check-row">
                                <input
                                    type="checkbox"
                                    :checked="draftFilters.flags.includes(flag.value)"
                                    @change="toggleDraftSelection('flags', flag.value)"
                                >
                                <span>{{ flag.label }}</span>
                            </label>
                            <button class="filter-menu-close" type="button" @click="closeFilterMenu">閉じる</button>
                        </div>
                    </div>

                    <div class="filter-field filter-date-range">
                        <span>予定開始</span>
                        <div class="filter-date-inputs">
                            <span class="filter-date-control">
                                <input type="date" v-model="draftFilters.planned_start_from">
                                <button v-if="draftFilters.planned_start_from" class="filter-date-clear" type="button" aria-label="予定開始 Fromをクリア" @click="clearDraftDate('planned_start_from')">×</button>
                            </span>
                            <span class="filter-date-sep">〜</span>
                            <span class="filter-date-control">
                                <input type="date" v-model="draftFilters.planned_start_to">
                                <button v-if="draftFilters.planned_start_to" class="filter-date-clear" type="button" aria-label="予定開始 Toをクリア" @click="clearDraftDate('planned_start_to')">×</button>
                            </span>
                        </div>
                    </div>

                    <div class="filter-field filter-date-range">
                        <span>予定終了</span>
                        <div class="filter-date-inputs">
                            <span class="filter-date-control">
                                <input type="date" v-model="draftFilters.planned_end_from">
                                <button v-if="draftFilters.planned_end_from" class="filter-date-clear" type="button" aria-label="予定終了 Fromをクリア" @click="clearDraftDate('planned_end_from')">×</button>
                            </span>
                            <span class="filter-date-sep">〜</span>
                            <span class="filter-date-control">
                                <input type="date" v-model="draftFilters.planned_end_to">
                                <button v-if="draftFilters.planned_end_to" class="filter-date-clear" type="button" aria-label="予定終了 Toをクリア" @click="clearDraftDate('planned_end_to')">×</button>
                            </span>
                        </div>
                    </div>

                    <div class="filter-field filter-date-range">
                        <span>実績開始</span>
                        <div class="filter-date-inputs">
                            <span class="filter-date-control">
                                <input type="date" v-model="draftFilters.actual_start_from">
                                <button v-if="draftFilters.actual_start_from" class="filter-date-clear" type="button" aria-label="実績開始 Fromをクリア" @click="clearDraftDate('actual_start_from')">×</button>
                            </span>
                            <span class="filter-date-sep">〜</span>
                            <span class="filter-date-control">
                                <input type="date" v-model="draftFilters.actual_start_to">
                                <button v-if="draftFilters.actual_start_to" class="filter-date-clear" type="button" aria-label="実績開始 Toをクリア" @click="clearDraftDate('actual_start_to')">×</button>
                            </span>
                        </div>
                    </div>

                    <div class="filter-field filter-date-range">
                        <span>実績終了</span>
                        <div class="filter-date-inputs">
                            <span class="filter-date-control">
                                <input type="date" v-model="draftFilters.actual_end_from">
                                <button v-if="draftFilters.actual_end_from" class="filter-date-clear" type="button" aria-label="実績終了 Fromをクリア" @click="clearDraftDate('actual_end_from')">×</button>
                            </span>
                            <span class="filter-date-sep">〜</span>
                            <span class="filter-date-control">
                                <input type="date" v-model="draftFilters.actual_end_to">
                                <button v-if="draftFilters.actual_end_to" class="filter-date-clear" type="button" aria-label="実績終了 Toをクリア" @click="clearDraftDate('actual_end_to')">×</button>
                            </span>
                        </div>
                    </div>
                </template>
            </div>

            <div class="issue-filter-actions">
                <span
                    class="filter-reset-wrapper"
                    :title="resetFilterTitle"
                >
                    <button class="btn btn-secondary" type="button" :disabled="!hasResettableFilters" @click="resetFilters">
                        絞り込み解除
                    </button>
                </span>
                <button class="btn btn-primary" type="submit">
                    絞り込み
                </button>
            </div>
        </form>

        <div class="master-panel issue-panel">
            <p v-if="issueStore.loading" class="empty-state">読み込み中です。</p>
            <template v-else>
                <div class="issue-count-summary" aria-live="polite">
                    <span>表示件数: {{ visibleIssueCount }}件</span>
                    <div v-if="activeFilterChips.length > 0" class="issue-filter-chips" aria-label="適用中の絞り込み条件">
                        <button
                            v-for="chip in activeFilterChips"
                            :key="chip.key"
                            class="issue-filter-chip"
                            type="button"
                            :aria-label="`${chip.label}を解除`"
                            @click="removeFilterChip(chip)"
                        >
                            <span>{{ chip.label }}</span>
                            <span aria-hidden="true">×</span>
                        </button>
                    </div>
                </div>
                <p v-if="issueStore.issues.length === 0" class="empty-state">
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
                                    <select :value="issue.status_id" @change="updateStatus(issue, Number($event.target.value))">
                                        <option v-for="status in statuses" :key="status.id" :value="status.id">{{ status.label }}</option>
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
            </template>
        </div>
    </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { useEngineerStore } from '../stores/engineers';
import { useIssueStore } from '../stores/issues';
import { useProductStore } from '../stores/products';
import { useUserStore } from '../stores/users';

const statuses = [
    { id: 1, label: '未着手' },
    { id: 2, label: '作業中' },
    { id: 3, label: 'テスト中' },
    { id: 4, label: '完了' },
    { id: 5, label: '保留' },
];
const statusIdsByLabel = Object.fromEntries(statuses.map((status) => [status.label, String(status.id)]));
const DONE_STATUS_ID = 4;
const EMPTY_FILTER_VALUE = '__empty__';
const filterStorageKey = 'issue-list-filters';
const issueStore = useIssueStore();
const productStore = useProductStore();
const engineerStore = useEngineerStore();
const userStore = useUserStore();
const errorMessage = ref('');
const successMessage = ref('');
const filterPanelRef = ref(null);
const openFilterMenu = ref('');
const isMainFiltersOpen = ref(true);
const isDateFiltersOpen = ref(false);
let successMessageTimer = null;
const flagOptions = [
    { value: 'overdue', label: '期限超過' },
    { value: 'due_soon', label: '期限近い' },
    { value: 'none', label: 'フラグなし' },
];
const dateRanges = [
    { label: '予定開始', from: 'planned_start_from', to: 'planned_start_to' },
    { label: '予定終了', from: 'planned_end_from', to: 'planned_end_to' },
    { label: '実績開始', from: 'actual_start_from', to: 'actual_start_to' },
    { label: '実績終了', from: 'actual_end_from', to: 'actual_end_to' },
];

const filters = reactive({
    product_id: [],
    engineer_id: [],
    director_id: [],
    status_id: defaultStatuses(),
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
const draftFilters = reactive(defaultFilters());
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
const visibleIssueCount = computed(() => issueStore.issues.length);
const hasActiveFilters = computed(() => Object.keys(filterParams()).length > 0);
const activeFilterChips = computed(() => buildActiveFilterChips());
const hasResettableFilters = computed(() => activeFilterChips.value.length > 0);
const resetFilterTitle = computed(() => (
    hasResettableFilters.value
        ? '絞り込み条件を既定に戻します'
        : '既定ではステータスが完了以外に絞り込まれているため、解除できません'
));
const emptyStateMessage = computed(() => (
    hasActiveFilters.value
        ? '条件に一致するISSUEはありません。絞り込み条件を変更してください。'
        : 'ISSUEは未登録です。GitHub連携または同期設定を確認してください。'
));
const sortAriaValue = computed(() => (sortState.direction === 'asc' ? 'ascending' : 'descending'));

onMounted(async () => {
    document.addEventListener('pointerdown', handleFilterPanelPointerDown);
    restoreFilters();

    await Promise.all([
        productStore.fetchProducts(),
        engineerStore.fetchEngineers(),
        userStore.fetchUsers(),
    ]);
    await fetchIssues();
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', handleFilterPanelPointerDown);
    clearSuccessMessageTimer();
});

async function fetchIssues() {
    errorMessage.value = '';
    normalizeAppliedFilters();
    saveFilters();

    try {
        await issueStore.fetchIssues(filterParams());
    } catch (error) {
        errorMessage.value = formatError(error, 'ISSUEの取得に失敗しました。');
    }
}

function filterParams() {
    const params = {};

    for (const key of ['product_id', 'engineer_id', 'director_id', 'status_id']) {
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
    if (!hasResettableFilters.value) {
        return;
    }

    closeFilterMenu();
    applyFilters(defaultFilters());
    applyDraftFiltersOnly(defaultFilters());
    saveFilters();
    await fetchIssues();
}

function defaultFilters() {
    return {
        product_id: [],
        engineer_id: [],
        director_id: [],
        status_id: defaultStatuses(),
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

function defaultStatuses() {
    return statuses.filter((status) => status.id !== DONE_STATUS_ID).map((status) => String(status.id));
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

    applyDraftFiltersOnly(filterSnapshot());
    isDateFiltersOpen.value = hasDetailedFilters(filters);
}

function saveFilters() {
    window.localStorage.setItem(filterStorageKey, JSON.stringify({
        product_id: [...filters.product_id],
        engineer_id: [...filters.engineer_id],
        director_id: [...filters.director_id],
        status_id: [...filters.status_id],
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
    assignFilters(filters, nextFilters);
}

function applyDraftFiltersOnly(nextFilters) {
    assignFilters(draftFilters, nextFilters);
}

function assignFilters(target, nextFilters) {
    target.product_id = normalizeFilterArray(nextFilters.product_id).filter((id) => id !== EMPTY_FILTER_VALUE);
    target.engineer_id = normalizeFilterArray(nextFilters.engineer_id);
    target.director_id = normalizeFilterArray(nextFilters.director_id);
    target.status_id = normalizeStatusFilter(nextFilters);
    target.mode = 'all';

    if (nextFilters.mode === '' || nextFilters.mode === 'all') {
        target.mode = 'all';
    }

    if (nextFilters.mode === 'managed') {
        target.mode = 'managed';
    }

    if (nextFilters.mode === 'unmanaged' || nextFilters.mode === 'unmanaged_imports') {
        target.mode = 'unmanaged_imports';
    }

    const dateKeys = [
        'planned_start_from', 'planned_start_to',
        'planned_end_from', 'planned_end_to',
        'actual_start_from', 'actual_start_to',
        'actual_end_from', 'actual_end_to',
    ];
    for (const key of dateKeys) {
        const v = nextFilters[key];
        target[key] = (typeof v === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(v)) ? v : '';
    }

    target.flags = normalizeFilterArray(nextFilters.flags).filter((f) => ['overdue', 'due_soon', 'none'].includes(f));
}

function normalizeAppliedFilters() {
    normalizeAllSelection(filters, 'product_id', productStore.products, false);
    normalizeAllSelection(filters, 'engineer_id', engineerStore.engineers, true);
    normalizeAllSelection(filters, 'director_id', userStore.users, true);

    if (isAllFlagSelection(filters.flags)) {
        filters.flags = [];
    }

    applyDraftFiltersOnly(filterSnapshot());
}

function filterSnapshot(source = filters) {
    return {
        product_id: [...source.product_id],
        engineer_id: [...source.engineer_id],
        director_id: [...source.director_id],
        status_id: [...source.status_id],
        mode: source.mode,
        planned_start_from: source.planned_start_from,
        planned_start_to: source.planned_start_to,
        planned_end_from: source.planned_end_from,
        planned_end_to: source.planned_end_to,
        actual_start_from: source.actual_start_from,
        actual_start_to: source.actual_start_to,
        actual_end_from: source.actual_end_from,
        actual_end_to: source.actual_end_to,
        flags: [...source.flags],
    };
}

function toggleFilterMenu(key) {
    openFilterMenu.value = openFilterMenu.value === key ? '' : key;
}

function closeFilterMenu() {
    openFilterMenu.value = '';
}

function handleFilterPanelPointerDown(event) {
    if (!openFilterMenu.value) {
        return;
    }

    if (!(event.target instanceof Element)) {
        closeFilterMenu();
        return;
    }

    if (event.target.closest('.filter-menu, .filter-select-button')) {
        return;
    }

    closeFilterMenu();
}

function toggleMainFilters() {
    closeFilterMenu();
    isMainFiltersOpen.value = !isMainFiltersOpen.value;
}

function toggleDateFilters() {
    closeFilterMenu();
    isDateFiltersOpen.value = !isDateFiltersOpen.value;
}

function clearDraftSelection(key) {
    draftFilters[key] = [];
}

async function applyDraftFilters() {
    const dateError = dateRangeError(draftFilters);

    if (dateError) {
        errorMessage.value = dateError;
        return;
    }

    closeFilterMenu();
    applyFilters(filterSnapshot(draftFilters));
    await fetchIssues();
}

function clearDraftDate(key) {
    draftFilters[key] = '';
}

function normalizeFilterArray(value) {
    if (!Array.isArray(value)) {
        return value ? [String(value)] : [];
    }

    return value.map((item) => String(item)).filter((item) => item !== '');
}

function normalizeStatusFilter(nextFilters) {
    if (Object.prototype.hasOwnProperty.call(nextFilters, 'status_id')) {
        return normalizeFilterArray(nextFilters.status_id).filter(isKnownStatusId);
    }

    if (!Object.prototype.hasOwnProperty.call(nextFilters, 'status')) {
        return defaultStatuses();
    }

    const legacyValues = normalizeFilterArray(nextFilters.status);

    if (legacyValues.length === 0) {
        return [];
    }

    const migratedValues = legacyValues
        .map((status) => statusIdsByLabel[status] ?? status)
        .filter(isKnownStatusId);

    return migratedValues.length > 0 ? migratedValues : defaultStatuses();
}

function isKnownStatusId(statusId) {
    return statuses.some((status) => String(status.id) === String(statusId));
}

function toggleDraftSelection(key, value) {
    const selected = draftFilters[key];
    const index = selected.indexOf(value);

    if (index === -1) {
        selected.push(value);
        return;
    }

    selected.splice(index, 1);
}

function selectedOptionLabel(selectedIds, options, emptyLabel = '未割当', includesEmpty = false) {
    if (selectedIds.length === 0 || isAllOptionSelection(selectedIds, options, includesEmpty)) {
        return 'すべて';
    }

    return selectedIds.map((id) => (
        id === EMPTY_FILTER_VALUE
            ? emptyLabel
            : options.find((option) => String(option.id) === String(id))?.name ?? `ID:${id}`
    )).join('、');
}

function selectedStatusLabel(selectedStatuses) {
    if (selectedStatuses.length === 0 || selectedStatuses.length === statuses.length) {
        return 'すべて';
    }

    if (isDefaultStatusSelection(selectedStatuses)) {
        return '完了以外';
    }

    return selectedStatuses.map(statusLabel).join('、');
}

function statusLabel(statusId) {
    return statuses.find((status) => String(status.id) === String(statusId))?.label ?? `ID:${statusId}`;
}

function selectedFlagLabel(selectedFlags) {
    if (selectedFlags.length === 0 || isAllFlagSelection(selectedFlags)) {
        return 'すべて';
    }

    return selectedFlags.map(flagLabel).join('、');
}

function flagLabel(flag) {
    if (flag === 'overdue') {
        return '期限超過';
    }

    if (flag === 'due_soon') {
        return '期限近い';
    }

    if (flag === 'none') {
        return 'フラグなし';
    }

    return flag;
}

async function removeFilterChip(chip) {
    if (chip.type === 'array') {
        filters[chip.field] = chip.field === 'status_id' ? defaultStatuses() : [];
    }

    if (chip.type === 'mode') {
        filters.mode = 'all';
    }

    if (chip.type === 'date_range') {
        filters[chip.from] = '';
        filters[chip.to] = '';
    }

    applyDraftFiltersOnly(filterSnapshot());
    saveFilters();
    await fetchIssues();
}

function buildActiveFilterChips() {
    const chips = [];

    pushArrayChip(chips, 'product_id', 'プロダクト', selectedOptionLabel(filters.product_id, productStore.products, '未設定'), false);
    pushArrayChip(chips, 'engineer_id', 'エンジニア', selectedOptionLabel(filters.engineer_id, engineerStore.engineers, '未割当', true), true);
    pushArrayChip(chips, 'director_id', 'ディレクター', selectedOptionLabel(filters.director_id, userStore.users, '未割当', true), true);

    if (!isDefaultStatusSelection(filters.status_id)) {
        if (filters.status_id.length === 0) {
            chips.push({
                key: 'status_id',
                type: 'array',
                field: 'status_id',
                label: 'ステータス: すべて',
            });
        } else {
            pushArrayChip(chips, 'status_id', 'ステータス', selectedStatusLabel(filters.status_id));
        }
    }

    if (filters.mode !== 'all') {
        chips.push({
            key: 'mode',
            type: 'mode',
            label: `管理表: ${filters.mode === 'managed' ? '表示中のみ' : '未追加のみ'}`,
        });
    }

    for (const range of dateRanges) {
        const from = filters[range.from];
        const to = filters[range.to];

        if (from || to) {
            chips.push({
                key: `${range.from}:${range.to}`,
                type: 'date_range',
                from: range.from,
                to: range.to,
                label: `${range.label}: ${from || '指定なし'}〜${to || '指定なし'}`,
            });
        }
    }

    if (!isAllFlagSelection(filters.flags)) {
        pushArrayChip(chips, 'flags', 'フラグ', selectedFlagLabel(filters.flags));
    }

    return chips;
}

function pushArrayChip(chips, field, label, valueLabel, includesEmpty = false) {
    if (filters[field].length === 0) {
        return;
    }

    if (field !== 'status_id') {
        const optionsByField = {
            product_id: productStore.products,
            engineer_id: engineerStore.engineers,
            director_id: userStore.users,
        };

        if (isAllOptionSelection(filters[field], optionsByField[field] ?? [], includesEmpty)) {
            return;
        }
    }

    chips.push({
        key: field,
        type: 'array',
        field,
        label: `${label}: ${valueLabel}`,
    });
}

function normalizeAllSelection(target, field, options, includesEmpty) {
    if (isAllOptionSelection(target[field], options, includesEmpty)) {
        target[field] = [];
    }
}

function isAllOptionSelection(selectedIds, options, includesEmpty) {
    if (options.length === 0) {
        return false;
    }

    const expectedValues = options.map((option) => String(option.id));

    if (includesEmpty) {
        expectedValues.push(EMPTY_FILTER_VALUE);
    }

    return hasSameValues(selectedIds, expectedValues);
}

function isAllFlagSelection(selectedFlags) {
    return hasSameValues(selectedFlags, flagOptions.map((flag) => flag.value));
}

function isDefaultStatusSelection(selectedStatuses) {
    return hasSameValues(selectedStatuses, defaultStatuses());
}

function hasSameValues(left, right) {
    if (left.length !== right.length) {
        return false;
    }

    const rightValues = new Set(right.map((value) => String(value)));

    return left.every((value) => rightValues.has(String(value)));
}

function hasDetailedFilters(source) {
    return source.director_id.length > 0
        || source.flags.length > 0
        || dateRanges.some((range) => source[range.from] || source[range.to]);
}

function dateRangeError(source) {
    for (const range of dateRanges) {
        if (source[range.from] && source[range.to] && source[range.from] > source[range.to]) {
            return `${range.label}はFromがTo以前になるように指定してください。`;
        }
    }

    return '';
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
            return issue.status_id;
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

async function updateStatus(issue, statusId) {
    try {
        await issueStore.updateStatus(issue.id, statusId);
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
    try {
        await issueStore.updateSchedule(issue.id, patch);
        showSuccessMessage('スケジュールを更新しました。');
    } catch (error) {
        await fetchIssues();
        errorMessage.value = formatError(error, 'スケジュールの更新に失敗しました。');
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
