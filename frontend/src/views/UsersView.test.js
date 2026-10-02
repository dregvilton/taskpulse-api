import { beforeEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import UsersView from './UsersView.vue'

vi.mock('../api.js', () => ({ api: { listUsers: vi.fn() } }))
import { api } from '../api.js'

beforeEach(() => vi.clearAllMocks())

it('shows the current profile returned by the API', async () => {
  api.listUsers.mockResolvedValue({
    items: [
      {
        id: 3,
        fullName: 'Иван Петров',
        email: 'ivan@example.test',
        phone: null,
        createdAt: '2026-10-01T12:00:00+00:00',
      },
    ],
  })

  const wrapper = mount(UsersView)
  await flushPromises()

  expect(wrapper.get('.profile-card').text()).toContain('Иван Петров')
  expect(wrapper.get('.profile-card').text()).toContain('ivan@example.test')
  expect(wrapper.text()).toContain('Не указан')
  expect(wrapper.text()).not.toContain('2026-10-01T12:00:00+00:00')
})

it('offers a retry after a failed profile request', async () => {
  api.listUsers
    .mockRejectedValueOnce(new Error('Сервис недоступен.'))
    .mockResolvedValueOnce({ items: [] })

  const wrapper = mount(UsersView)
  await flushPromises()
  expect(wrapper.get('[role="alert"]').text()).toContain('Сервис недоступен.')

  await wrapper.get('button').trigger('click')
  await flushPromises()
  expect(wrapper.text()).toContain('Профиль не найден')
  expect(api.listUsers).toHaveBeenCalledTimes(2)
})
