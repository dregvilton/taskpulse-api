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
