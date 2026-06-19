<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { useGeoLocation } from '../Composables/useGeoLocation.js'
import CountrySelect from './CountrySelect.vue'

const props = defineProps({
    isOpen: { type: Boolean, required: true },
    initialTab: { type: String, default: 'login' },
})
const emit = defineEmits(['close'])

const activeTab = ref(props.initialTab)
const registrationPending = ref(false)
const registeredEmail = ref('')
const resent = ref(false)
const page = usePage()

const { country } = useGeoLocation()

watch(
    () => props.isOpen,
    (val) => {
        if (val) {
            activeTab.value = props.initialTab
        } else {
            registrationPending.value = false
            registeredEmail.value = ''
            resent.value = false
        }
    }
)

watch(
    () => props.initialTab,
    (val) => {
        activeTab.value = val
    }
)

const loginForm = useForm({
    email: '',
    password: '',
    remember: false,
})

const registerForm = useForm({
    email: '',
    password: '',
    password_confirmation: '',
    country: '',
})

watch(country, (val) => {
    if (val && !registerForm.country) {
        registerForm.country = val
    }
})

const resendForm = useForm({})

const submitLogin = () => {
    loginForm.post('/login', {
        onFinish: () => loginForm.reset('password'),
    })
}

const submitRegister = () => {
    registeredEmail.value = registerForm.email
    registerForm.post('/register', {
        onSuccess: () => {
            registrationPending.value = true
            registerForm.reset()
        },
        onError: () => {
            registerForm.reset('password', 'password_confirmation')
        },
    })
}

