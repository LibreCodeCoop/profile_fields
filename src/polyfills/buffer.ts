// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
//
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Buffer } from 'buffer'

if (globalThis.Buffer === undefined) {
	globalThis.Buffer = Buffer
}
