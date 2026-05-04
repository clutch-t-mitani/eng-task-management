<template>
  <div class="app-layout">
    <nav class="sidebar">
      <div class="brand">
        <span class="brand-icon">📋</span>
        <span class="brand-name">ISSUE管理</span>
      </div>
      <router-link to="/table" class="nav-link">
        <span class="nav-icon">📊</span> 管理表
      </router-link>
      <router-link to="/dashboard" class="nav-link">
        <span class="nav-icon">📈</span> ダッシュボード
      </router-link>
    </nav>
    <main class="main-content">
      <router-view />
    </main>
  </div>
</template>

<style>
html, body, #app { height: 100%; margin: 0; }
.app-layout { display: flex; height: 100vh; overflow: hidden; }
.sidebar {
  width: 200px; flex-shrink: 0;
  background: #1a202c; color: #cbd5e0;
  display: flex; flex-direction: column;
  padding: 0;
}
.brand {
  display: flex; align-items: center; gap: 10px;
  padding: 20px 16px 16px;
  border-bottom: 1px solid #2d3748;
}
.brand-icon { font-size: 20px; }
.brand-name { font-size: 15px; font-weight: 700; color: #fff; }
.nav-link {
  display: flex; align-items: center; gap: 10px;
  padding: 12px 16px;
  color: #a0aec0; text-decoration: none; font-size: 14px;
  transition: background 0.15s, color 0.15s;
}
.nav-link:hover { background: #2d3748; color: #fff; }
.nav-link.router-link-active { background: #2d3748; color: #fff; border-left: 3px solid #4299e1; }
.nav-icon { font-size: 16px; }
.main-content { flex: 1; overflow: hidden; display: flex; flex-direction: column; background: #f5f6fa; }
</style>
