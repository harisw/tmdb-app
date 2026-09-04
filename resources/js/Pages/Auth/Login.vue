<script setup>
import { useForm } from '@inertiajs/vue3'
import { field } from '../../variants/field.js'
import { button } from '../../variants/button.js'

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

const submit = () => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-licorice text-white px-4 py-12">
        <div class="w-full max-w-sm space-y-8">
            <h1 class="text-center text-2xl font-bold">Sign in to your account</h1>

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
                        autocomplete="current-password"
                        :class="field({ state: form.errors.password ? 'error' : 'default', class: 'px-3 py-2' })"
                    />
                    <p v-if="form.errors.password" class="mt-1 text-xs text-red-400">{{ form.errors.password }}</p>
                </div>

                <label class="flex items-center gap-2 text-sm text-white/60">
                    <input
                        v-model="form.remember"
                        type="checkbox"
                        class="h-4 w-4 rounded border-white/20 bg-white/5 accent-madder"
                    />
                    Remember me
                </label>

                <button
                    type="submit"
                    :disabled="form.processing"
                    :class="button({ intent: 'primary', size: 'md', class: 'w-full' })"
                >
                    Sign in
                </button>

                <div class="flex items-center gap-3">
                    <div class="h-px flex-1 bg-white/10" />
                    <span class="text-xs text-white/40">or</span>
                    <div class="h-px flex-1 bg-white/10" />
                </div>

                <a
                    href="/auth/google"
                    class="flex w-full items-center justify-center gap-2 rounded-field border border-white/10 bg-white/5 px-4 py-2 text-sm text-white/70 transition-colors hover:bg-white/10"
                >
                    <img src="https://www.svgrepo.com/show/475656/google-color.svg" class="h-5 w-5" alt="" />
                    Continue with Google
                </a>

                <p class="text-center text-sm text-white/50">
                    Don't have an account?
                    <a href="/register" class="text-madder hover:underline">Register</a>
                </p>
            </form>
        </div>
    </div>
</template>
