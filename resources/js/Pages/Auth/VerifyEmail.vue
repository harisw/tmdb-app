<script setup>
import { useForm } from '@inertiajs/vue3'

const form = useForm({})
const resend = () => form.post('/email/verification-notification')
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4">
        <div class="max-w-md w-full text-center space-y-6">
            <h2 class="text-2xl font-bold text-gray-900">Verify your email</h2>
            <p class="text-gray-600">
                We've sent a verification link to your email address. Click it to activate your account.
            </p>
            <button
                @click="resend"
                :disabled="form.processing"
                class="w-full py-2 px-4 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
            >
                Resend verification email
            </button>
            <a href="/logout" class="block text-sm text-gray-500 hover:underline">Back to login</a>
        </div>
    </div>
</template>

<style scoped></style>
