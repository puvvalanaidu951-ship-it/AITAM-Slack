(() => {
    const form = document.querySelector('[data-update-form]');

    if (form instanceof HTMLFormElement) {
        const title = form.querySelector('[name="title"]');
        const description = form.querySelector('[name="description"]');
        const category = form.querySelector('[name="category"]');
        const publicationDate = form.querySelector('[name="published_at"]');
        const previewTitle = document.querySelector('[data-preview-title]');
        const previewDescription = document.querySelector('[data-preview-description]');
        const previewCategory = document.querySelector('[data-preview-category]');
        const previewDate = document.querySelector('[data-preview-date]');
        const previewMedia = document.querySelector('[data-preview-media]');
        const imageInput = form.querySelector('[name="images[]"]');
        const videoInput = form.querySelector('[name="video"]');
        const progress = form.querySelector('[data-upload-progress]');
        const progressLabel = form.querySelector('[data-upload-progress-label]');
        const status = form.querySelector('[data-upload-status]');
        const statusInput = form.querySelector('[data-publication-status]');
        const submitButtons = Array.from(
            form.querySelectorAll('button[type="submit"]')
        );
        let previewUrls = [];
        let uploadInProgress = false;

        const updateTextPreview = () => {
            if (previewTitle) {
                previewTitle.textContent = title && title.value.trim()
                    ? title.value.trim()
                    : 'Update title preview';
            }
            if (previewDescription) {
                previewDescription.textContent = description && description.value.trim()
                    ? description.value.trim()
                    : 'The event description will appear here.';
            }
            if (previewCategory) {
                previewCategory.textContent = category ? category.value : 'Category';
            }
            if (previewDate) {
                previewDate.textContent = publicationDate && publicationDate.value
                    ? new Date(publicationDate.value).toLocaleString()
                    : 'Publication date and time';
            }
        };

        const updateMediaPreview = () => {
            if (!previewMedia) {
                return;
            }

            previewUrls.forEach((url) => URL.revokeObjectURL(url));
            previewUrls = [];

            const imageFiles = imageInput instanceof HTMLInputElement
                ? Array.from(imageInput.files || [])
                : [];
            const videoFiles = videoInput instanceof HTMLInputElement
                ? Array.from(videoInput.files || [])
                : [];
            const files = [...imageFiles, ...videoFiles];

            if (files.length === 0) {
                const existing = previewMedia.dataset.existingSrc;
                const existingType = previewMedia.dataset.existingType;

                if (existing && existingType) {
                    const media = document.createElement(existingType === 'video' ? 'video' : 'img');
                    media.src = existing;
                    if (existingType === 'video') {
                        media.controls = true;
                        media.preload = 'metadata';
                    } else {
                        media.alt = 'Current event media preview';
                    }
                    previewMedia.replaceChildren(media);
                }
                return;
            }

            const mediaFiles = files.slice(0, 4);
            previewMedia.replaceChildren();

            mediaFiles.forEach((file) => {
                const url = URL.createObjectURL(file);
                previewUrls.push(url);
                const media = document.createElement(file.type.startsWith('video/')
                    ? 'video'
                    : 'img');
                media.src = url;
                if (media instanceof HTMLVideoElement) {
                    media.controls = true;
                    media.preload = 'metadata';
                } else {
                    media.alt = file.name;
                }
                previewMedia.append(media);
            });
        };

        [title, description, category, publicationDate].forEach((input) => {
            input?.addEventListener('input', updateTextPreview);
            input?.addEventListener('change', updateTextPreview);
        });
        imageInput?.addEventListener('change', updateMediaPreview);
        videoInput?.addEventListener('change', updateMediaPreview);
        updateTextPreview();

        form.addEventListener('submit', (event) => {
            if (uploadInProgress) {
                event.preventDefault();
                return;
            }

            if (!window.XMLHttpRequest || !progress || !status) {
                return;
            }

            event.preventDefault();
            uploadInProgress = true;
            submitButtons.forEach((button) => {
                button.disabled = true;
            });
            if (
                statusInput instanceof HTMLInputElement
                && event.submitter instanceof HTMLButtonElement
            ) {
                statusInput.value = event.submitter.value;
            }
            status.textContent = 'Uploading and saving update...';
            progress.hidden = false;
            progress.value = 0;

            const finishRequest = () => {
                uploadInProgress = false;
                submitButtons.forEach((button) => {
                    button.disabled = false;
                });
            };

            const request = new XMLHttpRequest();
            request.open(
                'POST',
                form.getAttribute('action') || window.location.href
            );

            request.upload.addEventListener('progress', (progressEvent) => {
                if (!progressEvent.lengthComputable) {
                    return;
                }

                const percent = Math.round(
                    (progressEvent.loaded / progressEvent.total) * 100
                );
                progress.value = percent;
                if (progressLabel) {
                    progressLabel.textContent = `Upload progress: ${percent}%`;
                }
            });

            request.addEventListener('load', () => {
                if (request.status >= 200 && request.status < 400) {
                    window.location.assign(request.responseURL || window.location.href);
                    return;
                }

                let message = 'The update could not be saved. Check the form and try again.';
                try {
                    const response = new DOMParser().parseFromString(
                        request.responseText,
                        'text/html'
                    );
                    const errors = response.querySelector('[data-update-errors]');
                    if (errors && errors.textContent.trim()) {
                        message = errors.textContent.trim();
                    }
                } catch (error) {
                    console.error('Could not read the update form response.', error);
                }
                status.textContent = message;
                progress.hidden = true;
                finishRequest();
            });

            request.addEventListener('error', () => {
                status.textContent = 'A network error interrupted the upload. Check your connection and retry.';
                progress.hidden = true;
                finishRequest();
            });

            request.addEventListener('abort', () => {
                status.textContent = 'The upload was cancelled.';
                progress.hidden = true;
                finishRequest();
            });

            request.send(new FormData(form));
        });
    }

    document.querySelectorAll('[data-confirm-delete]').forEach((button) => {
        button.addEventListener('click', (event) => {
            if (!window.confirm('Delete this update and its uploaded media? This cannot be undone.')) {
                event.preventDefault();
            }
        });
    });

    const filterForm = document.querySelector('[data-update-filters]');
    const filterStatus = document.querySelector('[data-filter-status]');

    filterForm?.addEventListener('submit', () => {
        if (filterStatus) {
            filterStatus.textContent = 'Loading college updates...';
        }
    });
})();
