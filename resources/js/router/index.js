import { createRouter, createWebHistory } from 'vue-router';
import TableView from '../views/TableView.vue';
import DashboardView from '../views/DashboardView.vue';
import LoginView from '../views/LoginView.vue';
import IssueListView from '../views/IssueListView.vue';
import UserListView from '../views/UserListView.vue';
import UserFormView from '../views/UserFormView.vue';
import EngineerListView from '../views/EngineerListView.vue';
import ProductListView from '../views/ProductListView.vue';
import ProductRepositoryView from '../views/ProductRepositoryView.vue';

const routes = [
    { path: '/login', component: LoginView, meta: { guest: true, title: 'ログイン' } },
    { path: '/', redirect: '/table' },
    { path: '/table', component: TableView, meta: { requiresAuth: true, title: '管理表' } },
    { path: '/dashboard', component: DashboardView, meta: { requiresAuth: true, title: 'ダッシュボード' } },
    { path: '/issues', component: IssueListView, meta: { requiresAuth: true, title: 'ISSUE一覧' } },
    { path: '/users', component: UserListView, meta: { requiresAuth: true, title: 'ユーザー管理' } },
    { path: '/users/new', component: UserFormView, meta: { requiresAuth: true, title: 'ユーザー作成' } },
    { path: '/users/:id/edit', component: UserFormView, meta: { requiresAuth: true, title: 'ユーザー編集' } },
    { path: '/engineers', component: EngineerListView, meta: { requiresAuth: true, title: 'エンジニア管理' } },
    { path: '/products', component: ProductListView, meta: { requiresAuth: true, title: 'プロダクト管理' } },
    { path: '/products/:id/repository', component: ProductRepositoryView, meta: { requiresAuth: true, title: 'リポジトリ連携設定' } },
    { path: '/:pathMatch(.*)*', redirect: '/table' },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach((to) => {
    document.title = `${to.meta.title ?? 'ISSUE管理'} | ISSUE管理`;
});

export default router;
