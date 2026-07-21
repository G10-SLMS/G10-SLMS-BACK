<template>
    <section class="w-full overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 bg-gradient-to-r from-slate-50 to-white px-5 py-4 sm:px-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-500">
                        Supporting documents
                    </p>
                    <h2 class="mt-1 text-xl font-semibold text-slate-900">
                        {{ props.title }}
                    </h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                        {{ props.description }}
                    </p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-600 shadow-sm">
                    <span class="block text-[10px] uppercase tracking-[0.2em] text-slate-400">Accepted</span>
                    <span class="mt-1 block">{{ supportedLabel }}</span>
                </div>
            </div>
        </div>

        <div class="space-y-5 p-5 sm:p-6">
            <div
                class="group flex cursor-pointer flex-col items-center justify-center rounded-3xl border-2 border-dashed px-4 py-8 text-center transition"
                :class="
                    isDragging
                        ? 'border-sky-500 bg-sky-50'
                        : 'border-slate-300 bg-slate-50 hover:border-slate-400 hover:bg-slate-100'
                "
                role="button"
                tabindex="0"
                :aria-busy="isUploading"
                @click="openPicker"
                @keydown.enter.prevent="openPicker"
                @keydown.space.prevent="openPicker"
                @dragenter.prevent="isDragging = true"
                @dragover.prevent="isDragging = true"
                @dragleave.prevent="handleDragLeave"
                @drop.prevent="handleDrop"
            >
                <input
                    ref="inputRef"
                    type="file"
                    class="hidden"
                    :accept="acceptAttribute"
                    :disabled="props.disabled || isUploading"
                    @change="handleInputChange"
                />

                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-900 text-white shadow-lg shadow-slate-900/10">
                    <svg viewBox="0 0 24 24" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 16V4" />
                        <path d="m8 8 4-4 4 4" />
                        <path d="M4 16.5V19a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-2.5" />
                    </svg>
                </div>

                <p class="mt-4 text-base font-semibold text-slate-900">
                    {{ selectedFile ? 'File ready to upload' : 'Drag and drop your file here' }}
                </p>
                <p class="mt-1 max-w-xl text-sm text-slate-600">
                    {{
                        selectedFile
                            ? 'You can replace or remove the file before uploading it.'
                            : 'Click Select file or drop a document into this area.'
                    }}
                </p>

                <div class="mt-4 flex flex-wrap items-center justify-center gap-3">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-full bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="props.disabled || isUploading"
                        @click.stop="openPicker"
                    >
                        {{ props.selectButtonLabel }}
                    </button>

                    <button
                        v-if="selectedFile"
                        type="button"
                        class="inline-flex items-center justify-center rounded-full border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="isUploading"
                        @click.stop="removeFile"
                    >
                        {{ props.removeButtonLabel }}
                    </button>
                </div>
            </div>

            <div v-if="selectedFile" class="grid gap-4 lg:grid-cols-[minmax(0,1.5fr)_minmax(280px,0.85fr)]">
                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
                    <div class="flex items-start gap-4">
                        <div class="flex h-24 w-24 shrink-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <img
                                v-if="previewKind === 'image'"
                                :src="previewUrl"
                                :alt="selectedFile.name"
                                class="h-full w-full object-cover"
                            />
                            <iframe
                                v-else-if="previewKind === 'pdf'"
                                :src="previewUrl"
                                title="PDF preview"
                                class="h-full w-full border-0 bg-white"
                            />
                            <div v-else class="flex h-full w-full flex-col items-center justify-center gap-1 bg-slate-900 text-white">
                                <svg viewBox="0 0 24 24" class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M8 2h5l5 5v15H8z" />
                                    <path d="M13 2v5h5" />
                                </svg>
                                <span class="text-xs font-semibold uppercase tracking-[0.2em]">{{ selectedFileExtension }}</span>
                            </div>
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-base font-semibold text-slate-900">
                                {{ selectedFile.name }}
                            </p>
                            <p class="mt-1 text-sm text-slate-600">
                                {{ selectedFileSize }} · {{ selectedFile.type || 'Unknown file type' }}
                            </p>
                            <p class="mt-3 text-xs font-medium uppercase tracking-[0.2em] text-slate-500">
                                {{ previewLabel }}
                            </p>
                        </div>
                    </div>

                    <div v-if="isUploading || uploadProgress > 0" class="mt-5">
                        <div class="flex items-center justify-between text-xs font-medium text-slate-500">
                            <span>Upload progress</span>
                            <span>{{ uploadProgress }}%</span>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200">
                            <div
                                class="h-full rounded-full bg-sky-500 transition-[width] duration-300"
                                :style="{ width: `${uploadProgress}%` }"
                            />
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">
                        Upload details
                    </h3>

                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-slate-500">Max size</dt>
                            <dd class="font-medium text-slate-900">{{ maxFileSizeLabel }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-slate-500">Field name</dt>
                            <dd class="font-medium text-slate-900">{{ props.fieldName }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-slate-500">Mode</dt>
                            <dd class="font-medium text-slate-900">
                                {{ props.autoUpload ? 'Automatic upload' : 'Manual upload' }}
                            </dd>
                        </div>
                    </dl>

                    <div class="mt-5 flex flex-col gap-3 sm:flex-row">
                        <button
                            type="button"
                            class="inline-flex flex-1 items-center justify-center rounded-full bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-sky-500 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!selectedFile || isUploading || props.disabled || !props.uploadUrl"
                            @click="uploadFile"
                        >
                            {{ uploadButtonLabel }}
                        </button>

                        <button
                            type="button"
                            class="inline-flex flex-1 items-center justify-center rounded-full border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!selectedFile || isUploading"
                            @click="removeFile"
                        >
                            {{ props.removeButtonLabel }}
                        </button>
                    </div>

                    <p class="mt-4 text-xs leading-5 text-slate-500">
                        You can drag a file into the upload area, preview it below, and remove it before sending it to the API.
                    </p>
                </div>
            </div>

            <div
                v-else
                class="rounded-3xl border border-slate-200 bg-slate-50 p-4 text-sm leading-6 text-slate-600 sm:p-5"
            >
                No file selected yet. Choose a document to preview it here before uploading.
            </div>

            <div
                v-if="validationErrors.length"
                class="rounded-3xl border border-amber-200 bg-amber-50 p-4 text-amber-900"
                aria-live="polite"
            >
                <p class="text-sm font-semibold">Please fix the following:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                    <li v-for="error in validationErrors" :key="error">
                        {{ error }}
                    </li>
                </ul>
            </div>

            <div
                v-if="statusMessage"
                class="rounded-3xl border p-4"
                :class="statusToneClasses"
                aria-live="polite"
            >
                <p class="text-sm font-semibold">
                    {{ statusTitle }}
                </p>
                <p class="mt-1 text-sm leading-6">
                    {{ statusMessage }}
                </p>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    uploadUrl: {
        type: String,
        required: true,
    },
    title: {
        type: String,
        default: 'Supporting Document Upload',
    },
    description: {
        type: String,
        default: 'Drag and drop a file here or browse from your device.',
    },
    fieldName: {
        type: String,
        default: 'file',
    },
    allowedExtensions: {
        type: Array,
        default: () => ['pdf', 'doc', 'docx', 'png', 'jpg', 'jpeg', 'webp'],
    },
    maxFileSizeMb: {
        type: Number,
        default: 5,
    },
    headers: {
        type: Object,
        default: () => ({}),
    },
    extraFields: {
        type: Object,
        default: () => ({}),
    },
    autoUpload: {
        type: Boolean,
        default: false,
    },
    selectButtonLabel: {
        type: String,
        default: 'Select file',
    },
    uploadButtonLabel: {
        type: String,
        default: 'Upload file',
    },
    removeButtonLabel: {
        type: String,
        default: 'Remove',
    },
    successMessage: {
        type: String,
        default: 'Supporting document uploaded successfully.',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['selected', 'removed', 'uploaded', 'error', 'progress']);

const inputRef = ref(null);
const selectedFile = ref(null);
const previewUrl = ref('');
const isDragging = ref(false);
const isUploading = ref(false);
const uploadProgress = ref(0);
const statusType = ref('');
const statusMessage = ref('');
const validationErrors = ref([]);

const mimeTypesByExtension = {
    pdf: ['application/pdf'],
    doc: ['application/msword', 'application/vnd.ms-word', 'application/x-msword'],
    docx: ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    png: ['image/png'],
    jpg: ['image/jpeg'],
    jpeg: ['image/jpeg'],
    webp: ['image/webp'],
};

const normalizedExtensions = computed(() =>
    Array.from(
        new Set(
            (props.allowedExtensions ?? [])
                .map((extension) => String(extension).trim().replace(/^\./, '').toLowerCase())
                .filter(Boolean),
        ),
    ),
);

const hasExtensionRestrictions = computed(() => normalizedExtensions.value.length > 0);

const supportedLabel = computed(() => {
    if (!hasExtensionRestrictions.value) {
        return 'Any file type';
    }

    return normalizedExtensions.value.map((extension) => extension.toUpperCase()).join(', ');
});

const acceptAttribute = computed(() => {
    if (!hasExtensionRestrictions.value) {
        return '';
    }

    return normalizedExtensions.value.map((extension) => `.${extension}`).join(',');
});

const allowedMimeTypes = computed(() =>
    Array.from(
        new Set(
            normalizedExtensions.value.flatMap((extension) => mimeTypesByExtension[extension] ?? []),
        ),
    ),
);

const maxFileSizeBytes = computed(() => props.maxFileSizeMb * 1024 * 1024);
const maxFileSizeLabel = computed(() => `${props.maxFileSizeMb} MB`);

const previewKind = computed(() => getPreviewKind(selectedFile.value));
const selectedFileExtension = computed(() => getFileExtension(selectedFile.value?.name) || 'FILE');
const selectedFileSize = computed(() =>
    selectedFile.value ? formatFileSize(selectedFile.value.size) : '',
);
const previewLabel = computed(() => {
    if (previewKind.value === 'image') {
        return 'Image preview';
    }

    if (previewKind.value === 'pdf') {
        return 'PDF preview';
    }

    return 'Document preview';
});

const uploadButtonLabel = computed(() => {
    if (isUploading.value) {
        return `Uploading ${uploadProgress.value}%`;
    }

    return props.uploadButtonLabel;
});

const statusTitle = computed(() => {
    if (statusType.value === 'success') {
        return 'Upload complete';
    }

    if (statusType.value === 'error') {
        return 'Upload failed';
    }

    if (statusType.value === 'info' && isUploading.value) {
        return 'Uploading';
    }

    if (statusType.value === 'info') {
        return 'Ready to upload';
    }

    return 'File upload';
});

const statusToneClasses = computed(() => {
    if (statusType.value === 'success') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-900';
    }

    if (statusType.value === 'error') {
        return 'border-rose-200 bg-rose-50 text-rose-900';
    }

    if (statusType.value === 'info') {
        return 'border-sky-200 bg-sky-50 text-sky-900';
    }

    return 'border-slate-200 bg-slate-50 text-slate-700';
});

function getAxiosClient() {
    if (typeof window !== 'undefined' && window.axios) {
        return window.axios;
    }

    return axios;
}

function getFileExtension(fileName) {
    if (!fileName || !fileName.includes('.')) {
        return '';
    }

    return fileName.split('.').pop()?.toLowerCase() ?? '';
}

function getPreviewKind(file) {
    if (!file) {
        return 'none';
    }

    const mimeType = String(file.type ?? '').toLowerCase();
    const extension = getFileExtension(file.name);

    if (mimeType.startsWith('image/')) {
        return 'image';
    }

    if (mimeType === 'application/pdf' || extension === 'pdf') {
        return 'pdf';
    }

    return 'document';
}

function formatFileSize(bytes) {
    if (!Number.isFinite(bytes) || bytes < 0) {
        return '0 B';
    }

    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let value = bytes;
    let unitIndex = 0;

    while (value >= 1024 && unitIndex < units.length - 1) {
        value /= 1024;
        unitIndex += 1;
    }

    const fractionDigits = unitIndex === 0 || value >= 10 ? 0 : 1;

    return `${value.toFixed(fractionDigits)} ${units[unitIndex]}`;
}

function clearPreview() {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
        previewUrl.value = '';
    }
}

