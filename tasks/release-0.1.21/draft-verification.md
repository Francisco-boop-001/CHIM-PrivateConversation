# Private Conversation 0.1.21 BETA draft verification

## Tag and draft metadata

- Remote annotated tag: `private_conversation-v0.1.21`
- Remote tag-object SHA: `e056638a9a083c91634da7619e469645cf2d09bc`
- Peeled commit: `f1e1b4b410bca981afb1f7e95de7ea84f9dd2981`
- GitHub draft ID: `RE_kwDOU2JT984YzCxj`
- Title: `Private Conversation 0.1.21 — BETA`
- Tag name: `private_conversation-v0.1.21`
- `isDraft`: `true`; `isPrerelease`: `false`
- Draft URL: <https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/tag/untagged-868adb4e96052d06cde8>
- GitHub's returned draft body matches `tasks/release-0.1.21/release-body.md` after normalizing only terminal newlines. Its internal links point to files at the version tag.

The annotated tag was absent on `origin` before the push. It was pushed without force using an explicit single-tag refspec and `--no-follow-tags`. A fresh `ls-remote` returned the expected tag-object SHA and peeled commit. No branch ref was pushed.

## Uploaded assets and fresh-download comparison

The draft contains exactly these five uploaded assets. Each was freshly downloaded from the draft into ignored `dist/release-v0.1.21-draft-downloads/`, then compared byte-for-byte with the clean-tag build under `dist/release-v0.1.21-clean-source/dist/release-v0.1.21-clean/`. The downloaded byte length and SHA-256 also match the GitHub asset metadata.

| Asset | Bytes | SHA-256 | Clean-build bytes match | GitHub size/digest match |
| --- | ---: | --- | --- | --- |
| `SHA256SUMS.txt` | 381 | `d69825f0173ca5a87bb1ea8ba636b294455b9c915f329f678f5cb34e0f414697` | yes | yes |
| `chim-plugin.tar.gz` | 3053489 | `d2f8dfde43ab0cef03aeeb7a06b110c18378aa6f7e322d64975d6d4f5e9a5b55` | yes | yes |
| `private_conversation-0.1.21-mo2.zip` | 3056680 | `8480d15479d0371a7e78979c809d698abf1fab35efb4ef98071ab9f9c811db5e` | yes | yes |
| `private_conversation-0.1.21.dwpkg` | 3475089 | `0dd26c6387dcb880caadd410580d63945412f04ce3178505df136fce242eda94` | yes | yes |
| `private_conversation.tar.gz` | 3053560 | `74629c0faa167143f4de7947862326a8465a9fc6058652dd07effdbd29943fe9` | yes | yes |

The fresh download contained exactly the five expected filenames; all five passed byte equality, size, and API digest checks. The release remains a draft and is not published. Canonical `main`, the PCV hub, and the 0.1.20 tag/release/assets were not changed. No generated archives or downloaded binaries are included in Git.
