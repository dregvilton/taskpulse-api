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

  await wrapper.get('input[name="title"]').setValue('Ещё одно изменение')
  expect(wrapper.find('.success-banner').exists()).toBe(false)
})

it('keeps unsaved text when completing a task with the quick action', async () => {
  route.name = 'task'
  route.params = { id: '7' }
  route.fullPath = '/tasks/7'
  const original = {
    id: 7,
    title: 'Исходное название',
    description: 'Исходное описание',
    completed: false,
  }
  api.getTask.mockResolvedValue(original)
  api.updateTask.mockResolvedValue({ ...original, completed: true })

  const wrapper = mount(TaskView, { global: { stubs: { RouterLink: RouterLinkStub } } })
  await flushPromises()
  await wrapper.get('input[name="title"]').setValue('Несохранённое название')
  await wrapper.get('textarea[name="description"]').setValue('Несохранённое описание')
  await wrapper.get('button[type="button"].button--secondary').trigger('click')
  await flushPromises()

  expect(api.updateTask).toHaveBeenCalledWith('7', { completed: true })
  expect(wrapper.get('input[name="title"]').element.value).toBe('Несохранённое название')
  expect(wrapper.get('textarea[name="description"]').element.value).toBe('Несохранённое описание')
  expect(wrapper.get('input[name="completed"]').element.checked).toBe(true)
})

it('connects task validation messages to their fields', async () => {
  api.createTask.mockRejectedValue({
    message: 'Проверьте заполненные поля.',
    fields: { title: 'Укажите название.', description: 'Описание слишком длинное.' },
  })
  const wrapper = mount(TaskView, { global: { stubs: { RouterLink: RouterLinkStub } } })
  await wrapper.get('input[name="title"]').setValue('Новая задача')
  await wrapper.get('form').trigger('submit')
  await flushPromises()

  expect(wrapper.get('input[name="title"]').attributes('aria-invalid')).toBe('true')
  expect(wrapper.get('input[name="title"]').attributes('aria-describedby')).toBe('title-error')
  expect(wrapper.get('#title-error').text()).toBe('Укажите название.')
  expect(wrapper.get('textarea[name="description"]').attributes('aria-invalid')).toBe('true')
  expect(wrapper.get('textarea[name="description"]').attributes('aria-describedby')).toBe(
    'description-error',
  )
})
