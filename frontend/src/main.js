import { createApp } from 'vue'
import App from './App.vue'
import { setUnauthorizedHandler } from './api.js'
import { router } from './router.js'
import { clearSession } from './session.js'
import './style.css'

setUnauthorizedHandler(() => {
  clearSession()
  if (router.currentRoute.value.name !== 'login') {
    router.replace({ name: 'login', query: { reason: 'expired' } })
  }
})

createApp(App).use(router).mount('#app')