function clearInput() {
    if (inputRef.value) {
        inputRef.value.value = '';
    }
}

function clearSelection({ keepStatus = false } = {}) {
    clearPreview();
    selectedFile.value = null;
    uploadProgress.value = 0;
    validationErrors.value = [];
    isDragging.value = false;
    clearInput();

    if (!keepStatus) {
        statusType.value = '';
        statusMessage.value = '';
    }
}

function setStatus(type, message) {
    statusType.value = type;
    statusMessage.value = message;
}

function flattenErrors(errors) {
    return Object.values(errors ?? {}).reduce((messages, value) => {
        if (Array.isArray(value)) {
            messages.push(...value.map((item) => String(item)));
            return messages;
        }

        if (value) {
            messages.push(String(value));
        }

        return messages;
    }, []);
}

function appendFormValue(formData, key, value) {
    if (value === undefined || value === null) {
        return;
    }

    if (Array.isArray(value)) {
        value.forEach((item) => appendFormValue(formData, `${key}[]`, item));
        return;
    }

    if (value instanceof Date) {
        formData.append(key, value.toISOString());
        return;
    }

    if (typeof value === 'object') {
        formData.append(key, JSON.stringify(value));
        return;
    }

    formData.append(key, value);
}

function validateFile(file) {
    const errors = [];

    if (!file) {
        errors.push('Please select a file to upload.');
        return errors;
    }

    const extension = getFileExtension(file.name);
    const mimeType = String(file.type ?? '').toLowerCase();
    const allowedByExtension = hasExtensionRestrictions.value
        ? normalizedExtensions.value.includes(extension)
        : true;
    const allowedByMimeType = allowedMimeTypes.value.length
        ? allowedMimeTypes.value.some((allowedMimeType) => mimeType === allowedMimeType)
        : true;

    if (hasExtensionRestrictions.value && !allowedByExtension && !allowedByMimeType) {
        errors.push(`Unsupported file type. Allowed types: ${supportedLabel.value}.`);
    }

    if (file.size > maxFileSizeBytes.value) {
        errors.push(`The selected file is too large. Maximum size is ${props.maxFileSizeMb} MB.`);
    }

    return errors;
}

