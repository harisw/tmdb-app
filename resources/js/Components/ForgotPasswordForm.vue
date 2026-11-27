<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { field } from '../variants/field.js'
import { button } from '../variants/button.js'

const props = defineProps({
    initialEmail: { type: String, default: '' },
    backLabel: { type: String, default: 'Back to sign in' },
})
const emit = defineEmits(['back'])

const sent = ref(false)
const form = useForm({ email: props.initialEmail })

const submit = () => {
    form.post('/forgot-password', {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => (sent.value = true),
    })
}
</script>

<template>
    <div>
        <div v-if="sent" role="status" class="space-y-4">
            <h2 class="text-lg font-semibold text-white">Check your inbox</h2>
            <p class="text-sm leading-relaxed text-white/70">
                We sent a link to choose a new password to
                <span class="font-medium text-white">{{ form.email }}</span
                >.
            </p>
            <button
                type="button"
                :class="button({ intent: 'soft', size: 'md', class: 'w-full' })"
                @click="emit('back')"
            >
                {{ backLabel }}
            </button>
        </div>

        <form v-else class="space-y-4" @submit.prevent="submit">
            <div class="space-y-1">
                <h2 class="text-lg font-semibold text-white">Reset your password</h2>
                <p class="text-sm leading-relaxed text-white/70">
                    Enter the email you signed up with and we'll send you a link to choose a new one.
                </p>
            </div>

            <div>
                <label for="forgot-email" class="mb-1 block text-sm text-white/60">Email</label>
                <input
                    id="forgot-email"
                    v-model="form.email"
                    type="email"
                    required
                    autocomplete="email"
                    :class="field({ state: form.errors.email ? 'error' : 'default', class: 'px-3 py-2 text-sm' })"
                />
                <p v-if="form.errors.email" class="mt-1 text-xs text-red-400">{{ form.errors.email }}</p>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                :class="button({ intent: 'primary', size: 'md', class: 'w-full' })"
            >
                {{ form.processing ? 'Sending…' : 'Send reset link' }}
            </button>

            <button
                type="button"
                class="block w-full text-center text-sm text-white/60 transition-colors hover:text-white"
                @click="emit('back')"
            >
                {{ backLabel }}
            </button>
        </form>
    </div>
</template>
