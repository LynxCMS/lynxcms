/**
 * Scales an image file down in the browser before it is uploaded, so a phone's photo (often 12 to
 * 20 MB) fits the upload limit and the server gets the size it would keep anyway. Only JPEG, PNG
 * and WebP files larger than the maximum change; animated PNG and WebP, other types and files the
 * browser can't read are sent as they are. The server still checks and scales every upload.
 */
const SHRINKABLE_TYPES = ['image/jpeg', 'image/png', 'image/webp']

/** Bytes read to find an animation's marker; PNG's acTL and WebP's VP8X header come first. */
const HEADER_BYTES = 4096

/**
 * @param {File} file
 * @param {{maxWidth: number, maxHeight: number, quality: number}} limits
 * @returns {Promise<File>} the scaled file, or the same file when it is left as it is
 */
export async function shrinkImage(file, limits) {
    if (! SHRINKABLE_TYPES.includes(file.type) || await isAnimated(file)) {
        return file
    }

    let bitmap

    try {
        // The photo's EXIF orientation is applied while decoding, as the server's orient() does.
        bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' })
    } catch {
        return file
    }

    const scale = Math.min(1, limits.maxWidth / bitmap.width, limits.maxHeight / bitmap.height)

    if (scale >= 1) {
        bitmap.close()

        return file
    }

    const width = Math.max(1, Math.round(bitmap.width * scale))
    const height = Math.max(1, Math.round(bitmap.height * scale))
    const blob = await draw(bitmap, width, height, file.type, limits.quality / 100)
    bitmap.close()

    return blob === null ? file : new File([blob], file.name, { type: file.type, lastModified: file.lastModified })
}

/** Draws the bitmap at the new size and encodes it in the file's own type. */
function draw(bitmap, width, height, type, quality) {
    const canvas = document.createElement('canvas')
    canvas.width = width
    canvas.height = height
    const context = canvas.getContext('2d')

    if (context === null) {
        return Promise.resolve(null)
    }

    context.imageSmoothingQuality = 'high'
    context.drawImage(bitmap, 0, 0, width, height)

    return new Promise((resolve) => canvas.toBlob(resolve, type, quality))
}

/** An animated PNG (acTL chunk) or WebP (VP8X animation flag) would keep only its first frame. */
async function isAnimated(file) {
    if (file.type === 'image/jpeg') {
        return false
    }

    const bytes = new Uint8Array(await file.slice(0, HEADER_BYTES).arrayBuffer())
    const text = String.fromCharCode(...bytes)

    if (file.type === 'image/png') {
        return text.includes('acTL')
    }

    // RIFF....WEBPVP8X, then a flags byte whose bit 1 marks an animation.
    return text.slice(12, 16) === 'VP8X' && (bytes[20] & 0x02) !== 0
}
