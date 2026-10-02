<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api } from '../api.js'
import { formatDate } from '../format.js'

const route = useRoute()
const router = useRouter()
const isNew = computed(() => route.name === 'new-task')
const task = ref(null)
const title = ref('')
const description = ref('')
const completed = ref(false)
const loading = ref(false)
const busy = ref(false)
const error = ref('')
const fields = ref({})
const saved = ref(false)
let loadNumber = 0

function fillForm(value) {
  task.value = value
  title.value = value?.title || ''
  description.value = value?.description || ''
  completed.value = value?.completed || false
}

async function load() {
  const currentLoad = ++loadNumber
  error.value = ''
  fields.value = {}
  saved.value = false
  if (isNew.value) {
    loading.value = false
    fillForm(null)
    return
  }
  loading.value = true
  try {
    const result = await api.getTask(route.params.id)
    if (currentLoad === loadNumber) fillForm(result)
  } catch (failure) {
    if (currentLoad === loadNumber) {
      error.value = failure.message
      task.value = null
    }
  } finally {
    if (currentLoad === loadNumber) loading.value = false
  }
}

watch(() => route.fullPath, load, { immediate: true })

async function save() {
  if (busy.value) return
  busy.value = true
  error.value = ''
  fields.value = {}
  saved.value = false
  try {
    const body = {
      title: title.value.trim(),
      description: description.value.trim() || null,
      completed: completed.value,
    }
    if (isNew.value) {
      const created = await api.createTask(body)
      await router.replace({ name: 'task', params: { id: created.id } })
    } else {
      fillForm(await api.updateTask(route.params.id, body))
      saved.value = true
    }
  } catch (failure) {
    fields.value = failure.fields || {}
    error.value = failure.message
  } finally {
    busy.value = false
  }
}

async function toggleComplete() {
  if (busy.value) return
  busy.value = true
  error.value = ''
  saved.value = false
  try {
    const updated = await api.updateTask(route.params.id, { completed: !task.value.completed })
    task.value = updated
    completed.value = updated.completed
  } catch (failure) {
    error.value = failure.message
  } finally {
    busy.value = false
  }
}

async function remove() {
  if (busy.value) return
  if (!window.confirm('Удалить эту задачу? Действие нельзя отменить в интерфейсе.')) return
  busy.value = true
  error.value = ''
  try {
    await api.deleteTask(route.params.id)
    await router.replace({ name: 'tasks' })
  } catch (failure) {
    error.value = failure.message
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="page-content page-content--narrow">
    <RouterLink class="back-link" :to="{ name: 'tasks' }">← Все задачи</RouterLink>
    <div class="page-heading">
      <div>
        <p class="eyebrow">{{ isNew ? 'НОВЫЙ ШАГ' : `ЗАДАЧА #${route.params.id}` }}</p>
        <h1>{{ isNew ? 'Новая задача' : 'Карточка задачи' }}<span class="heading-dot">.</span></h1>
        <p class="page-subtitle">
          {{
            isNew
              ? 'Сформулируйте цель — остальное приложится.'
              : 'Детали, прогресс и изменения в одном месте.'
          }}
        </p>
      </div>
    </div>

    <div v-if="loading" class="state-panel" role="status">
      <span class="spinner"></span> Загружаем задачу…
    </div>
    <div v-else-if="!isNew && !task" class="state-panel state-panel--error" role="alert">
      {{ error || 'Задача не найдена.' }}
    </div>
    <template v-else>
      <p v-if="error" class="error-banner" role="alert">{{ error }}</p>
      <p v-if="saved" class="success-banner" role="status">Изменения сохранены.</p>
      <div v-if="task" class="task-meta">
        <span
          class="status-pill"
          :class="task.completed ? 'status-pill--done' : 'status-pill--active'"
          >{{ task.completed ? 'Завершена' : 'В работе' }}</span
        >
        <span>Создана {{ formatDate(task.createdAt) }}</span>
        <span v-if="task.completedAt">Завершена {{ formatDate(task.completedAt) }}</span>
      </div>

      <form class="editor-card stack-form" @submit.prevent="save">
        <label
          >Название
          <input
            v-model="title"
            type="text"
            name="title"
            :aria-invalid="Boolean(fields.title)"
            :aria-describedby="fields.title ? 'title-error' : undefined"
            minlength="3"
            maxlength="255"
            :disabled="busy"
            required
            placeholder="Например, подготовить презентацию"
            @input="saved = false"
          />
          <small v-if="fields.title" id="title-error" class="field-error">{{ fields.title }}</small>
        </label>
        <label
          >Описание <span class="optional">необязательно</span>
          <textarea
            v-model="description"
            name="description"
            :aria-invalid="Boolean(fields.description)"
            :aria-describedby="fields.description ? 'description-error' : undefined"
            rows="7"
            maxlength="5000"
            :disabled="busy"
            placeholder="Что именно нужно сделать?"
            @input="saved = false"
          ></textarea>
          <small v-if="fields.description" id="description-error" class="field-error">{{
            fields.description
          }}</small>
        </label>
        <label class="checkbox-field"
          ><input
            v-model="completed"
            type="checkbox"
            name="completed"
            :disabled="busy"
            @change="saved = false"
          /><span>Задача завершена</span></label
        >
        <div class="editor-actions">
          <button class="button button--primary" type="submit" :disabled="busy">
            {{ busy ? 'Сохраняем…' : isNew ? 'Создать задачу' : 'Сохранить изменения' }}
            <span aria-hidden="true">→</span>
          </button>
          <button
            v-if="task"
            class="button button--secondary"
            type="button"
            :disabled="busy"
            @click="toggleComplete"
          >
            {{ task.completed ? 'Вернуть в работу' : 'Быстро завершить' }}
          </button>
        </div>
      </form>
      <button v-if="task" class="delete-link" type="button" :disabled="busy" @click="remove">
        Удалить задачу
      </button>
    </template>
  </div>
</template>
