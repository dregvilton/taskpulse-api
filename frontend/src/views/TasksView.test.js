import { beforeEach, expect, it, vi } from 'vitest'
import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils'
import TasksView from './TasksView.vue'

vi.mock('../api.js', () => ({
  api: {
    listTasks: vi.fn(),
    updateTask: vi.fn(),
  },
}))

import { api } from '../api.js'

beforeEach(() => {
  vi.clearAllMocks()
})

it('shows the empty state and sends status and sort filters to the API', async () => {
  api.listTasks.mockResolvedValue({ items: [], pagination: { page: 1, pages: 0, total: 0 } })
  const wrapper = mount(TasksView, { global: { stubs: { RouterLink: RouterLinkStub } } })
  await flushPromises()
  expect(wrapper.text()).toContain('Пока нет задач')

  await wrapper.findAll('.segmented button')[2].trigger('click')
  await flushPromises()
  expect(api.listTasks).toHaveBeenLastCalledWith(
    expect.objectContaining({ completed: true, page: 1 }),
  )

  await wrapper.get('select').setValue('title')
  await flushPromises()
  expect(api.listTasks).toHaveBeenLastCalledWith(expect.objectContaining({ sort: 'title' }))
})

it('completes a task and refreshes the list', async () => {
  const task = {
    id: 1,
    title: 'Подготовить отчёт',
    description: null,
    completed: false,
    createdAt: '2026-10-01 12:00:00',
  }
  api.listTasks.mockResolvedValue({ items: [task], pagination: { page: 1, pages: 1, total: 1 } })
  api.updateTask.mockResolvedValue({ ...task, completed: true })
  const wrapper = mount(TasksView, { global: { stubs: { RouterLink: RouterLinkStub } } })
  await flushPromises()

  await wrapper.get('button[aria-label="Завершить задачу Подготовить отчёт"]').trigger('click')
  await flushPromises()

  expect(api.updateTask).toHaveBeenCalledWith(1, { completed: true })
  expect(api.listTasks).toHaveBeenCalledTimes(2)
})

it('requests the next page using API pagination', async () => {
  api.listTasks.mockResolvedValue({
    items: [{ id: 1, title: 'Задача', completed: false, createdAt: '2026-10-01 12:00:00' }],
    pagination: { page: 1, pages: 3, total: 21 },
  })
  const wrapper = mount(TasksView, { global: { stubs: { RouterLink: RouterLinkStub } } })
  await flushPromises()

  await wrapper.findAll('.pagination button')[1].trigger('click')
  await flushPromises()

  expect(api.listTasks).toHaveBeenLastCalledWith(expect.objectContaining({ page: 2, perPage: 9 }))
})
