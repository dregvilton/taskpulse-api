import { beforeEach, expect, it, vi } from 'vitest'
import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils'
import TaskView from './TaskView.vue'

const { route, replace } = vi.hoisted(() => ({
  route: { name: 'new-task', params: {}, fullPath: '/tasks/new' },
  replace: vi.fn(),
}))

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal()),
  useRoute: () => route,
  useRouter: () => ({ replace }),
}))
vi.mock('../api.js', () => ({
  api: { getTask: vi.fn(), createTask: vi.fn(), updateTask: vi.fn(), deleteTask: vi.fn() },
}))

import { api } from '../api.js'

beforeEach(() => {
  route.name = 'new-task'
  route.params = {}
  route.fullPath = '/tasks/new'
  vi.clearAllMocks()
})

it('creates a task with camelCase API fields and opens its card', async () => {
  api.createTask.mockResolvedValue({ id: 7 })
  const wrapper = mount(TaskView, { global: { stubs: { RouterLink: RouterLinkStub } } })
  await wrapper.get('input[name="title"]').setValue('Новая задача')
  await wrapper.get('textarea[name="description"]').setValue('  Подробности  ')
  await wrapper.get('form').trigger('submit')
  await flushPromises()

  expect(api.createTask).toHaveBeenCalledWith({
    title: 'Новая задача',
    description: 'Подробности',
    completed: false,
  })
  expect(replace).toHaveBeenCalledWith({ name: 'task', params: { id: 7 } })
})

it('edits an existing task and shows the saved state', async () => {
  route.name = 'task'
  route.params = { id: '7' }
  route.fullPath = '/tasks/7'
  api.getTask.mockResolvedValue({
    id: 7,
    title: 'Исходное название',
    description: null,
    completed: false,
  })
  api.updateTask.mockResolvedValue({
    id: 7,
    title: 'Новое название',
    description: null,
    completed: false,
  })
  const wrapper = mount(TaskView, { global: { stubs: { RouterLink: RouterLinkStub } } })
  await flushPromises()
  await wrapper.get('input[name="title"]').setValue('Новое название')
  await wrapper.get('form').trigger('submit')
  await flushPromises()

  expect(api.updateTask).toHaveBeenCalledWith('7', {
    title: 'Новое название',
    description: null,
    completed: false,
  })
  expect(wrapper.get('[role="status"]').text()).toContain('сохранены')
})
