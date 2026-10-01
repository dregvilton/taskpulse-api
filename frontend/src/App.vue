<script setup>
import { ref } from 'vue'
import { RouterLink, RouterView, useRouter } from 'vue-router'
import { api } from './api.js'
import { clearSession, session } from './session.js'

const router = useRouter()
const logoutError = ref('')
const loggingOut = ref(false)

async function logout() {
  logoutError.value = ''
  loggingOut.value = true
  try {
    await api.logout()
    clearSession()
    await router.replace({ name: 'login' })
  } catch (error) {
    if (error.status === 401) {
      clearSession()
      await router.replace({ name: 'login' })
    } else {
      logoutError.value = error.message
    }
  } finally {
    loggingOut.value = false
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
        <p v-if="logoutError" class="sidebar-error" role="alert">{{ logoutError }}</p>
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
