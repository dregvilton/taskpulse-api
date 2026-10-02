import { beforeEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import AnalyticsView from './AnalyticsView.vue'

vi.mock('../api.js', () => ({ api: { getAnalytics: vi.fn() } }))
import { api } from '../api.js'

beforeEach(() => vi.clearAllMocks())

it('shows empty analytics and applies a creation period', async () => {
  api.getAnalytics.mockResolvedValue({
    totalCreated: 0,
    totalCompleted: 0,
    completionPercent: 0,
    avgCompletionTimeSeconds: null,
  })
  const wrapper = mount(AnalyticsView)
  await flushPromises()
  expect(wrapper.text()).toContain('Создайте задачу')
  expect(wrapper.text()).toContain('Нет данных')

  await wrapper.get('input[name="from"]').setValue('2026-10-01')
  await wrapper.get('form').trigger('submit')
  await flushPromises()

  expect(api.getAnalytics).toHaveBeenLastCalledWith({
    createdFrom: expect.stringMatching(/T\d\d:\d\d:\d\d\+00:00$/),
    createdTo: null,
  })
})

it('ignores an older response after a newer period has loaded', async () => {
  let resolveOld
  let resolveNew
  api.getAnalytics
    .mockImplementationOnce(() => new Promise((resolve) => (resolveOld = resolve)))
    .mockImplementationOnce(() => new Promise((resolve) => (resolveNew = resolve)))

  const wrapper = mount(AnalyticsView)
  await wrapper.get('input[name="from"]').setValue('2026-10-01')
  await wrapper.get('form').trigger('submit')

  resolveNew({
    totalCreated: 2,
    totalCompleted: 1,
    completionPercent: 50,
    avgCompletionTimeSeconds: 3600,
  })
  await flushPromises()
  expect(wrapper.text()).toContain('1 из 2 задач завершено')

  resolveOld({
    totalCreated: 9,
    totalCompleted: 9,
    completionPercent: 100,
    avgCompletionTimeSeconds: 3600,
  })
  await flushPromises()
  expect(wrapper.text()).toContain('1 из 2 задач завершено')
  expect(wrapper.text()).not.toContain('9 из 9 задач')
})

it('invalidates a pending response when the new period is invalid', async () => {
  let resolveOld
  api.getAnalytics.mockImplementationOnce(() => new Promise((resolve) => (resolveOld = resolve)))

  const wrapper = mount(AnalyticsView)
  await wrapper.get('input[name="from"]').setValue('2026-10-02')
  await wrapper.get('input[name="to"]').setValue('2026-10-01')
  await wrapper.get('form').trigger('submit')

  resolveOld({
    totalCreated: 9,
    totalCompleted: 9,
    completionPercent: 100,
    avgCompletionTimeSeconds: 3600,
  })
  await flushPromises()
  expect(wrapper.get('[role="alert"]').text()).toContain(
    'Начало периода не может быть позже конца.',
  )
  expect(wrapper.text()).not.toContain('9 из 9 задач')
  expect(api.getAnalytics).toHaveBeenCalledTimes(1)
})
