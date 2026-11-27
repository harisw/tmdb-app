<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { field } from '../../variants/field.js'
import { button } from '../../variants/button.js'
import ForgotPasswordForm from '../../Components/ForgotPasswordForm.vue'

const props = defineProps({
    token: { type: String, required: true },
    email: { type: String, default: '' },
})

const requestingNewLink = ref(false)

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
})

const submit = () => {
    form.post('/reset-password', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-licorice text-white px-4 py-12">
        <div class="w-full max-w-sm space-y-8">
            <ForgotPasswordForm
                v-if="requestingNewLink"
                :initial-email="form.email"
                back-label="Back to new password"
                @back="requestingNewLink = false"
            />

            <template v-else>
                <h1 class="text-center text-2xl font-bold">Choose a new password</h1>

                <form class="space-y-5" @submit.prevent="submit">
                    <div>
                        <label for="email" class="mb-1 block text-sm text-white/60">Email</label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            required
                            autocomplete="email"
                            :class="field({ state: form.errors.email ? 'error' : 'default', class: 'px-3 py-2' })"
                        />
                        <p v-if="form.errors.email" class="mt-1 text-xs text-red-400">
                            {{ form.errors.email }}
                            <button
                                type="button"
                                class="text-white underline underline-offset-2"
                                @click="requestingNewLink = true"
                            >
                                Send a new link
                            </button>
                        </p>
                    </div>

                    <div>
                        <label for="password" class="mb-1 block text-sm text-white/60">New password</label>
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            required
                            autofocus
                            autocomplete="new-password"
                            :class="field({ state: form.errors.password ? 'error' : 'default', class: 'px-3 py-2' })"
                        />
                        <p v-if="form.errors.password" class="mt-1 text-xs text-red-400">{{ form.errors.password }}</p>
                    </div>

                    <div>
                        <label for="password_confirmation" class="mb-1 block text-sm text-white/60"
                            >Confirm new password</label
                        >
                        <input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            required
                            autocomplete="new-password"
                            :class="field({ class: 'px-3 py-2' })"
                        />
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        :class="button({ intent: 'primary', size: 'md', class: 'w-full' })"
                    >
                        Save new password
                    </button>
                </form>
            </template>
        </div>
    </div>
</template>
