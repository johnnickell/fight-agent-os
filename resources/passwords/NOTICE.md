# Common-password data attribution and license review

`common.sha256` is adapted from **Probable-Wordlists**, by **berzerk0**:
[Top12Thousand-probable-v2.txt](https://github.com/berzerk0/Probable-Wordlists/blob/2df55facf06c7742f2038a8f6607ea9071596128/Real-Passwords/Top12Thousand-probable-v2.txt).
The upstream [license declaration](https://github.com/berzerk0/Probable-Wordlists/blob/2df55facf06c7742f2038a8f6607ea9071596128/README.md#disclaimer-and-license)
explicitly licenses the work under **Creative Commons Attribution-ShareAlike 4.0 International**.

Changes by Fight Agent OS contributors (2026): ASCII case-folding, SHA-256 transformation, deduplication and
sorting. The resulting data is distributed under **CC BY-SA 4.0**, not the repository's default MIT license.
The full [license text](CC-BY-SA-4.0.txt) accompanies it. Retain this attribution, the license link/text, upstream
source link and modification notice when redistributing the data or an adaptation. No endorsement is implied.
Application code and the transformation script remain under the repository's MIT license; the data is a separately
identified work, not incorporated into those source files.

## Selection review — 2026-10-15

The pinned project's explicit CC BY-SA 4.0 declaration permits copying and adaptation with attribution and
ShareAlike. This distribution retains attribution and the complete license, identifies modifications and licenses
the derived data under that same license. The upstream disclaimer describes lawful, ethical and educational use;
local defensive rejection of insecure passwords is consistent with that stated purpose. No account identifiers,
user/password pairs or plaintext password list are distributed here.

This is a bounded engineering license review, not legal advice or independent verification of every historical
breach source's chain of title. The author states that the underlying lines were obtained through freely available
means; availability alone is not an independent copyright grant from those historical sources. We rely on the
selected project's explicit license for its published compilation. A distributor requiring stronger provenance
assurance needs a legal/provenance review before deployment; no such assurance is claimed.

The [manifest](manifest.json) pins the actual source, license declaration, retrieval details, transformation and
output checksums/counts. See [password semantics and reproduction](../../docs/engineering/PASSWORDS.md).
