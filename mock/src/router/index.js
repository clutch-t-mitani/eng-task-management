import { createRouter, createWebHashHistory } from 'vue-router'
import TableView from '../views/TableView.vue'
import DashboardView from '../views/DashboardView.vue'

const routes = [
  { path: '/', redirect: '/table' },
  { path: '/table', component: TableView, meta: { title: '管理表' } },
  { path: '/dashboard', component: DashboardView, meta: { title: 'ダッシュボード' } },
]

export default createRouter({
  history: createWebHashHistory(),
  routes,
})
