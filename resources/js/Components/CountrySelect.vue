<script setup>
import { computed, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
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
import { Check, ChevronDown, Search } from 'lucide-vue-next'
import { field } from '../variants/field.js'

const model = defineModel({ type: String, default: '' })

defineProps({
    inputId: { type: String, default: undefined },
    invalid: { type: Boolean, default: false },
})

const page = usePage()
const countries = computed(() => page.props.countries)
const isOpen = ref(false)

const selected = computed({
    get: () => countries.value.find((country) => country.iso2 === model.value),
    set: (country) => (model.value = country?.iso2 ?? ''),
})

function closeWithoutBubbling(event) {
    if (isOpen.value) {
        event.stopPropagation()
        isOpen.value = false
    }
}
</script>

<template>
    <ComboboxRoot v-model="selected" v-model:open="isOpen" by="iso2" open-on-click>
        <ComboboxAnchor
            :class="
                field({
                    state: invalid ? 'error' : 'default',
                    open: isOpen,
                    class: 'flex h-10 items-center gap-2.5 pl-3 pr-1 text-sm',
                })
            "
        >
            <span
                v-if="selected"
                :class="`fi fi-${selected.iso2.toLowerCase()} fis size-4 shrink-0 rounded-sm`"
                aria-hidden="true"
            ></span>
            <Search v-else class="size-4 shrink-0 text-white/40" aria-hidden="true" />
            <ComboboxInput
                :id="inputId"
                :display-value="(country) => country?.name ?? ''"
                placeholder="Search countries"
                :aria-invalid="invalid || undefined"
                class="h-full min-w-0 flex-1 bg-transparent text-white caret-madder outline-none placeholder:text-white/40"
                @focus="$event.target.select()"
                @keydown.escape="closeWithoutBubbling"
            />
            <ComboboxTrigger
                aria-label="Show all countries"
                class="grid size-8 shrink-0 cursor-pointer place-items-center rounded-md text-white/50 transition-colors hover:bg-white/10 hover:text-white motion-reduce:transition-none"
            >
                <ChevronDown
                    class="size-4 transition-transform motion-reduce:transition-none"
                    :class="{ 'rotate-180': isOpen }"
                    aria-hidden="true"
                />
            </ComboboxTrigger>
        </ComboboxAnchor>

        <ComboboxPortal>
            <ComboboxContent
                position="popper"
                :side-offset="6"
                class="z-[120] w-[var(--reka-combobox-trigger-width)] overflow-hidden rounded-field border border-white/15 bg-base-200 shadow-[0_16px_40px_-12px_rgb(0_0_0/0.7)] data-[state=open]:animate-popover-in motion-reduce:data-[state=open]:animate-none"
            >
                <ComboboxViewport class="max-h-64 p-1">
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