const resendVerification = () => {
    resent.value = false
    resendForm.post('/email/verification-notification', {
        onSuccess: () => {
            resent.value = true
        },
    })
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
                <!-- Verification pending screen -->
                <Transition name="slide-up" mode="out-in">
                    <div
                        v-if="registrationPending"
                        key="pending"
                        class="px-6 py-10 flex flex-col items-center text-center gap-5"
                    >
                        <!-- Sonar rings + envelope -->
                        <div class="relative flex items-center justify-center w-28 h-28">
                            <span
                                class="absolute inline-flex h-full w-full rounded-full bg-madder/20 animate-ping"
                            ></span>
                            <span
                                class="absolute inline-flex h-3/4 w-3/4 rounded-full bg-madder/15 animate-ping [animation-delay:0.4s]"
                            ></span>
                            <div
                                class="relative z-10 w-16 h-16 rounded-full bg-madder/20 border border-madder/40 flex items-center justify-center"
                            >
                                <svg
                                    class="w-8 h-8 text-madder animate-bounce"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.5"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"
                                    />
                                </svg>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <h3 class="text-white font-semibold text-lg">Check your inbox</h3>
                            <p class="text-gray-400 text-sm">We sent a verification link to</p>
                            <p
                                class="text-white text-sm font-medium bg-white/5 border border-white/10 rounded-lg px-3 py-1.5 mt-1 inline-block"
                            >
                                {{ registeredEmail }}
                            </p>
                        </div>

                        <!-- Waiting dots -->
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs text-gray-500">Waiting for verification</span>
                            <span class="flex gap-1">
                                <span
                                    class="w-1 h-1 rounded-full bg-gray-500 animate-bounce [animation-delay:0s]"
                                ></span>
                                <span
                                    class="w-1 h-1 rounded-full bg-gray-500 animate-bounce [animation-delay:0.15s]"
                                ></span>
                                <span
                                    class="w-1 h-1 rounded-full bg-gray-500 animate-bounce [animation-delay:0.3s]"
                                ></span>
                            </span>
                        </div>

                        <div class="w-full space-y-3 pt-1">
                            <Transition name="fade">
                                <p v-if="resent" class="text-xs text-green-400 text-center">
                                    Verification link resent!
                                </p>
                            </Transition>
                            <button
                                :disabled="resendForm.processing"
                                class="w-full py-2 bg-white/5 border border-white/10 text-sm text-gray-300 rounded-lg hover:bg-white/10 transition-colors disabled:opacity-50 cursor-pointer"
                                @click="resendVerification"
                            >
                                {{ resendForm.processing ? 'Sending…' : 'Resend email' }}
                            </button>
                            <p class="text-xs text-gray-600">
                                Wrong address?
                                <button
                                    type="button"
                                    class="text-madder hover:underline cursor-pointer"
                                    @click="((registrationPending = false), (activeTab = 'register'))"
                                >
                                    Go back
                                </button>
                            </p>
                        </div>
                    </div>

                    <!-- Forms screen -->
                    <div v-else key="forms">
                        <!-- Header tabs -->
                        <div class="flex items-center justify-between px-6 pt-6 pb-4">
                            <div class="flex gap-1 bg-white/5 rounded-lg p-1">
                                <button
                                    class="px-4 py-1.5 text-sm font-medium rounded-md transition-colors cursor-pointer"
                                    :class="
                                        activeTab === 'login'
                                            ? 'bg-madder text-white'
                                            : 'text-gray-400 hover:text-white'
                                    "
                                    @click="activeTab = 'login'"
                                >
                                    Sign in
                                </button>
                                <button
                                    class="px-4 py-1.5 text-sm font-medium rounded-md transition-colors cursor-pointer"
                                    :class="
                                        activeTab === 'register'
                                            ? 'bg-madder text-white'
                                            : 'text-gray-400 hover:text-white'
                                    "
                                    @click="activeTab = 'register'"
                                >
                                    Register
                                </button>
                            </div>
                            <button
                                class="text-gray-500 hover:text-white transition-colors cursor-pointer"
                                @click="close"
                            >
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

                        <!-- Login form -->
                        <form v-if="activeTab === 'login'" class="px-6 pb-6 space-y-4" @submit.prevent="submitLogin">
                            <div>
                                <label class="block text-sm text-gray-400 mb-1">Email</label>
                                <input
                                    v-model="loginForm.email"
                                    type="email"
                                    required
                                    class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm placeholder-gray-600 focus:outline-none focus:border-madder"
                                    placeholder="you@example.com"
                                />
                                <p v-if="loginForm.errors.email" class="text-red-400 text-xs mt-1">
                                    {{ loginForm.errors.email }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm text-gray-400 mb-1">Password</label>
                                <input
                                    v-model="loginForm.password"
                                    type="password"
                                    required
                                    class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm placeholder-gray-600 focus:outline-none focus:border-madder"
                                    placeholder="••••••••"
                                />
                                <p v-if="loginForm.errors.password" class="text-red-400 text-xs mt-1">
                                    {{ loginForm.errors.password }}
                                </p>
                            </div>

                            <div class="flex items-center gap-2">
                                <input
                                    id="remember"
                                    v-model="loginForm.remember"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-white/20 bg-white/5 accent-madder"
                                />
                                <label for="remember" class="text-sm text-gray-400">Remember me</label>
                            </div>

                            <button
                                type="submit"
                                :disabled="loginForm.processing"
                                class="w-full py-2 bg-madder text-white text-sm font-medium rounded-lg hover:bg-red-800 transition-colors disabled:opacity-50 cursor-pointer"
                            >
                                Sign in
                            </button>

                            <div class="relative flex items-center gap-3">
                                <div class="flex-1 h-px bg-white/10"></div>
                                <span class="text-xs text-gray-600">or</span>
                                <div class="flex-1 h-px bg-white/10"></div>
                            </div>

                            <a
                                href="/auth/google"
                                class="w-full flex justify-center items-center gap-2 py-2 px-4 bg-white/5 border border-white/10 rounded-lg text-sm text-gray-300 hover:bg-white/10 transition-colors"
                            >
                                <img
                                    src="https://www.svgrepo.com/show/475656/google-color.svg"
                                    class="w-4 h-4"
                                    alt=""
                                />
                                Continue with Google
                            </a>

                            <p class="text-center text-xs text-gray-600">
                                No account?
                                <button
                                    type="button"
                                    class="text-madder hover:underline cursor-pointer"
                                    @click="activeTab = 'register'"
                                >
                                    Register here
                                </button>
                            </p>
                        </form>

                        <!-- Register form -->
                        <form v-else class="px-6 pb-6 space-y-4" @submit.prevent="submitRegister">
                            <div>
                                <label class="block text-sm text-gray-400 mb-1">Email</label>
                                <input
                                    v-model="registerForm.email"
                                    type="email"
                                    required
                                    class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm placeholder-gray-600 focus:outline-none focus:border-madder"
                                    placeholder="you@example.com"
                                />
                                <p v-if="registerForm.errors.email" class="text-red-400 text-xs mt-1">
                                    {{ registerForm.errors.email }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm text-gray-400 mb-1">Password</label>
                                <input
                                    v-model="registerForm.password"
                                    type="password"
                                    required
                                    class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm placeholder-gray-600 focus:outline-none focus:border-madder"
                                    placeholder="••••••••"
                                />
                                <p v-if="registerForm.errors.password" class="text-red-400 text-xs mt-1">
                                    {{ registerForm.errors.password }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm text-gray-400 mb-1">Confirm Password</label>
                                <input
                                    v-model="registerForm.password_confirmation"
                                    type="password"
                                    required
                                    class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm placeholder-gray-600 focus:outline-none focus:border-madder"
                                    placeholder="••••••••"
                                />
                            </div>

                            <div>
                                <label class="block text-sm text-gray-400 mb-1">Default Country</label>
                                <CountrySelect v-model="registerForm.country" />
                            </div>

                            <button
                                type="submit"
                                :disabled="registerForm.processing"
                                class="w-full py-2 bg-madder text-white text-sm font-medium rounded-lg hover:bg-red-800 transition-colors disabled:opacity-50 cursor-pointer"
                            >
                                Create account
                            </button>

                            <p class="text-center text-xs text-gray-600">
                                Already have an account?
                                <button
                                    type="button"
                                    class="text-madder hover:underline cursor-pointer"
                                    @click="activeTab = 'login'"
                                >
                                    Sign in
                                </button>
                            </p>
                        </form>
                    </div>
                </Transition>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.5s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.slide-up-enter-active,
.slide-up-leave-active {
    transition:
        opacity 0.2s ease,
        transform 0.2s ease;
}

.slide-up-enter-from {
    opacity: 0;
    transform: translateY(8px);
}

.slide-up-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}
</style>
