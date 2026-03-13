import 'vite/modulepreload-polyfill'

import { createApp } from 'vue'
import AdminSettings from './views/AdminSettings.vue'

const app = createApp(AdminSettings)
app.config.idPrefix = 'profile-fields-admin'
app.mount('#profile-fields-admin-settings')
