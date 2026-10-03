import { beforeEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

const { replace } = vi.hoisted(() => ({ replace: vi.fn() }))
vi.mock('vue-router', () => ({
  useRoute: () => ({ query: {} }),
  useRouter: () => ({ replace }),
}))
vi.mock('../api.js', () => ({ api: { login: vi.fn(), registerUser: vi.fn() } }))
vi.mock('../demoConfig.js', () => ({
  demoMode: true,
  demoEmail: 'demo@example.test',
  demoPassword: 'public-demo-password',
}))

import LoginView from './LoginView.vue'
import { api } from '../api.js'
import { clearSession } from '../session.js'

beforeEach(() => {
  clearSession()
  vi.clearAllMocks()
})

it('opens the public demo with one click and hides registration', async () => {
  api.login.mockResolvedValue({ accessToken: 'demo-token', userId: 1 })
  const wrapper = mount(LoginView)

  expect(wrapper.find('.login-switch').exists()).toBe(false)
  expect(wrapper.get('.demo-entry').text()).toContain('30 минут')
  await wrapper.get('.demo-entry button').trigger('click')
  await flushPromises()

  expect(api.login).toHaveBeenCalledWith({
    email: 'demo@example.test',
    password: 'public-demo-password',
  })
  expect(api.registerUser).not.toHaveBeenCalled()
  expect(replace).toHaveBeenCalledWith({ name: 'tasks' })
})
