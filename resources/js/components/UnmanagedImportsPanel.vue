<template>
    <div class="unmanaged-panel">
        <div class="panel-header">
            <h3 class="panel-title">GitHubから取り込み済み（未管理）</h3>
            <button class="btn-icon" type="button" :disabled="loading" @click="fetchItems">
                {{ loading ? '読込中…' : '更新' }}
            </button>
        </div>

        <div v-if="loading" class="panel-empty">読み込み中…</div>
        <div v-else-if="items.length === 0" class="panel-empty">未管理のISSUEはありません。</div>
        <table v-else class="panel-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>タイトル</th>
                    <th>状態</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="item in items" :key="item.id">
                    <td class="num-col">{{ item.github_issue_number }}</td>
                    <td class="title-col">
                        <a
                            v-if="item.github_url"
                            :href="item.github_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="issue-link"
                        >
                            {{ item.title }}
                        </a>
                        <span v-else>{{ item.title }}</span>
                    </td>
                    <td>
                        <span :class="`gh-state gh-state-${item.github_state}`">{{ item.github_state }}</span>
                    </td>
                    <td class="action-col">
                        <button
                            class="btn-add"
                            type="button"
                            :disabled="adding.has(item.id)"
                            @click="addToManaged(item)"
                        >
                            {{ adding.has(item.id) ? '追加中…' : '管理表に追加' }}
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>

        <p v-if="errorMessage" class="panel-error">{{ errorMessage }}</p>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import { useFilterStore } from '../stores/filters';
import { useIssueStore } from '../stores/issues';

const emit = defineEmits(['managed']);

const filterStore = useFilterStore();
const issueStore = useIssueStore();

const items = ref([]);
const loading = ref(false);
const errorMessage = ref('');
const adding = ref(new Set());

watch(
    () => filterStore.product_id,
    (id) => {
        if (id != null) {
            fetchItems();
        } else {
            items.value = [];
        }
    },
    { immediate: true },
);

async function fetchItems() {
    if (filterStore.product_id == null) return;
    loading.value = true;
    errorMessage.value = '';
    try {
        const { data } = await axios.get('/api/v1/issues', {
            params: { unmanaged_imports: true, product_id: filterStore.product_id },
        });
        items.value = data;
    } catch {
        errorMessage.value = '未管理ISSUEの取得に失敗しました。';
    } finally {
        loading.value = false;
    }
}

async function addToManaged(item) {
    const next = new Set(adding.value);
    next.add(item.id);
    adding.value = next;
    errorMessage.value = '';
    try {
        await issueStore.toggleManaged(item.id);
        items.value = items.value.filter((i) => i.id !== item.id);
        emit('managed', item);
    } catch {
        errorMessage.value = `「${item.title}」の追加に失敗しました。`;
    } finally {
        const updated = new Set(adding.value);
        updated.delete(item.id);
        adding.value = updated;
    }
}
</script>

<style scoped>
.unmanaged-panel {
    border-top: 2px solid #bee3f8;
    background: #ebf8ff;
    padding: 14px 20px;
    flex-shrink: 0;
}
.panel-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
}
.panel-title { font-size: 13px; font-weight: 700; color: #2a4365; }
.btn-icon {
    background: none;
    border: 1px solid #90cdf4;
    border-radius: 6px;
    color: #2b6cb0;
    cursor: pointer;
    font-size: 12px;
    padding: 3px 10px;
}
.btn-icon:disabled { cursor: not-allowed; opacity: 0.5; }
.panel-empty { color: #4a5568; font-size: 12px; padding: 8px 0; }

.panel-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.panel-table th {
    background: #dbeafe;
    border-bottom: 1px solid #bee3f8;
    color: #2a4365;
    font-weight: 700;
    padding: 5px 8px;
    text-align: left;
}
.panel-table td { border-bottom: 1px solid #bee3f8; padding: 5px 8px; vertical-align: middle; }
.panel-table tr:last-child td { border-bottom: none; }

.num-col { width: 50px; color: #4a5568; }
.title-col { max-width: 400px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.action-col { text-align: right; white-space: nowrap; }

.issue-link { color: #2b6cb0; text-decoration: none; }
.issue-link:hover { text-decoration: underline; }

.gh-state {
    border-radius: 10px;
    font-size: 10px;
    font-weight: 600;
    padding: 2px 6px;
    white-space: nowrap;
}
.gh-state-open { background: #c6f6d5; color: #276749; }
.gh-state-closed { background: #fed7d7; color: #9b2c2c; }

.btn-add {
    background: #4299e1;
    border: none;
    border-radius: 6px;
    color: #fff;
    cursor: pointer;
    font-size: 11px;
    font-weight: 600;
    padding: 4px 10px;
}
.btn-add:hover:not(:disabled) { background: #3182ce; }
.btn-add:disabled { cursor: not-allowed; opacity: 0.6; }

.panel-error { color: #c53030; font-size: 12px; margin-top: 8px; }
</style>
