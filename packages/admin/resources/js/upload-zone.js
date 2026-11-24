import { shrinkImage } from './image-shrink.js'

/**
 * An upload zone (the media module's media-upload view): files chosen or dropped are scaled down
 * when the zone says so (data-shrink-*), then uploaded to the Livewire property data-upload-model
 * names. A zone for one file takes the first. The property is a list either way.
 */
export function lynxUploadZone() {
    return {
        dragging: false,
        busy: false,

        drop(event) {
            this.dragging = false
            this.upload([...event.dataTransfer.files])
        },

        choose(event) {
            const files = [...event.target.files]
            // Choosing the same file again must fire change again.
            event.target.value = ''
            this.upload(files)
        },

        async upload(files) {
            const dataset = this.$root.dataset
            const chosen = dataset.uploadMultiple === 'true' ? files : files.slice(0, 1)

            if (chosen.length === 0 || this.busy) {
                return
            }

            this.busy = true

            try {
                const limits = this.limits(dataset)
                const ready = limits === null ? chosen : await Promise.all(chosen.map((file) => shrinkImage(file, limits)))
                const done = () => { this.busy = false }
                this.$wire.uploadMultiple(dataset.uploadModel, ready, done, done)
            } catch (error) {
                this.busy = false
                throw error
            }
        },

        limits(dataset) {
            if (dataset.shrinkMaxWidth === undefined) {
                return null
            }

            return {
                maxWidth: Number(dataset.shrinkMaxWidth),
                maxHeight: Number(dataset.shrinkMaxHeight),
                quality: Number(dataset.shrinkQuality),
            }
        },
    }
}
