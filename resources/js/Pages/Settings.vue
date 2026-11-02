<script setup>
import { computed } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { Shuffle } from 'lucide-vue-next'
import AppLayout from '../Layouts/AppLayout.vue'
import SectionHeader from '../Components/SectionHeader.vue'
import CountrySelect from '../Components/CountrySelect.vue'
import AvoidedTagPicker from '../Components/AvoidedTagPicker.vue'
import { button } from '../variants/button.js'
import { field } from '../variants/field.js'

defineProps({
    avoidedKeywords: { type: Array, required: true },
    maxAvoidedKeywords: { type: Number, required: true },
    keywordResults: { type: Object, default: null },
})

const page = usePage()
const user = computed(() => page.props.auth.user)
const avatarBaseUrl = computed(() => page.props.base_url.profile)
const avatarStyles = computed(() => page.props.avatarStyles)

const form = useForm({
    username: user.value.username,
    country: user.value.country ?? '',
    avatar_style: user.value.avatar_style ?? '',
})

const avatarPreview = computed(() => {
    const style = form.avatar_style || 'adventurer'
    const seed = form.username || user.value.username
    return `${avatarBaseUrl.value}${style}/svg?seed=${encodeURIComponent(seed)}`
})

function randomizeAvatar() {
    const styles = avatarStyles.value
    let next
    do {
        next = styles[Math.floor(Math.random() * styles.length)]
    } while (next === form.avatar_style && styles.length > 1)
    form.avatar_style = next
}

function submit() {
    form.patch('/profile', {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
    })
}
</script>

<template>
    <Head title="Settings" />
    <AppLayout>
        <div class="w-full max-w-5xl selection:bg-madder/60 selection:text-white">
            <SectionHeader title="Settings" class="mb-6" />
            <p class="-mt-2 mb-10 text-sm leading-relaxed text-white/50">
                Your profile, your region, and what Chill keeps out of your feed.
            </p>

            <div class="grid gap-10 lg:grid-cols-[17rem_minmax(0,1fr)] lg:gap-x-14">
                <form id="profile-form" class="contents" novalidate @submit.prevent="submit">
                    <aside
                        aria-label="Profile"
                        class="flex flex-col gap-6 rounded-2xl border border-white/10 bg-white/[0.03] p-6 lg:sticky lg:top-28 lg:row-span-2 lg:self-start"
                    >
                        <div class="flex flex-col items-center text-center">
                            <img
                                :key="avatarPreview"
                                :src="avatarPreview"
                                :alt="`Avatar for ${form.username || user.username}`"
                                width="112"
                                height="112"
                                class="size-28 rounded-full bg-white/5 ring-2 ring-madder ring-offset-4 ring-offset-licorice starting:scale-95 starting:opacity-0 transition duration-300 ease-out motion-reduce:transition-none"
                            />
                            <p class="mt-5 max-w-full truncate text-lg font-semibold text-white">
                                @{{ form.username || user.username }}
                            </p>
                            <p class="max-w-full truncate text-sm text-white/45">{{ user.email }}</p>
                            <button
                                type="button"
                                :class="button({ intent: 'soft', size: 'xs', class: 'mt-4 gap-1.5 px-3' })"
                                @click="randomizeAvatar"
                            >
                                <Shuffle class="size-3.5" aria-hidden="true" />
                                Randomize avatar
                            </button>
                        </div>

                        <div class="border-t border-white/10 pt-6">
                            <label for="settings-username" class="block text-sm font-medium text-white/80"
                                >Username</label
                            >
                            <input
                                id="settings-username"
                                v-model="form.username"
                                type="text"
                                required
                                minlength="3"
                                maxlength="30"
                                autocomplete="username"
                                aria-describedby="settings-username-hint"
                                :aria-invalid="!!form.errors.username"
                                :class="
                                    field({
                                        state: form.errors.username ? 'error' : 'default',
                                        class: 'mt-2 h-10 px-3 text-sm caret-madder',
                                    })
                                "
                            />
                            <p v-if="form.errors.username" class="mt-1.5 text-sm text-red-300">
                                {{ form.errors.username }}
                            </p>
                            <p v-else id="settings-username-hint" class="mt-1.5 text-sm text-white/55">
                                3–30 characters.
                            </p>
                        </div>
                    </aside>

                    <section aria-labelledby="region-heading" class="lg:col-start-2 lg:row-start-1">
                        <h2 id="region-heading" class="text-lg font-semibold text-white">Region</h2>
                        <p class="mt-1.5 max-w-[60ch] text-sm leading-relaxed text-white/55">
                            Streaming availability follows your country.
                        </p>

                        <dl class="mt-6 divide-y divide-white/10 border-y border-white/10">
                            <div class="grid gap-3 py-5 sm:grid-cols-[12rem_minmax(0,1fr)] sm:items-center">
                                <dt>
                                    <label for="settings-country" class="text-sm font-medium text-white/80"
                                        >Country</label
                                    >
                                </dt>
                                <dd>
                                    <CountrySelect
                                        v-model="form.country"
                                        input-id="settings-country"
                                        :invalid="!!form.errors.country"
                                    />
                                    <p v-if="form.errors.country" class="mt-1.5 text-sm text-red-300">
                                        {{ form.errors.country }}
                                    </p>
                                </dd>
                            </div>
                        </dl>

                        <div class="mt-6 flex items-center justify-end gap-4">
                            <span v-if="form.isDirty" class="flex items-center gap-2 text-sm text-white/60">
                                <span class="size-1.5 rounded-full bg-madder" aria-hidden="true"></span>
                                Unsaved changes
                            </span>
                            <span v-else-if="form.recentlySuccessful" class="text-sm text-tea-green">Saved</span>
                            <button
                                type="submit"
                                :disabled="!form.isDirty || form.processing"
                                :class="
                                    button({
                                        intent: 'primary',
                                        size: 'md',
                                        class: 'px-6 disabled:bg-white/10 disabled:text-white/40',
                                    })
                                "
                            >
                                {{ form.processing ? 'Saving…' : 'Save changes' }}
                            </button>
                        </div>
                    </section>
                </form>

                <AvoidedTagPicker
                    class="lg:col-start-2 lg:row-start-2"
                    :keywords="avoidedKeywords"
                    :max="maxAvoidedKeywords"
                    :results="keywordResults"
                />
            </div>
        </div>
    </AppLayout>
</template>