function handleSelectedFile(file) {
    const errors = validateFile(file);

    if (errors.length > 0) {
        validationErrors.value = errors;
        setStatus('error', 'Please review the validation message below.');
        clearInput();
        emit('error', {
            type: 'validation',
            errors,
            file,
        });
        return false;
    }

    clearSelection();

    selectedFile.value = file;
    const kind = getPreviewKind(file);

    if (kind === 'image' || kind === 'pdf') {
        previewUrl.value = URL.createObjectURL(file);
    }

    setStatus('info', `${file.name} is ready to upload.`);
    emit('selected', file);

    if (props.autoUpload) {
        void uploadFile();
    }

    return true;
}

function openPicker() {
    if (props.disabled || isUploading.value) {
        return;
    }

    inputRef.value?.click();
}

function handleInputChange(event) {
    const files = Array.from(event.target.files ?? []);

    if (files.length === 0) {
        return;
    }

    if (files.length > 1) {
        validationErrors.value = ['Only one file can be uploaded at a time.'];
        setStatus('error', 'Please select a single file.');
        clearInput();
        return;
    }

    handleSelectedFile(files[0]);
}

function handleDragLeave(event) {
    if (event?.relatedTarget && event.currentTarget?.contains(event.relatedTarget)) {
        return;
    }

    isDragging.value = false;
}

