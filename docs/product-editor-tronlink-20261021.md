# Product editor blank visual field — 2026-10-21

J02/J03 (domestic product 96; local 93) had a nonempty saved short description,
but Chrome's TinyMCE visual iframe was blank. Core media/TinyMCE raised
`TypeError: _.pluck is not a function`.

Temporary, query-gated script diagnostics identified an assignment to `window._`
from `chrome-extension://ibnejdfjmmkpcnlpebklmnkoeoihofec/injected/injected.js`.
The installed manifest identifies this as TronLink 4.11.1. It replaces Underscore
1.13.8 with Lodash 4.18.1 before the editor initializes. WordPress's later Lodash
noConflict call restores the extension's Lodash, not Underscore.

Server and local Underscore, Lodash and jquery.query files matched by SHA-256.
The core libraries also passed an isolated Node test of their normal noConflict
sequence. No WordPress/WooCommerce core patch or product-content overwrite was
needed. The temporary diagnostics were removed and the normal theme redeployed
using the existing domestic sync script, which runs composer check.

The editor's Code tab displayed the saved short description intact. Resolving
Visual mode requires disabling TronLink on this site (or using a browser profile
without it). No browser-extension permission settings were changed automatically.
No product publish/save action, inventory change or international deployment was
performed during this investigation.
