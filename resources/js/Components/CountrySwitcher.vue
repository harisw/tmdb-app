<script setup>
import { computed } from 'vue'
import { storeToRefs } from 'pinia'
import { usePage, router } from '@inertiajs/vue3'
import { Globe } from 'lucide-vue-next'
import useMobileDrawer from '../Composables/useMobileDrawer.js'
import { useCountryStore } from '../stores/country.js'
import { menu } from '../variants/menu.js'
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

const countryStore = useCountryStore()
const { country: guestCountry } = storeToRefs(countryStore)

const selectedIso2 = computed(() => (isAuthenticated.value ? user.value.country : guestCountry.value))

const { item } = menu({ width: 'lg', pad: 'sm', offset: props.dropTop ? 'up' : 'gap' })

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

    document.activeElement?.blur()
    closeMobileDrawer()
}
</script>

<template>
    <div class="dropdown dropdown-end" :class="{ 'dropdown-top': dropTop }">
        <label tabindex="0" :class="button({ intent: 'link', class: 'gap-1.5 px-2' })">
            <Globe :size="16" />
            <span class="text-xs font-medium uppercase">{{ selectedIso2 || '--' }}</span>
        </label>
        <ul
            tabindex="0"
            class="dropdown-content z-[100] p-2 shadow-lg bg-black border border-gray-800 rounded-box w-56 max-w-[70vw] max-h-72 text-white overflow-y-scroll"
            :class="dropTop ? 'mb-3' : 'mt-3'"
        >
            <li v-for="c in countries" :key="c.iso2">
                <button
                    type="button"
                    :class="
                        item({ class: ['w-full flex items-center gap-2', { 'bg-gray-800': c.iso2 === selectedIso2 }] })
                    "
                    @click="select(c.iso2)"
                >
                    <span :class="`fi fi-${c.iso2.toLowerCase()} fis shrink-0`"></span>
                    <span class="truncate">{{ c.name }}</span>
                </button>
            </li>
        </ul>
    </div>
</template>
