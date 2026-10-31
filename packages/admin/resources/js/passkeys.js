/**
 * Passkeys in the admin (ADR-0041): Alpine components around @laravel/passkeys, which talks to
 * Fortify's passkey routes under the admin prefix.
 */
import { Passkeys } from '@laravel/passkeys'

const CANCELLED = 'UserCancelledError'

/** The error to show, or null when the person cancelled the browser's dialog. */
function messageOf(error, fallback) {
    return error?.constructor?.name === CANCELLED || error?.name === CANCELLED ? null : (error?.message || fallback)
}

/** Account security: name and register a passkey, then refresh the Livewire list. */
export function lynxPasskeyRegister(routes, fallback) {
    return {
        supported: Passkeys.isSupported(),
        name: '',
        loading: false,
        error: null,
        async register() {
            this.loading = true
            this.error = null
            try {
                await Passkeys.register({ name: this.name.trim(), routes })
                this.name = ''
                await this.$wire.passkeyAdded()
            } catch (error) {
                this.error = messageOf(error, fallback)
            } finally {
                this.loading = false
            }
        },
    }
}

/** Sign-in screen: sign in with a passkey instead of the password. */
export function lynxPasskeyVerify(routes, fallback) {
    return {
        supported: Passkeys.isSupported(),
        loading: false,
        error: null,
        async verify() {
            this.loading = true
            this.error = null
            try {
                const response = await Passkeys.verify({ routes })
                window.location.assign(response?.redirect || '/')
            } catch (error) {
                this.error = messageOf(error, fallback)
            } finally {
                this.loading = false
            }
        },
    }
}
