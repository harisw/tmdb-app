<script setup>
import { computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import CountrySelect from './CountrySelect.vue'

const props = defineProps({
    isOpen: { type: Boolean, required: true },
})
const emit = defineEmits(['close'])

const page = usePage()
const user = computed(() => page.props.auth.user)
const avatarStyles = computed(() => page.props.avatarStyles)

const form = useForm({
    username: '',
    country: '',
    avatar_style: '',
    hide_adult: true,
})

watch(
    () => props.isOpen,
    (val) => {
        if (val) {
            form.username = user.value?.username ?? ''
            form.country = user.value?.country ?? ''
            form.avatar_style = user.value?.avatar_style ?? ''
            form.hide_adult = user.value?.hide_adult ?? true
            form.clearErrors()
        }
    }
)

const avatarPreview = computed(() => {
    const style = form.avatar_style || 'adventurer'
    const seed = form.username || user.value?.username || 'user'
    return `https://api.dicebear.com/9.x/${style}/svg?seed=${encodeURIComponent(seed)}`
})

function randomizeAvatar() {
    const styles = avatarStyles.value
    const current = form.avatar_style
    let next
    do {
        next = styles[Math.floor(Math.random() * styles.length)]
    } while (next === current && styles.length > 1)
    form.avatar_style = next
}

const close = () => emit('close')

const handleKeydown = (e) => {
    if (e.key === 'Escape') close()
}
onMounted(() => document.addEventListener('keydown', handleKeydown))
onBeforeUnmount(() => document.removeEventListener('keydown', handleKeydown))
</script>

<template>
    <Transition name="fade">
        <div
            v-if="isOpen"
            class="fixed inset-0 z-200 flex items-center justify-center p-4 bg-black/70"
            @click.self="close"
        >
            <div class="bg-zinc-900 rounded-xl shadow-2xl w-full max-w-md border border-white/10">
                <!-- Header -->
                <div class="flex items-center justify-between px-6 pt-6 pb-4">
                    <h2 class="text-white font-semibold text-base">Update Profile</h2>
                    <button class="text-gray-500 hover:text-white transition-colors cursor-pointer" @click="close">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>

                <form
                    class="px-6 pb-6 space-y-4"
                    @submit.prevent="form.patch('/profile', { preserveScroll: true, onSuccess: close })"
                >
                    <!-- Avatar preview + randomize -->
                    <div class="flex flex-col items-center gap-2 py-2">
                        <img
                            :src="avatarPreview"
                            :alt="form.username"
                            class="w-20 h-20 rounded-full ring-2 ring-madder ring-offset-2 ring-offset-zinc-900 bg-white/5"
                        />
                        <button
                            type="button"
                            class="flex items-center gap-1.5 text-xs text-gray-400 hover:text-white transition-colors cursor-pointer"
                            @click="randomizeAvatar"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                />
                            </svg>
                            Randomize avatar
                        </button>
                    </div>

                    <!-- Username -->
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Username</label>
                        <input
                            v-model="form.username"
                            type="text"
                            required
                            minlength="3"
                            maxlength="30"
                            class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm placeholder-gray-600 focus:outline-none focus:border-madder"
                            placeholder="your_username"
                        />
                        <p v-if="form.errors.username" class="text-red-400 text-xs mt-1">{{ form.errors.username }}</p>
                    </div>

                    <!-- Country -->
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Country</label>
                        <CountrySelect v-model="form.country" />
                        <p v-if="form.errors.country" class="text-red-400 text-xs mt-1">{{ form.errors.country }}</p>
                    </div>
                    <div>
                        <label class="flex gap-3 text-sm text-gray-400 mb-1 cursor-pointer">
                            <input v-model="form.hide_adult" type="checkbox" class="toggle toggle-sm" />
                            {{ form.hide_adult ? 'Hide' : 'Show' }} adult contents
                        </label>
                    </div>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-2 bg-madder text-white text-sm font-medium rounded-lg hover:bg-red-800 transition-colors disabled:opacity-50 cursor-pointer"
                    >
                        {{ form.processing ? 'Saving…' : 'Save changes' }}
                    </button>
                </form>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.15s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
