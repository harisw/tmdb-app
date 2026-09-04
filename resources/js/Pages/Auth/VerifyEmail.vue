<script setup>
import { useForm } from '@inertiajs/vue3'
import { button } from '../../variants/button.js'

const form = useForm({})
const resend = () => form.post('/email/verification-notification')
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-licorice text-white px-4 py-12">
        <div class="w-full max-w-sm space-y-6 text-center">
            <h1 class="text-2xl font-bold">Verify your email</h1>
            <p class="text-sm text-white/60 leading-relaxed">
                We've sent a verification link to your email address. Click it to activate your account.
            </p>
            <button
                :disabled="form.processing"
                :class="button({ intent: 'primary', size: 'md', class: 'w-full' })"
                @click="resend"
            >
                Resend verification email
            </button>
            <a href="/logout" class="block text-sm text-white/40 hover:text-white">Back to login</a>
        </div>
    </div>
</template>

<style scoped></style>
