<script setup>
import { ref } from 'vue'
import { RouterLink, RouterView, useRouter } from 'vue-router'
import { api } from './api.js'
import { clearSession, session } from './session.js'

const router = useRouter()
const loggingOut = ref(false)

async function logout() {
  loggingOut.value = true
  let revokeFailed = false
  try {
    await api.logout()
  } catch (failure) {
    revokeFailed = failure.status !== 401
  } finally {
    clearSession()
    loggingOut.value = false
    await router.replace(
      revokeFailed ? { name: 'login', query: { reason: 'logout-unconfirmed' } } : { name: 'login' },
    )
  }
}
</script>

<template>
  <div class="app-shell" :class="{ 'app-shell--guest': !session.token }">
    <aside v-if="session.token" class="sidebar">
      <RouterLink class="brand" :to="{ name: 'tasks' }" aria-label="TaskPulse, к задачам">
        <span class="brand-mark" aria-hidden="true"><span></span><span></span><span></span></span>
        <span>task<span class="brand-accent">pulse</span><small>Рабочее пространство</small></span>
      </RouterLink>

      <nav class="navigation" aria-label="Основная навигация">
        <RouterLink :to="{ name: 'tasks' }"><span class="nav-icon">▦</span> Задачи</RouterLink>
        <RouterLink :to="{ name: 'analytics' }"
          ><span class="nav-icon">◫</span> Аналитика</RouterLink
        >
        <RouterLink :to="{ name: 'users' }"><span class="nav-icon">◎</span> Профиль</RouterLink>
      </nav>

      <div class="sidebar-bottom">
        <p class="sidebar-note">Ваши задачи и показатели — в одном месте.</p>
        <button class="logout-button" type="button" :disabled="loggingOut" @click="logout">
          {{ loggingOut ? 'Выходим…' : 'Выйти из аккаунта' }} <span aria-hidden="true">↗</span>
        </button>
      </div>
    </aside>

    <main class="main-area">
      <header v-if="session.token" class="topbar">
        <span class="eyebrow">TaskPulse / панель управления</span>
        <span class="topbar-user"
          ><span class="online-dot"></span> Аккаунт #{{ session.userId }}</span
        >
      </header>
      <RouterView />
    </main>
  </div>
</template>
