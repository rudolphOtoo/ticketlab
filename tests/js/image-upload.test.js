import { describe, expect, it } from 'vitest';
import { fileProblem } from '../../resources/js/media/image-upload.js';

// The limits EventShow-style components get from the server (CloudinaryUploads::sign()).
const limits = { maxBytes: 5 * 1024 * 1024, formats: ['jpg', 'png', 'webp'] };

describe('image upload checks in the browser', () => {
    it.each([
        ['image/jpeg', 'jpg'],
        ['image/png', 'png'],
        ['image/webp', 'webp'],
    ])('accepts %s', (type) => {
        expect(fileProblem({ type, size: 300_000 }, limits)).toBeNull();
    });

    it.each(['image/gif', 'image/svg+xml', 'application/pdf', ''])('refuses %s', (type) => {
        expect(fileProblem({ type, size: 300_000 }, limits)).toBe('Choose a JPG, PNG or WebP image.');
    });

    it('refuses files over 5 MB and says how large they are', () => {
        expect(fileProblem({ type: 'image/jpeg', size: 6 * 1048576 }, limits)).toBe(
            'That image is 6.0 MB. The limit is 5 MB.',
        );
        expect(fileProblem({ type: 'image/jpeg', size: 5 * 1048576 }, limits)).toBeNull();
    });
});
