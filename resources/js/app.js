import './bootstrap';
import { createApp } from 'vue';
import FileUpload from './components/FileUpload.vue';

const parseList = (value) => {
    if (!value) {
        return [];
    }

    return value
        .split(',')
        .map((item) => item.trim())
        .filter(Boolean);
};

const parseBoolean = (value, fallback = false) => {
    if (value === undefined || value === null) {
        return fallback;
    }

    if (value === '') {
        return true;
    }

    return ['1', 'true', 'yes', 'on'].includes(String(value).toLowerCase());
};

const parseNumber = (value, fallback) => {
    const parsed = Number.parseFloat(value);

    return Number.isFinite(parsed) ? parsed : fallback;
};

const parseJson = (value, fallback) => {
    if (!value) {
        return fallback;
    }

    try {
        return JSON.parse(value);
    } catch {
        return fallback;
    }
};

const mountFileUploadWidgets = () => {
    document.querySelectorAll('[data-file-upload]').forEach((element) => {
        if (element.dataset.fileUploadMounted === 'true') {
            return;
        }

        const props = {
            uploadUrl: element.dataset.uploadUrl,
            fieldName: element.dataset.fieldName ?? 'file',
            title: element.dataset.title ?? 'Supporting Document Upload',
            description:
                element.dataset.description ??
                'Drag and drop a file here or browse from your device.',
            selectButtonLabel: element.dataset.selectButtonLabel ?? 'Select file',
            uploadButtonLabel: element.dataset.uploadButtonLabel ?? 'Upload file',
            removeButtonLabel: element.dataset.removeButtonLabel ?? 'Remove',
            successMessage:
                element.dataset.successMessage ?? 'Supporting document uploaded successfully.',
            allowedExtensions: parseList(
                element.dataset.allowedExtensions ?? 'pdf,doc,docx,png,jpg,jpeg,webp',
            ),
            maxFileSizeMb: parseNumber(element.dataset.maxFileSizeMb, 5),
            autoUpload: parseBoolean(element.dataset.autoUpload, false),
            headers: parseJson(element.dataset.headers, {}),
            extraFields: parseJson(element.dataset.extraFields, {}),
        };

        createApp(FileUpload, props).mount(element);
        element.dataset.fileUploadMounted = 'true';
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountFileUploadWidgets);
} else {
    mountFileUploadWidgets();
}

window.mountFileUploadWidgets = mountFileUploadWidgets;
