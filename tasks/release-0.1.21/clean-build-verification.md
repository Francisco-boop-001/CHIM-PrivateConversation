# Private Conversation 0.1.21 clean-tag build verification

This receipt records a clean export and rebuild of the locally tagged release candidate. It does not record a push, upload, draft, public release, main-branch advancement, install, live server test, or gameplay run.

## Source identity

- Annotated tag: `private_conversation-v0.1.21`
- Annotated tag object: `e056638a9a083c91634da7619e469645cf2d09bc`
- Peeled commit: `f1e1b4b410bca981afb1f7e95de7ea84f9dd2981`
- Commit tree: `30ea1280bcd8beecfee5bb2ad16c99e21a96da0c`
- Clean source export: `dist/release-v0.1.21-clean-source.zip`, created with `git archive` from the version tag and extracted to the ignored `dist/release-v0.1.21-clean-source/` directory.

The archive was created with `git archive --format=zip --output=dist/release-v0.1.21-clean-source.zip refs/tags/private_conversation-v0.1.21`. The committed `.gitattributes` sets `text eol=lf` for source and documentation extensions, but defines no `export-ignore` or `export-subst`; `working-tree-encoding` is unspecified. Git archive reads committed blobs and does not apply checkout line-ending conversion. A focused comparison found zero mismatches between the raw tagged blobs and all 21 `server/` files in both the extracted export and the candidate DWPkg. This check covers packaged server files, not every file in the source archive.

## Build and byte comparison

The existing host builder ran from the extracted clean source using Python 3.12.4:

```text
py -3 scripts/build-package.py --format release --release-dir dist/release-v0.1.21-clean
```

The builder verified all five artifacts. Each clean-build artifact was then compared byte-for-byte with the previously built candidate artifact; all five were identical:

| Artifact | Bytes | SHA-256 | Candidate equality |
| --- | ---: | --- | --- |
| `SHA256SUMS.txt` | 381 | `d69825f0173ca5a87bb1ea8ba636b294455b9c915f329f678f5cb34e0f414697` | identical |
| `chim-plugin.tar.gz` | 3053489 | `d2f8dfde43ab0cef03aeeb7a06b110c18378aa6f7e322d64975d6d4f5e9a5b55` | identical |
| `private_conversation-0.1.21-mo2.zip` | 3056680 | `8480d15479d0371a7e78979c809d698abf1fab35efb4ef98071ab9f9c811db5e` | identical |
| `private_conversation-0.1.21.dwpkg` | 3475089 | `0dd26c6387dcb880caadd410580d63945412f04ce3178505df136fce242eda94` | identical |
| `private_conversation.tar.gz` | 3053560 | `74629c0faa167143f4de7947862326a8465a9fc6058652dd07effdbd29943fe9` | identical |

The source export and generated packages are under ignored `dist/` and are not part of the release commit. This receipt is a later documentation-only follow-up; it is not in the tagged commit.
