<script setup>
import { computed, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { usePage, router } from '@inertiajs/vue3'
import {
    ComboboxAnchor,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxPortal,
    ComboboxRoot,
    ComboboxTrigger,
    ComboboxViewport,
} from 'reka-ui'
import { Check, Globe, Search } from 'lucide-vue-next'
import useMobileDrawer from '../Composables/useMobileDrawer.js'
import { useCountryStore } from '../stores/country.js'
import { button } from '../variants/button.js'

// `dropTop` is for the mobile sidebar's bottom bar, where the trigger sits at
// the bottom of the screen and the menu needs to open upward instead.
const props = defineProps({
    dropTop: { type: Boolean, default: false },
})

const { closeMobileDrawer } = useMobileDrawer()

const page = usePage()
const countries = computed(() => page.props.countries)
const user = computed(() => page.props.auth.user)
const isAuthenticated = computed(() => !!user.value)
const isOpen = ref(false)
const isTouch = window.matchMedia('(pointer: coarse)').matches

const countryStore = useCountryStore()
const { country: guestCountry } = storeToRefs(countryStore)

const selectedIso2 = computed(() => (isAuthenticated.value ? user.value.country : guestCountry.value))

const selected = computed({
    get: () => countries.value.find((country) => country.iso2 === selectedIso2.value),
    set: (country) => country && select(country.iso2),
})

function select(iso2) {
    if (iso2 === selectedIso2.value) {
        return
    }

    if (isAuthenticated.value) {
        router.patch('/profile/country', { country: iso2 }, { preserveScroll: true, preserveState: true })
    } else {
        countryStore.setCountry(iso2)
        router.visit(window.location.pathname, { preserveState: false, preserveScroll: false })
    }

    closeMobileDrawer()
}
</script>

<template>
    <ComboboxRoot v-model="selected" v-model:open="isOpen" by="iso2">
        <ComboboxAnchor as-child>
            <ComboboxTrigger
                :aria-label="`Country: ${selected?.name ?? 'not set'}. Change country`"
                :class="button({ intent: 'link', class: 'gap-1.5 px-2' })"
            >
                <Globe :size="16" aria-hidden="true" />
                <span class="text-xs font-medium uppercase">{{ selectedIso2 || '--' }}</span>
            </ComboboxTrigger>
        </ComboboxAnchor>

        <ComboboxPortal>
            <ComboboxContent
                position="popper"
                :side="dropTop ? 'top' : 'bottom'"
                align="end"
                :side-offset="12"
                :collision-padding="16"
                class="z-[120] flex w-72 max-w-[calc(100vw-2rem)] max-h-[min(24rem,var(--reka-combobox-content-available-height))] flex-col overflow-hidden rounded-box border border-white/15 bg-base-200 text-white shadow-[0_16px_40px_-12px_rgb(0_0_0/0.7)] data-[state=open]:animate-popover-in motion-reduce:data-[state=open]:animate-none"
            >
                <label class="flex shrink-0 items-center gap-2.5 border-b border-white/10 px-3">
                    <Search class="size-4 shrink-0 text-white/40" aria-hidden="true" />
                    <span class="sr-only">Search countries</span>
                    <ComboboxInput
                        :auto-focus="!isTouch"
                        :display-value="() => ''"
                        placeholder="Search countries"
                        class="h-11 min-w-0 flex-1 bg-transparent text-sm caret-madder outline-none placeholder:text-white/40"
                    />
                </label>
                <ComboboxViewport class="min-h-0 flex-1 overflow-y-auto p-1">
                    <ComboboxEmpty class="px-3 py-3 text-sm text-white/50">No country matches that.</ComboboxEmpty>
                    <ComboboxItem
                        v-for="country in countries"
                        :key="country.iso2"
                        :value="country"
                        :text-value="`${country.name} ${country.iso2}`"
                        class="flex cursor-pointer items-center gap-2.5 rounded-md px-2.5 py-2 text-sm text-white/75 outline-none data-[highlighted]:bg-white/10 data-[highlighted]:text-white data-[state=checked]:text-white"
                    >
                        <span
                            :class="`fi fi-${country.iso2.toLowerCase()} fis size-4 shrink-0 rounded-sm`"
                            aria-hidden="true"
                        ></span>
                        <span class="min-w-0 flex-1 truncate">{{ country.name }}</span>
                        <ComboboxItemIndicator>
                            <Check class="size-4 text-tea-green" aria-hidden="true" />
                        </ComboboxItemIndicator>
                    </ComboboxItem>
                </ComboboxViewport>
            </ComboboxContent>
        </ComboboxPortal>
    </ComboboxRoot>
</template>
