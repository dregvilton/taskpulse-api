import { beforeEach, expect, it, vi } from 'vitest'
import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils'
import App from './App.vue'

const { replace } = vi.hoisted(() => ({ replace: vi.fn() }))

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal()),
  useRouter: () => ({ replace }),
}))
vi.mock('./api.js', () => ({ api: { logout: vi.fn() } }))

import { api } from './api.js'
import { clearSession, session, startSession } from './session.js'

beforeEach(() => {
  clearSession()
  startSession({ accessToken: 'token', userId: 3 })
  vi.clearAllMocks()
})

it('clears the local session even when token revocation fails', async () => {
  api.logout.mockRejectedValue(new Error('Network error'))
  const wrapper = mount(App, {
    global: { stubs: { RouterLink: RouterLinkStub, RouterView: true } },
  })

  await wrapper.get('.logout-button').trigger('click')
  await flushPromises()

  expect(session.token).toBeNull()
  expect(wrapper.find('.sidebar').exists()).toBe(false)
  expect(replace).toHaveBeenCalledWith({
    name: 'login',
    query: { reason: 'logout-unconfirmed' },
  })
})

it('treats an already revoked token as a completed logout', async () => {
  api.logout.mockRejectedValue({ status: 401 })
  const wrapper = mount(App, {
    global: { stubs: { RouterLink: RouterLinkStub, RouterView: true } },
  })

  await wrapper.get('.logout-button').trigger('click')
  await flushPromises()

  expect(session.token).toBeNull()
  expect(replace).toHaveBeenCalledWith({ name: 'login' })
})
