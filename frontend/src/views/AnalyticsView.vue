<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../api.js'
import { formatDuration, toApiDate } from '../format.js'

const from = ref('')
const to = ref('')
const analytics = ref(null)
const loading = ref(true)
const error = ref('')
let loadNumber = 0

async function load() {
  const currentLoad = ++loadNumber
  if (from.value && to.value && from.value > to.value) {
    loading.value = false
    error.value = 'Начало периода не может быть позже конца.'
    return
  }
  loading.value = true
  error.value = ''
  try {
    const result = await api.getAnalytics({
      createdFrom: toApiDate(from.value),
      createdTo: toApiDate(to.value, true),
    })
    if (currentLoad === loadNumber) analytics.value = result
  } catch (failure) {
    if (currentLoad === loadNumber) error.value = failure.message
  } finally {
    if (currentLoad === loadNumber) loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page-content">
    <div class="page-heading">
      <div>
        <p class="eyebrow">ВИДЕТЬ БОЛЬШЕ</p>
        <h1>Аналитика<span class="heading-dot">.</span></h1>
        <p class="page-subtitle">Как движутся ваши задачи — коротко и по делу.</p>
      </div>
    </div>

    <form class="date-toolbar" @submit.prevent="load">
      <label>Созданы с <input v-model="from" type="date" name="from" /></label>
      <label>По <input v-model="to" type="date" name="to" /></label>
      <button class="button button--secondary" type="submit">Применить</button>
    </form>

    <div v-if="loading" class="state-panel" role="status">
      <span class="spinner"></span> Считаем показатели…
    </div>
    <div v-else-if="error" class="state-panel state-panel--error" role="alert">
      {{ error }} <button class="text-button" type="button" @click="load">Повторить</button>
    </div>
    <template v-else-if="analytics">
      <div class="stats-grid">
        <article class="stat-card">
          <span class="stat-label">Создано задач</span><strong>{{ analytics.totalCreated }}</strong
          ><span class="stat-hint">За выбранный период</span>
        </article>
        <article class="stat-card stat-card--accent">
          <span class="stat-label">Завершено</span><strong>{{ analytics.totalCompleted }}</strong
          ><span class="stat-hint">Доведено до результата</span>
        </article>
        <article class="stat-card">
          <span class="stat-label">Выполнение</span
          ><strong>{{ Math.round(analytics.completionPercent) }}<small>%</small></strong
          ><span class="stat-hint">Доля завершённых задач</span>
        </article>
        <article class="stat-card">
          <span class="stat-label">Среднее время</span
          ><strong class="stat-duration">{{
            formatDuration(analytics.avgCompletionTimeSeconds)
          }}</strong
          ><span class="stat-hint">От создания до завершения</span>
        </article>
      </div>
      <div class="progress-panel">
        <div>
          <p class="eyebrow">ОБЩАЯ КАРТИНА</p>
          <h2>Ваш прогресс</h2>
          <p>
            {{
              analytics.totalCreated === 0
                ? 'Создайте задачу, чтобы увидеть первые показатели.'
                : `${analytics.totalCompleted} из ${analytics.totalCreated} задач завершено`
            }}
          </p>
        </div>
        <div
          class="progress-chart"
          role="img"
          :aria-label="`Выполнено ${Math.round(analytics.completionPercent)} процентов задач`"
          :style="{ '--progress': `${analytics.completionPercent}%` }"
        >
          <span>{{ Math.round(analytics.completionPercent) }}%</span>
        </div>
      </div>
    </template>
  </div>
</template>
