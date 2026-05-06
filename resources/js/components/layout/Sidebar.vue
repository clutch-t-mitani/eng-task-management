<template>
    <nav class="sidebar">
        <div class="brand">
            <span class="brand-icon">ISS</span>
            <span class="brand-name">ISSUE管理</span>
        </div>
        <router-link to="/table" class="nav-link">
            <span class="nav-icon">表</span> 管理表
        </router-link>
        <router-link to="/dashboard" class="nav-link">
            <span class="nav-icon">集</span> ダッシュボード
        </router-link>
        <router-link to="/issues" class="nav-link">
            <span class="nav-icon">件</span> ISSUE一覧
        </router-link>
        <router-link to="/users" class="nav-link">
            <span class="nav-icon">人</span> ユーザー管理
        </router-link>
        <router-link to="/engineers" class="nav-link">
            <span class="nav-icon">技</span> エンジニア管理
        </router-link>
        <router-link to="/products" class="nav-link">
            <span class="nav-icon">品</span> プロダクト管理
        </router-link>
        <div class="sidebar-footer">
            <p v-if="authStore.user" class="user-name">{{ authStore.user.name }}</p>
            <button class="logout-button" type="button" @click="logout">
                ログアウト
            </button>
        </div>
    </nav>
</template>

<script setup>
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';

const router = useRouter();
const authStore = useAuthStore();

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
}

.brand {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 20px 16px 16px;
    border-bottom: 1px solid #2d3748;
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
    width: 18px;
    font-size: 12px;
    font-weight: 700;
    text-align: center;
}

.sidebar-footer {
    margin-top: auto;
    padding: 16px;
    border-top: 1px solid #2d3748;
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
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.logout-button:hover {
    background: #2d3748;
    color: #fff;
}
</style>
