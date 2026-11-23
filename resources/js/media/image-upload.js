/**
 * Signed direct image upload (App\Livewire\Concerns\UploadsImages).
 *
 * 1. Ask Livewire to sign an upload for this field (folder per organisation, allowed formats, timestamp).
 * 2. Post the file straight to Cloudinary: it never passes through our serverless function.
 * 3. Give Livewire only what Cloudinary answered (public_id, version, signature); the server checks it before the
 *    image can be saved.
 */

const MIME_TO_FORMAT = { 'image/jpeg': 'jpg', 'image/png': 'png', 'image/webp': 'webp' };

/**
 * Why this file cannot be uploaded, or null when it can. Cloudinary and the server check again.
 *
 * @param {{type: string, size: number}} file
 * @param {{maxBytes: number, formats: string[]}} limits
 * @returns {string|null}
 */
export function fileProblem(file, limits) {
    const format = MIME_TO_FORMAT[file.type];

    if (format === undefined || !limits.formats.includes(format)) {
        return 'Choose a JPG, PNG or WebP image.';
    }

    if (file.size > limits.maxBytes) {
        return `That image is ${(file.size / 1048576).toFixed(1)} MB. The limit is ${limits.maxBytes / 1048576} MB.`;
    }

    return null;
}

/**
 * Posts the file and the signed fields to Cloudinary, reporting progress from 0 to 100.
 *
 * @returns {Promise<{public_id: string, version: number, signature: string}>}
 */
function sendToCloudinary(signed, file, onProgress) {
    return new Promise((resolve, reject) => {
        const form = new FormData();

        for (const [name, value] of Object.entries(signed.fields)) {
            form.append(name, value);
        }

        form.append('file', file);

        const request = new XMLHttpRequest();
        request.open('POST', signed.url);
        request.responseType = 'json';
        request.upload.addEventListener('progress', (event) => {
            if (event.lengthComputable) {
                onProgress(Math.round((event.loaded / event.total) * 100));
            }
        });
        request.addEventListener('load', () => {
            const body = request.response ?? {};

            if (request.status >= 200 && request.status < 300 && body.public_id) {
                resolve(body);
            } else {
                reject(new Error(body.error?.message ?? 'The image could not be uploaded. Please try again.'));
            }
        });
        request.addEventListener('error', () => reject(new Error('The upload failed. Check your connection and try again.')));
        request.send(form);
    });
}

export default function imageUpload(field) {
    return {
        field,
        busy: false,
        progress: 0,
        error: '',

        async upload(event) {
            const input = event.target;
            const file = input.files?.[0];

            if (!file) {
                return;
            }

            this.error = '';
            this.busy = true;
            this.progress = 0;

            try {
                // Null when the server refused (e.g. uploads not configured): Livewire shows that error.
                const signed = await this.$wire.signImageUpload(this.field);

                if (!signed) {
                    return;
                }

                const problem = fileProblem(file, signed);

                if (problem !== null) {
                    this.error = problem;

                    return;
                }

                const result = await sendToCloudinary(signed, file, (percent) => (this.progress = percent));

                await this.$wire.acceptImageUpload(this.field, result.public_id, Number(result.version), result.signature);
            } catch (error) {
                this.error = error?.message ?? 'The upload failed. Please try again.';
            } finally {
                this.busy = false;
                input.value = '';
            }
        },
    };
}