function handleDrop(event) {
    isDragging.value = false;

    const files = Array.from(event.dataTransfer?.files ?? []);

    if (files.length === 0) {
        return;
    }

    if (files.length > 1) {
        validationErrors.value = ['Only one file can be uploaded at a time.'];
        setStatus('error', 'Please drop a single file.');
        return;
    }

    handleSelectedFile(files[0]);
}

function removeFile() {
    if (!selectedFile.value) {
        clearSelection();
        return;
    }

    const removedFile = selectedFile.value;
    clearSelection();
    emit('removed', removedFile);
}

async function uploadFile() {
    if (isUploading.value) {
        return;
    }

    if (!props.uploadUrl) {
        validationErrors.value = ['An upload URL is required.'];
        setStatus('error', 'Upload URL is required.');
        return;
    }

    if (!selectedFile.value) {
        validationErrors.value = ['Please select a file before uploading.'];
        setStatus('error', 'Select a file first.');
        return;
    }

    const fileToUpload = selectedFile.value;
    const client = getAxiosClient();
    const formData = new FormData();

    formData.append(props.fieldName, fileToUpload);

    Object.entries(props.extraFields ?? {}).forEach(([key, value]) => {
        appendFormValue(formData, key, value);
    });

    validationErrors.value = [];
    isUploading.value = true;
    uploadProgress.value = 0;
    setStatus('info', 'Uploading file...');

    try {
        const response = await client.post(props.uploadUrl, formData, {
            headers: {
                ...props.headers,
            },
            onUploadProgress: (event) => {
                if (!event.total) {
                    return;
                }

                uploadProgress.value = Math.round((event.loaded * 100) / event.total);
                emit('progress', uploadProgress.value);
            },
        });

        const responsePayload = response?.data ?? {};
        const successText =
            responsePayload.message ||
            props.successMessage ||
            `${fileToUpload.name} uploaded successfully.`;

        setStatus('success', successText);
        emit('uploaded', {
            file: fileToUpload,
            response: responsePayload,
        });

        clearSelection({ keepStatus: true });
    } catch (error) {
        const responseData = error?.response?.data ?? {};
        const backendErrors = flattenErrors(responseData.errors);

        validationErrors.value = backendErrors;
        setStatus('error', responseData.message || 'The file could not be uploaded.');

        emit('error', {
            type: 'upload',
            message: responseData.message || 'The file could not be uploaded.',
            errors: backendErrors,
            response: responseData,
        });
    } finally {
        isUploading.value = false;
    }
}

onBeforeUnmount(() => {
    clearPreview();
});
</script>
