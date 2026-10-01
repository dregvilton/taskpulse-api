import { describe, expect, it, vi } from 'vitest'
import { ApiError, createApiClient } from './api.js'

function reply(status, data, headers = {}) {
  return {
    ok: status >= 200 && status < 300,
    status,
    headers: { get: (name) => headers[name] || null },
    json: async () => data,
  }
}

describe('API client', () => {
  it('adds the bearer token and reads Yii pagination headers', async () => {
    const transport = vi.fn().mockResolvedValue(
      reply(
        200,
        { items: [{ id: 7 }] },
        {
          'X-Pagination-Total-Count': '21',
          'X-Pagination-Page-Count': '3',
          'X-Pagination-Current-Page': '2',
        },
      ),
    )
    const client = createApiClient({ baseUrl: '/api/', getToken: () => 'secret-token', transport })

    const result = await client.listTasks({ page: 2, completed: false, sort: '-createdAt' })

    expect(transport).toHaveBeenCalledWith(
      '/api/tasks?page=2&completed=false&sort=-createdAt',
      expect.objectContaining({
        headers: expect.objectContaining({ Authorization: 'Bearer secret-token' }),
      }),
    )
    expect(result).toEqual({ items: [{ id: 7 }], pagination: { page: 2, pages: 3, total: 21 } })
  })

  it('ends the session on 401, but not on 403', async () => {
    const onUnauthorized = vi.fn()
    const transport = vi
      .fn()
      .mockResolvedValueOnce(reply(401, { message: 'Необходима авторизация.' }))
      .mockResolvedValueOnce(reply(403, { message: 'Запись в публичном API отключена.' }))
    const client = createApiClient({ transport, onUnauthorized })

    await expect(client.getTask(1)).rejects.toMatchObject({ status: 401 })
    await expect(client.createTask({ title: 'Задача' })).rejects.toMatchObject({ status: 403 })
    expect(onUnauthorized).toHaveBeenCalledTimes(1)
  })

  it('keeps validation errors by field and does not end the session for invalid login', async () => {
    const onUnauthorized = vi.fn()
    const transport = vi
      .fn()
      .mockResolvedValueOnce(reply(422, [{ field: 'title', message: 'Укажите название.' }]))
      .mockResolvedValueOnce(reply(401, { message: 'Неверные учётные данные.' }))
    const client = createApiClient({ transport, onUnauthorized })

    await expect(client.createTask({ title: '' })).rejects.toMatchObject({
      status: 422,
      fields: { title: 'Укажите название.' },
    })
    await expect(
      client.login({ email: 'a@example.test', password: 'wrong' }),
    ).rejects.toBeInstanceOf(ApiError)
    expect(onUnauthorized).not.toHaveBeenCalled()
  })
})
