<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../api.js'
import { formatDate } from '../format.js'

const users = ref([])
const loading = ref(true)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    const result = await api.listUsers()
    users.value = result?.items || []
  } catch (failure) {
    error.value = failure.message
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page-content page-content--narrow">
    <div class="page-heading">
      <div>
        <p class="eyebrow">УЧЁТНАЯ ЗАПИСЬ</p>
        <h1>Профиль<span class="heading-dot">.</span></h1>
        <p class="page-subtitle">
          Список пользователей в API доступен только в пределах вашего аккаунта.
        </p>
      </div>
    </div>
    <div v-if="loading" class="state-panel" role="status">
      <span class="spinner"></span> Загружаем профиль…
    </div>
    <div v-else-if="error" class="state-panel state-panel--error" role="alert">
      {{ error }} <button class="text-button" type="button" @click="load">Повторить</button>
    </div>
    <div v-else-if="users.length === 0" class="empty-panel">
      <h2>Профиль не найден</h2>
      <p>Попробуйте обновить страницу.</p>
    </div>
    <article v-for="user in users" v-else :key="user.id" class="profile-card">
      <div class="profile-avatar" aria-hidden="true">{{ user.fullName?.charAt(0) || 'У' }}</div>
      <div class="profile-details">
        <span class="eyebrow">ПОЛЬЗОВАТЕЛЬ #{{ user.id }}</span>
        <h2>{{ user.fullName }}</h2>
        <dl>
          <div>
            <dt>Почта</dt>
            <dd>{{ user.email || 'Не указана' }}</dd>
          </div>
          <div>
            <dt>Телефон</dt>
            <dd>{{ user.phone || 'Не указан' }}</dd>
          </div>
          <div>
            <dt>В проекте с</dt>
            <dd>{{ formatDate(user.createdAt) }}</dd>
          </div>
        </dl>
      </div>
    </article>
  </div>
</template>
