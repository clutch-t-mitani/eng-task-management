<template>
    <nav :class="['sidebar', { 'is-collapsed': isCollapsed }]">
        <div class="brand">
            <span class="brand-icon">ISS</span>
            <span v-if="!isCollapsed" class="brand-name">ISSUE管理</span>
            <button
                class="collapse-button"
                type="button"
                :aria-label="isCollapsed ? 'サイドメニューを開く' : 'サイドメニューを畳む'"
                :title="isCollapsed ? '開く' : '畳む'"
                @click="toggleSidebar"
            >
                {{ isCollapsed ? '›' : '‹' }}
            </button>
        </div>
        <router-link to="/table" class="nav-link" title="管理表">
            <span class="nav-icon">表</span>
            <span v-if="!isCollapsed" class="nav-label">管理表</span>
        </router-link>
        <router-link to="/dashboard" class="nav-link" title="ダッシュボード">
            <span class="nav-icon">集</span>
            <span v-if="!isCollapsed" class="nav-label">ダッシュボード</span>
        </router-link>
        <router-link to="/issues" class="nav-link" title="ISSUE一覧">
            <span class="nav-icon">件</span>
            <span v-if="!isCollapsed" class="nav-label">ISSUE一覧</span>
        </router-link>
        <router-link to="/users" class="nav-link" title="ユーザー管理">
            <span class="nav-icon">人</span>
            <span v-if="!isCollapsed" class="nav-label">ユーザー管理</span>
        </router-link>
        <router-link to="/engineers" class="nav-link" title="エンジニア管理">
            <span class="nav-icon">技</span>
            <span v-if="!isCollapsed" class="nav-label">エンジニア管理</span>
        </router-link>
        <router-link to="/products" class="nav-link" title="プロダクト管理">
            <span class="nav-icon">品</span>
            <span v-if="!isCollapsed" class="nav-label">プロダクト管理</span>
        </router-link>
        <div class="sidebar-footer">
            <p v-if="authStore.user && !isCollapsed" class="user-name">{{ authStore.user.name }}</p>
            <button
                class="logout-button"
                type="button"
                :aria-label="isCollapsed ? 'ログアウト' : undefined"
                :title="isCollapsed ? 'ログアウト' : undefined"
                @click="logout"
            >
                <span class="logout-icon">出</span>
                <span v-if="!isCollapsed">ログアウト</span>
            </button>
        </div>
    </nav>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';

const router = useRouter();
const authStore = useAuthStore();
const storageKey = 'issue-sidebar-collapsed';
const isCollapsed = ref(false);

onMounted(() => {
    isCollapsed.value = localStorage.getItem(storageKey) === 'true';
});

watch(isCollapsed, (value) => {
    localStorage.setItem(storageKey, String(value));
});

function toggleSidebar() {
    isCollapsed.value = !isCollapsed.value;
}

async function logout() {
    await authStore.logout();
    await router.push('/login');
}
</script>

<style scoped>
.sidebar {
    width: 200px;
    flex-shrink: 0;
    background: #1a202c;
    color: #cbd5e0;
    display: flex;
    flex-direction: column;
    padding: 0;
    transition: width 0.18s ease;
}

.sidebar.is-collapsed {
    width: 64px;
}

.brand {
    position: relative;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 20px 16px 16px;
    border-bottom: 1px solid #2d3748;
}

.is-collapsed .brand {
    justify-content: center;
    gap: 0;
    padding: 18px 12px 14px;
}

.brand-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 6px;
    background: #2b6cb0;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
}

.brand-name {
    font-size: 15px;
    font-weight: 700;
    color: #fff;
}

.collapse-button {
    margin-left: auto;
    width: 26px;
    height: 26px;
    border: 1px solid #4a5568;
    border-radius: 6px;
    background: transparent;
    color: #cbd5e0;
    font-size: 20px;
    line-height: 1;
    cursor: pointer;
}

.collapse-button:hover {
    background: #2d3748;
    color: #fff;
}

.is-collapsed .collapse-button {
    position: absolute;
    top: 19px;
    right: -13px;
    z-index: 2;
    margin-left: 0;
    background: #1a202c;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    color: #a0aec0;
    text-decoration: none;
    font-size: 14px;
    transition: background 0.15s, color 0.15s;
}

.is-collapsed .nav-link {
    justify-content: center;
    gap: 0;
    padding: 13px 0;
}

.nav-link:hover {
    background: #2d3748;
    color: #fff;
}

.nav-link.router-link-active {
    background: #2d3748;
    color: #fff;
    border-left: 3px solid #4299e1;
}

.nav-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    font-size: 12px;
    font-weight: 700;
    text-align: center;
}

.nav-label {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.sidebar-footer {
    margin-top: auto;
    padding: 16px;
    border-top: 1px solid #2d3748;
}

.is-collapsed .sidebar-footer {
    padding: 12px;
}

.user-name {
    margin: 0 0 10px;
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    line-height: 1.4;
    overflow-wrap: anywhere;
}

.logout-button {
    width: 100%;
    border: 1px solid #4a5568;
    border-radius: 6px;
    padding: 8px 10px;
    background: transparent;
    color: #cbd5e0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.is-collapsed .logout-button {
    height: 38px;
    padding: 0;
}

.logout-icon {
    display: none;
}

.is-collapsed .logout-icon {
    display: inline;
}

.logout-button:hover {
    background: #2d3748;
    color: #fff;
}
</style>
