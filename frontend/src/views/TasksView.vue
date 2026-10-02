<script setup>
import { ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../api.js'
import { formatDate, toApiDate } from '../format.js'

const status = ref('all')
const sort = ref('-createdAt')
const createdFrom = ref('')
const createdTo = ref('')
const appliedFrom = ref('')
const appliedTo = ref('')
const filterError = ref('')
const page = ref(1)
const items = ref([])
const pagination = ref({ page: 1, pages: 1, total: 0 })
const loading = ref(true)
const error = ref('')
const actionError = ref('')
const updatingId = ref(null)
let loadNumber = 0

async function load() {
  const currentLoad = ++loadNumber
  loading.value = true
  error.value = ''
  try {
    const result = await api.listTasks({
      page: page.value,
      perPage: 9,
      sort: sort.value,
      completed: status.value === 'all' ? null : status.value === 'done',
      createdFrom: toApiDate(appliedFrom.value),
      createdTo: toApiDate(appliedTo.value, true),
    })
    if (currentLoad !== loadNumber) return
    items.value = result.items
    pagination.value = result.pagination
  } catch (failure) {
    if (currentLoad === loadNumber) error.value = failure.message
  } finally {
    if (currentLoad === loadNumber) loading.value = false
  }
}

watch([status, sort, appliedFrom, appliedTo], () => {
  page.value = 1
})
watch([status, sort, appliedFrom, appliedTo, page], load, { immediate: true })

function applyDates() {
  if (createdFrom.value && createdTo.value && createdFrom.value > createdTo.value) {
    filterError.value = 'Начало периода не может быть позже конца.'
    return
  }
  filterError.value = ''
  appliedFrom.value = createdFrom.value
  appliedTo.value = createdTo.value
}

function resetDates() {
  createdFrom.value = ''
  createdTo.value = ''
  applyDates()
}

async function complete(task) {
  if (updatingId.value !== null) return
  actionError.value = ''
  updatingId.value = task.id
  try {
    await api.updateTask(task.id, { completed: !task.completed })
    await load()
  } catch (failure) {
    actionError.value = failure.message
  } finally {
    updatingId.value = null
  }
}
</script>

<template>
  <div class="page-content">
    <div class="page-heading">
      <div>
        <p class="eyebrow">ВАШЕ ПРОСТРАНСТВО</p>
        <h1>Мои задачи<span class="heading-dot">.</span></h1>
        <p class="page-subtitle">Держите фокус на том, что важно сегодня.</p>
      </div>
      <RouterLink class="button button--primary" :to="{ name: 'new-task' }"
        >Новая задача <span aria-hidden="true">＋</span></RouterLink
      >
    </div>

    <div class="toolbar">
      <div class="segmented" role="group" aria-label="Статус задачи">
        <button
          v-for="option in [
            { value: 'all', label: 'Все' },
            { value: 'active', label: 'В работе' },
            { value: 'done', label: 'Завершены' },
          ]"
          :key="option.value"
          type="button"
          :class="{ active: status === option.value }"
          :aria-pressed="status === option.value"
          @click="status = option.value"
        >
          {{ option.label }}
        </button>
      </div>
      <label class="sort-control"
        >Сортировка
        <select v-model="sort">
          <option value="-createdAt">Сначала новые</option>
          <option value="createdAt">Сначала старые</option>
          <option value="title">По названию</option>
          <option value="-completedAt">Недавно завершённые</option>
        </select>
      </label>
    </div>

    <form class="date-toolbar" @submit.prevent="applyDates">
      <label>Созданы с <input v-model="createdFrom" type="date" name="createdFrom" /></label>
      <label>По <input v-model="createdTo" type="date" name="createdTo" /></label>
      <button class="button button--secondary" type="submit">Применить период</button>
      <button v-if="appliedFrom || appliedTo" class="text-button" type="button" @click="resetDates">
        Сбросить
      </button>
    </form>
    <p v-if="filterError" class="error-banner" role="alert">{{ filterError }}</p>

    <p v-if="actionError" class="error-banner" role="alert">{{ actionError }}</p>
    <div v-if="loading" class="state-panel" role="status">
      <span class="spinner"></span> Загружаем задачи…
    </div>
    <div v-else-if="error" class="state-panel state-panel--error" role="alert">
      <span>Не удалось загрузить задачи. {{ error }}</span>
      <button class="text-button" type="button" @click="load">Повторить</button>
    </div>
    <div v-else-if="items.length === 0" class="empty-panel">
      <div class="empty-art" aria-hidden="true">✳</div>
      <h2>{{ status === 'all' ? 'Пока нет задач' : 'Здесь пока пусто' }}</h2>
      <p>
        {{
          status === 'all'
            ? 'Создайте первую задачу — и начните двигаться к цели.'
            : 'Попробуйте выбрать другой фильтр или добавьте новую задачу.'
        }}
      </p>
      <RouterLink class="button button--secondary" :to="{ name: 'new-task' }"
        >Создать задачу</RouterLink
      >
    </div>
    <template v-else>
      <div class="list-caption">
        <span>Найдено: {{ pagination.total }}</span
        ><span>Страница {{ pagination.page }} из {{ pagination.pages || 1 }}</span>
      </div>
      <div class="task-grid">
        <article
          v-for="task in items"
          :key="task.id"
          class="task-card"
          :class="{ 'task-card--done': task.completed }"
        >
          <div class="task-card-top">
            <span class="task-id">ЗАДАЧА #{{ task.id }}</span
            ><span
              class="status-pill"
              :class="task.completed ? 'status-pill--done' : 'status-pill--active'"
              >{{ task.completed ? 'Завершена' : 'В работе' }}</span
            >
          </div>
          <RouterLink class="task-card-title" :to="{ name: 'task', params: { id: task.id } }">{{
            task.title
          }}</RouterLink>
          <p class="task-description">{{ task.description || 'Без описания' }}</p>
          <div class="task-card-footer">
            <span>{{ formatDate(task.createdAt) }}</span>
            <button
              class="text-button"
              type="button"
              :disabled="updatingId !== null"
              :aria-label="
                task.completed
                  ? `Вернуть задачу ${task.title} в работу`
                  : `Завершить задачу ${task.title}`
              "
              @click="complete(task)"
            >
              {{ updatingId === task.id ? 'Сохраняем…' : task.completed ? 'Вернуть' : 'Завершить' }}
              <span aria-hidden="true">↗</span>
            </button>
          </div>
        </article>
      </div>
      <div class="pagination" aria-label="Страницы задач">
        <button
          class="button button--secondary"
          type="button"
          :disabled="page <= 1"
          @click="page--"
        >
          ← Назад
        </button>
        <span>{{ page }} / {{ pagination.pages || 1 }}</span>
        <button
          class="button button--secondary"
          type="button"
          :disabled="page >= pagination.pages"
          @click="page++"
        >
          Далее →
        </button>
      </div>
    </template>
  </div>
</template>
