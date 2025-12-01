<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
            <!-- Header -->
            <div class="flex items-center justify-between p-6 border-b">
                <h2 class="text-xl font-semibold text-gray-900">Upload CSV File</h2>
                <button
                    @click="closeModal"
                    :disabled="isProcessing"
                    class="text-gray-400 hover:text-gray-600 disabled:opacity-50 cursor-pointer"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Body -->
            <div class="p-6">
                <!-- File Upload Area -->
                <div v-if="!isProcessing && !isComplete">
                    <div
                        @dragover.prevent="isDragging = true"
                        @dragleave.prevent="isDragging = false"
                        @drop.prevent="handleDrop"
                        class="border-2 border-dashed rounded-lg p-8 text-center transition-colors"
                        :class="isDragging ? 'border-blue-500 bg-blue-50' : 'border-gray-300'"
                    >
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor"
                             viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        <p class="mt-2 text-sm text-gray-600">
                            Drag and drop your CSV file here, or
                        </p>
                        <label class="mt-2 inline-block">
              <span
                  class="px-4 py-2 bg-blue-600 text-white rounded-md cursor-pointer hover:bg-blue-700 transition-colors">
                Browse Files
              </span>
                            <input
                                type="file"
                                @change="handleFileSelect"
                                accept=".csv"
                                class="hidden"
                            >
                        </label>
                    </div>

                    <!-- Selected File Info -->
                    <div v-if="selectedFile" class="mt-4 p-4 bg-gray-50 rounded-lg">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <svg class="w-8 h-8 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                          d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"
                                          clip-rule="evenodd"/>
                                </svg>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ selectedFile.name }}</p>
                                    <p class="text-xs text-gray-500">{{ formatFileSize(selectedFile.size) }}</p>
                                </div>
                            </div>
                            <button @click="selectedFile = null" class="text-red-500 hover:text-red-700">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                          d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                          clip-rule="evenodd"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Error Message -->
                    <div v-if="error" class="mt-4 p-4 bg-red-50 rounded-lg">
                        <p class="text-sm text-red-800">{{ error }}</p>
                    </div>
                </div>

                <!-- Processing State -->
                <div v-if="isProcessing">
                    <div class="text-center mb-4">
                        <svg class="animate-spin h-12 w-12 text-blue-600 mx-auto" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor"
                                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="mt-4 text-sm font-medium text-gray-900">Processing CSV file...</p>
                        <p class="text-xs text-gray-500 mt-1">{{ processedRows }} of {{ totalRows }} rows processed</p>
                    </div>

                    <!-- Progress Bar -->
                    <div class="w-full bg-gray-200 rounded-full h-2.5 overflow-hidden">
                        <div
                            class="bg-blue-600 h-2.5 rounded-full transition-all duration-300"
                            :style="{ width: `${progress}%` }"
                        ></div>
                    </div>
                    <p class="text-center text-sm text-gray-600 mt-2">{{ progress }}%</p>
                </div>

                <!-- Complete State -->
                <div v-if="isComplete" class="text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100">
                        <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <h3 class="mt-4 text-lg font-medium text-gray-900">Upload Complete!</h3>
                    <p class="mt-2 text-sm text-gray-500">Successfully processed {{ totalRows }} rows</p>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex justify-end gap-3 p-6 border-t bg-gray-50">
                <button
                    @click="closeModal"
                    :disabled="isProcessing"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 cursor-pointer
                    rounded-md hover:bg-gray-50 disabled:opacity-50"
                >
                    {{ isComplete ? 'Close' : 'Cancel' }}
                </button>
                <button
                    v-if="!isProcessing && !isComplete"
                    @click="uploadFile"
                    :disabled="!selectedFile"
                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md cursor-pointer
                    hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Upload
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import {ref, computed} from 'vue';
import axios from 'axios';

const props = defineProps({
    isOpen: {
        type: Boolean,
        required: true
    }
});

const emit = defineEmits(['close', 'success']);

const selectedFile = ref(null);
const isDragging = ref(false);
const isProcessing = ref(false);
const isComplete = ref(false);
const error = ref(null);
const processedRows = ref(0);
const totalRows = ref(0);

const progress = computed(() => {
    if (totalRows.value === 0) return 0;
    return Math.round((processedRows.value / totalRows.value) * 100);
});

const handleFileSelect = (event) => {
    const file = event.target.files[0];
    if (file && file.type === 'text/csv') {
        selectedFile.value = file;
        error.value = null;
    } else {
        error.value = 'Please select a valid CSV file';
    }
};

const handleDrop = (event) => {
    isDragging.value = false;
    const file = event.dataTransfer.files[0];
    if (file && file.type === 'text/csv') {
        selectedFile.value = file;
        error.value = null;
    } else {
        error.value = 'Please select a valid CSV file';
    }
};

const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
};

const uploadFile = async () => {
    if (!selectedFile.value) return;

    isProcessing.value = true;
    error.value = null;
    processedRows.value = 0;
    totalRows.value = 0;

    const formData = new FormData();
    formData.append('csv_file', selectedFile.value);

    try {
        const response = await axios.post('/api/csv-upload', formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
            onUploadProgress: (progressEvent) => {
                // This tracks upload progress, not processing
            }
        });

        // Start polling for progress
        const uploadId = response.data.upload_id;
        totalRows.value = response.data.total_rows;
        pollProgress(uploadId);

    } catch (err) {
        isProcessing.value = false;
        error.value = err.response?.data?.message || 'An error occurred during upload';
    }
};

const pollProgress = async (uploadId) => {
    const interval = setInterval(async () => {
        try {
            const response = await axios.get(`/api/csv-upload/${uploadId}/progress`);
            processedRows.value = response.data.processed_rows;
            totalRows.value = response.data.total_rows;

            if (response.data.status === 'completed') {
                clearInterval(interval);
                isProcessing.value = false;
                isComplete.value = true;
                emit('success', response.data);
            } else if (response.data.status === 'failed') {
                clearInterval(interval);
                isProcessing.value = false;
                error.value = response.data.error || 'Processing failed';
            }
        } catch (err) {
            clearInterval(interval);
            isProcessing.value = false;
            error.value = 'Failed to fetch progress';
        }
    }, 1000); // Poll every second
};

const closeModal = () => {
    if (!isProcessing.value) {
        selectedFile.value = null;
        error.value = null;
        isComplete.value = false;
        processedRows.value = 0;
        totalRows.value = 0;
        emit('close');
    }
};
</script>
