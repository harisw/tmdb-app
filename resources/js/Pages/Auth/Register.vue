<script setup>
import { useForm } from '@inertiajs/vue3'
import { field } from '../../variants/field.js'
import { button } from '../../variants/button.js'

const form = useForm({
    email: '',
    password: '',
    password_confirmation: '',
})

const submit = () => {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-licorice text-white px-4 py-12">
        <div class="w-full max-w-sm space-y-8">
            <h1 class="text-center text-2xl font-bold">Create your account</h1>

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
                    <p v-if="form.errors.email" class="mt-1 text-xs text-red-400">{{ form.errors.email }}</p>
                </div>

                <div>
                    <label for="password" class="mb-1 block text-sm text-white/60">Password</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        required
                        autocomplete="new-password"
                        :class="field({ state: form.errors.password ? 'error' : 'default', class: 'px-3 py-2' })"
                    />
                    <p v-if="form.errors.password" class="mt-1 text-xs text-red-400">{{ form.errors.password }}</p>
                </div>

                <div>
                    <label for="password_confirmation" class="mb-1 block text-sm text-white/60">Confirm password</label>
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
                    Register
                </button>

                <p class="text-center text-sm text-white/50">
                    Already have an account?
                    <a href="/login" class="text-madder hover:underline">Login</a>
                </p>
            </form>
        </div>
    </div>
</template>
