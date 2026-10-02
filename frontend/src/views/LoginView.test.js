import { beforeEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import LoginView from './LoginView.vue'

const { route, replace } = vi.hoisted(() => ({ route: { query: {} }, replace: vi.fn() }))
vi.mock('vue-router', () => ({
  useRoute: () => route,
  useRouter: () => ({ replace }),
}))
vi.mock('../api.js', () => ({
  api: { login: vi.fn(), registerUser: vi.fn() },
}))

import { api } from '../api.js'
import { clearSession, session } from '../session.js'

beforeEach(() => {
  clearSession()
  route.query = {}
  vi.clearAllMocks()
})

it('logs in and keeps the token only in memory', async () => {
  api.login.mockResolvedValue({
    accessToken: 'token',
    userId: 3,
    expiresAt: '2026-10-01T12:00:00Z',
  })
  const wrapper = mount(LoginView)
  await wrapper.get('input[name="email"]').setValue('user@example.test')
  await wrapper.get('input[name="password"]').setValue('a-long-password')
  await wrapper.get('form').trigger('submit')
  await flushPromises()

  expect(session.token).toBe('token')
  expect('expiresAt' in session).toBe(false)
  expect(replace).toHaveBeenCalledWith({ name: 'tasks' })
  expect(window.localStorage.length).toBe(0)
})

it('shows the API error when public registration is forbidden', async () => {
  api.registerUser.mockRejectedValue({
    status: 403,
    message: 'Запись в публичном API отключена.',
    fields: {},
  })
  const wrapper = mount(LoginView)
  await wrapper.get('.login-switch button').trigger('click')
  await wrapper.get('input[name="fullName"]').setValue('Иван Петров')
  await wrapper.get('input[name="email"]').setValue('user@example.test')
  await wrapper.get('input[name="password"]').setValue('a-long-password')
  await wrapper.get('form').trigger('submit')
  await flushPromises()

  expect(wrapper.get('[role="alert"]').text()).toContain('отключена')
  expect(api.login).not.toHaveBeenCalled()
})

it('connects field validation messages to registration inputs', async () => {
  api.registerUser.mockRejectedValue({
    message: 'Проверьте заполненные поля.',
    fields: {
      fullName: 'Укажите имя.',
      email: 'Некорректная почта.',
      password: 'Пароль слишком короткий.',
    },
  })
  const wrapper = mount(LoginView)
  await wrapper.get('.login-switch button').trigger('click')
  await wrapper.get('input[name="fullName"]').setValue('Иван Петров')
  await wrapper.get('input[name="email"]').setValue('user@example.test')
  await wrapper.get('input[name="password"]').setValue('a-long-password')
  await wrapper.get('form').trigger('submit')
  await flushPromises()

  for (const [name, errorId] of [
    ['fullName', 'full-name-error'],
    ['email', 'email-error'],
    ['password', 'password-error'],
  ]) {
    const input = wrapper.get(`input[name="${name}"]`)
    expect(input.attributes('aria-invalid')).toBe('true')
    expect(input.attributes('aria-describedby')).toBe(errorId)
    expect(wrapper.get(`#${errorId}`).exists()).toBe(true)
  }
})

it('warns when the server did not confirm token revocation', () => {
  route.query = { reason: 'logout-unconfirmed' }
  const wrapper = mount(LoginView)

  expect(wrapper.get('.notice').text()).toContain('сервер не подтвердил отзыв токена')
})

it('keeps the login form unchanged while the request is pending', async () => {
  let resolveLogin
  api.login.mockImplementation(() => new Promise((resolve) => (resolveLogin = resolve)))
  const wrapper = mount(LoginView)
  await wrapper.get('input[name="email"]').setValue('user@example.test')
  await wrapper.get('input[name="password"]').setValue('a-long-password')
  await wrapper.get('form').trigger('submit')

  expect(wrapper.get('input[name="email"]').attributes('disabled')).toBeDefined()
  expect(wrapper.get('.login-switch button').attributes('disabled')).toBeDefined()
  wrapper.get('.login-switch button').element.click()
  expect(wrapper.find('input[name="fullName"]').exists()).toBe(false)

  resolveLogin({ accessToken: 'token', userId: 3 })
  await flushPromises()
  expect(replace).toHaveBeenCalledWith({ name: 'tasks' })
})
