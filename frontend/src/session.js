import { reactive } from 'vue'

export const session = reactive({
  token: null,
  userId: null,
  expiresAt: null,
})

export function startSession({ accessToken, userId, expiresAt }) {
  session.token = accessToken
  session.userId = userId
  session.expiresAt = expiresAt
}

export function clearSession() {
  session.token = null
  session.userId = null
  session.expiresAt = null
}
