<template>
    <div class="app-layout">
        <Sidebar />
        <main class="main-content">
            <router-view />
        </main>
    </div>
</template>

<script setup>
import Sidebar from './Sidebar.vue';
</script>

<style>
html,
body,
#app {
    height: 100%;
    margin: 0;
}

.app-layout {
    display: flex;
    height: 100vh;
    overflow: hidden;
}

.main-content {
    flex: 1;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    background: #f5f6fa;
}
</style>
