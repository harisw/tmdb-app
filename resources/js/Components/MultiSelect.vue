<template>
    <div class="w-full">
        <label v-if="label" :for="inputId" class="block mb-2 text-sm font-medium text-gray-700">
            {{ label }}
            <span v-if="required" class="text-red-500">*</span>
        </label>

        <div class="relative" :class="{ 'opacity-60': disabled }">
            <div
                class="flex items-center min-h-[42px] px-2 pr-10 bg-white border rounded-md cursor-pointer transition-all"
                :class="{
          'border-gray-300 hover:border-gray-400': !isOpen && !error,
          'border-blue-500 ring-4 ring-blue-100': isOpen && !error,
          'border-red-500': error,
          'cursor-not-allowed bg-gray-50': disabled
        }"
                @click="toggleDropdown"
                :tabindex="disabled ? -1 : 0"
                @keydown.enter.prevent="toggleDropdown"
                @keydown.space.prevent="toggleDropdown"
                @keydown.escape="closeDropdown"
            >
                <div class="flex flex-wrap items-center flex-1 gap-1">
          <span
              v-for="item in selectedItems"
              :key="getItemValue(item)"
              class="inline-flex items-center gap-1 px-2 py-1 text-sm text-white bg-blue-500 rounded"
          >
            {{ getItemLabel(item) }}
            <button
                type="button"
                @click.stop="removeItem(item)"
                class="flex items-center justify-center w-4 h-4 text-white transition-colors rounded hover:bg-black/20"
                :disabled="disabled"
            >
              ×
            </button>
          </span>
                    <input
                        :id="inputId"
                        ref="searchInput"
                        v-model="searchQuery"
                        type="text"
                        :placeholder="selectedItems.length === 0 ? placeholder : ''"
                        class="flex-1 min-w-[120px] px-1 py-1 text-sm bg-transparent border-none outline-none placeholder:text-gray-400"
                        @focus="openDropdown"
                        @keydown.down.prevent="navigateDown"
                        @keydown.up.prevent="navigateUp"
                        @keydown.enter.prevent="selectHighlighted"
                        @keydown.backspace="handleBackspace"
                        :disabled="disabled"
                    />
                </div>
                <div
                    class="absolute text-gray-600 transition-transform pointer-events-none right-3 top-1/2 -translate-y-1/2"
                    :class="{ 'rotate-180': isOpen }"
                >
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                        <path d="M3.5 5.25L7 8.75L10.5 5.25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </div>

            <transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="isOpen"
                    class="absolute left-0 right-0 z-50 mt-1 overflow-y-auto bg-white border border-gray-300 rounded-md shadow-lg max-h-60"
                >
                    <div v-if="filteredOptions.length === 0" class="px-3 py-3 text-sm text-center text-gray-600">
                        {{ emptyText }}
                    </div>
                    <div
                        v-for="(option, index) in filteredOptions"
                        :key="getItemValue(option)"
                        class="flex items-center gap-2 px-3 py-2.5 cursor-pointer transition-colors"
                        :class="{
              'bg-blue-50': isSelected(option),
              'hover:bg-blue-100': isSelected(option),
              'bg-gray-100': !isSelected(option) && index === highlightedIndex,
              'hover:bg-gray-100': !isSelected(option)
            }"
                        @click="toggleOption(option)"
                        @mouseenter="highlightedIndex = index"
                    >
                        <input
                            type="checkbox"
                            :checked="isSelected(option)"
                            class="w-4 h-4 cursor-pointer accent-blue-500"
                            tabindex="-1"
                        />
                        <span class="text-sm">{{ getItemLabel(option) }}</span>
                    </div>
                </div>
            </transition>
        </div>

        <div v-if="error" class="mt-1 text-sm text-red-500">
            {{ error }}
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => []
    },
    options: {
        type: Array,
        required: true
    },
    label: {
        type: String,
        default: ''
    },
    placeholder: {
        type: String,
        default: 'Select options...'
    },
    emptyText: {
        type: String,
        default: 'No options found'
    },
    trackBy: {
        type: String,
        default: null
    },
    displayBy: {
        type: String,
        default: null
    },
    disabled: {
        type: Boolean,
        default: false
    },
    required: {
        type: Boolean,
        default: false
    },
    error: {
        type: String,
        default: ''
    },
    searchable: {
        type: Boolean,
        default: true
    }
});

const emit = defineEmits(['update:modelValue', 'change']);

const isOpen = ref(false);
const searchQuery = ref('');
const searchInput = ref(null);
const highlightedIndex = ref(0);
const inputId = `multiselect-${Math.random().toString(36).substr(2, 9)}`;

const selectedItems = computed(() => {
    if (!props.trackBy) {
        return props.options.filter(opt => props.modelValue.includes(opt));
    }
    return props.options.filter(opt =>
        props.modelValue.includes(opt[props.trackBy])
    );
});

const filteredOptions = computed(() => {
    if (!props.searchable || !searchQuery.value) {
        return props.options;
    }

    const query = searchQuery.value.toLowerCase();
    return props.options.filter(option => {
        const label = getItemLabel(option).toLowerCase();
        return label.includes(query);
    });
});

const getItemValue = (item) => {
    if (!item) return null;
    return props.trackBy ? item[props.trackBy] : item;
};

const getItemLabel = (item) => {
    if (!item) return '';
    if (props.displayBy) return item[props.displayBy];
    if (typeof item === 'object') return item.name || item.label || item.title || '';
    return String(item);
};

const isSelected = (option) => {
    const value = getItemValue(option);
    return props.modelValue.includes(value);
};

const toggleOption = (option) => {
    if (props.disabled) return;

    const value = getItemValue(option);
    let newValue;

    if (isSelected(option)) {
        newValue = props.modelValue.filter(v => v !== value);
    } else {
        newValue = [...props.modelValue, value];
    }

    emit('update:modelValue', newValue);
    emit('change', newValue);
    searchQuery.value = '';
    searchInput.value?.focus();
};

const removeItem = (item) => {
    if (props.disabled) return;
    toggleOption(item);
};

const toggleDropdown = () => {
    if (props.disabled) return;
    isOpen.value ? closeDropdown() : openDropdown();
};

const openDropdown = () => {
    if (props.disabled) return;
    isOpen.value = true;
    highlightedIndex.value = 0;
};

const closeDropdown = () => {
    isOpen.value = false;
    searchQuery.value = '';
};

const navigateDown = () => {
    if (highlightedIndex.value < filteredOptions.value.length - 1) {
        highlightedIndex.value++;
    }
};

const navigateUp = () => {
    if (highlightedIndex.value > 0) {
        highlightedIndex.value--;
    }
};

const selectHighlighted = () => {
    if (filteredOptions.value[highlightedIndex.value]) {
        toggleOption(filteredOptions.value[highlightedIndex.value]);
    }
};

const handleBackspace = (e) => {
    if (searchQuery.value === '' && selectedItems.value.length > 0) {
        removeItem(selectedItems.value[selectedItems.value.length - 1]);
    }
};

const handleClickOutside = (e) => {
    if (!e.target.closest('.relative')) {
        closeDropdown();
    }
};

watch(() => filteredOptions.value, () => {
    highlightedIndex.value = 0;
});

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>
