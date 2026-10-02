<script setup>
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../api.js'
import { startSession } from '../session.js'

const route = useRoute()
const router = useRouter()
const mode = ref('login')
const fullName = ref('')
const email = ref('')
const password = ref('')
const busy = ref(false)
const error = ref('')
const fields = ref({})
const isRegister = computed(() => mode.value === 'register')

function switchMode(nextMode) {
  if (busy.value) return
  mode.value = nextMode
  error.value = ''
  fields.value = {}
}

async function submit() {
  if (busy.value) return
  error.value = ''
  fields.value = {}
  busy.value = true
  try {
    const credentials = { email: email.value.trim(), password: password.value }
    if (isRegister.value) {
      await api.registerUser({ fullName: fullName.value.trim(), ...credentials })
    }
    const result = await api.login(credentials)
    startSession(result)
    const next = route.query.next
    await router.replace(
      typeof next === 'string' && next.startsWith('/') && !next.startsWith('//')
        ? next
        : { name: 'tasks' },
    )
  } catch (failure) {
    fields.value = failure.fields || {}
    error.value = failure.message
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="login-layout">
    <section class="login-intro">
      <div class="login-brand">
        <span class="brand-mark" aria-hidden="true"><span></span><span></span><span></span></span>
        task<span>pulse</span>
      </div>
      <div>
        <p class="eyebrow eyebrow--light">ЗАДАЧИ · ФОКУС · ПРОГРЕСС</p>
        <h1>Планы становятся<br /><em>результатом.</em></h1>
        <p>
          Простое пространство для задач: записывайте идеи, отмечайте сделанное и наблюдайте за
          прогрессом.
        </p>
      </div>
      <div class="login-feature">
        <span class="feature-number">01 / 03</span><span>Всё важное — перед глазами</span>
      </div>
    </section>

    <section class="login-panel">
      <div class="login-card">
        <p class="eyebrow">ДОБРО ПОЖАЛОВАТЬ</p>
        <h2>{{ isRegister ? 'Создайте аккаунт' : 'С возвращением' }}</h2>
        <p class="muted">
          {{
            isRegister
              ? 'Для начала понадобится имя, почта и пароль.'
              : 'Войдите, чтобы продолжить работу с задачами.'
          }}
        </p>

        <p v-if="route.query.reason === 'expired'" class="notice" role="status">
          Сессия истекла. Войдите снова.
        </p>
        <p v-if="route.query.reason === 'logout-unconfirmed'" class="notice" role="status">
          Вы вышли из интерфейса, но сервер не подтвердил отзыв токена. Он может действовать до
          истечения срока.
        </p>
        <p v-if="error" class="error-banner" role="alert">{{ error }}</p>

        <form class="stack-form" @submit.prevent="submit">
          <label v-if="isRegister"
            >Имя
            <input
              v-model="fullName"
              type="text"
              name="fullName"
              :aria-invalid="Boolean(fields.fullName)"
              :aria-describedby="fields.fullName ? 'full-name-error' : undefined"
              autocomplete="name"
              required
              maxlength="255"
              :disabled="busy"
              placeholder="Как к вам обращаться"
            />
            <small v-if="fields.fullName" id="full-name-error" class="field-error">{{
              fields.fullName
            }}</small>
          </label>
          <label
            >Электронная почта
            <input
              v-model="email"
              type="email"
              name="email"
              :aria-invalid="Boolean(fields.email)"
              :aria-describedby="fields.email ? 'email-error' : undefined"
              autocomplete="email"
              required
              :disabled="busy"
              placeholder="name@example.com"
            />
            <small v-if="fields.email" id="email-error" class="field-error">{{
              fields.email
            }}</small>
          </label>
          <label
            >Пароль
            <input
              v-model="password"
              type="password"
              name="password"
              :aria-invalid="Boolean(fields.password)"
              :aria-describedby="fields.password ? 'password-error' : undefined"
              :autocomplete="isRegister ? 'new-password' : 'current-password'"
              :minlength="isRegister ? 12 : undefined"
              required
              :disabled="busy"
              :placeholder="isRegister ? 'Не менее 12 символов' : 'Ваш пароль'"
            />
            <small v-if="fields.password" id="password-error" class="field-error">{{
              fields.password
            }}</small>
          </label>
          <button class="button button--primary button--wide" type="submit" :disabled="busy">
            {{ busy ? 'Подождите…' : isRegister ? 'Создать аккаунт' : 'Войти' }}
            <span aria-hidden="true">→</span>
          </button>
        </form>

        <p class="login-switch">
          {{ isRegister ? 'Уже есть аккаунт?' : 'Впервые здесь?' }}
          <button
            type="button"
            :disabled="busy"
            @click="switchMode(isRegister ? 'login' : 'register')"
          >
            {{ isRegister ? 'Войти' : 'Зарегистрироваться' }}
          </button>
        </p>
        <p class="login-footnote">
          Токен хранится только до закрытия или обновления страницы. Данные доступны только
          владельцу аккаунта.
        </p>
      </div>
    </section>
  </div>
</template>
