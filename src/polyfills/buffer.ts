import { Buffer } from 'buffer'

if (globalThis.Buffer === undefined) {
	globalThis.Buffer = Buffer
}
