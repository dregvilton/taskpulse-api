import { beforeEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import LoginView from './LoginView.vue'

const replace = vi.fn()
vi.mock('vue-router', () => ({
  useRoute: () => ({ query: {} }),
  useRouter: () => ({ replace }),
}))
vi.mock('../api.js', () => ({
  api: { login: vi.fn(), registerUser: vi.fn() },
}))

import { api } from '../api.js'
import { clearSession, session } from '../session.js'

beforeEach(() => {
  clearSession()
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
