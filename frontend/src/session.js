import { reactive } from 'vue'

export const session = reactive({
  token: null,
  userId: null,
})

export function startSession({ accessToken, userId }) {
  session.token = accessToken
  session.userId = userId
}

export function clearSession() {
  session.token = null
  session.userId = null
}
