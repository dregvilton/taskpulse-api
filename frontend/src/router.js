import { createRouter, createWebHistory } from 'vue-router'
import { session } from './session.js'
import LoginView from './views/LoginView.vue'
import TasksView from './views/TasksView.vue'
import TaskView from './views/TaskView.vue'
import UsersView from './views/UsersView.vue'
import AnalyticsView from './views/AnalyticsView.vue'

export const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', redirect: { name: 'tasks' } },
    { path: '/login', name: 'login', component: LoginView },
    { path: '/tasks', name: 'tasks', component: TasksView },
    { path: '/tasks/new', name: 'new-task', component: TaskView },
    { path: '/tasks/:id(\\d+)', name: 'task', component: TaskView },
    { path: '/users', name: 'users', component: UsersView },
    { path: '/analytics', name: 'analytics', component: AnalyticsView },
    { path: '/:pathMatch(.*)*', redirect: { name: 'tasks' } },
  ],
})

router.beforeEach((to) => {
  if (to.name === 'login') {
    return session.token ? { name: 'tasks' } : true
  }
  if (!session.token) {
    return { name: 'login', query: { next: to.fullPath } }
  }
  return true
})
