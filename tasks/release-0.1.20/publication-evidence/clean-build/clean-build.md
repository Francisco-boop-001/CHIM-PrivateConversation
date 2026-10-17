PCV 0.1.20 clean-source build receipt

Accepted source commit: 85e9dd08c8bacbb6f1553e36b0d1f4c2caacf45e
Accepted source tree: fd1a2ff48cfa65dbb5c4500a7fb364975e48fdcd
Clean source root: K:\ActorwrightExchange\projects\CHIM-PrivateConversation-dev\tasks\release-0.1.20\publication-evidence\clean-build\source-85e9dd0
Git archive command: git -c safe.directory="K:\ActorwrightExchange\projects\CHIM-PrivateConversation-dev" -C "K:\ActorwrightExchange\projects\CHIM-PrivateConversation-dev" archive --format=tar --output="K:\ActorwrightExchange\projects\CHIM-PrivateConversation-dev\tasks\release-0.1.20\publication-evidence\clean-build\source-85e9dd0.tar" 85e9dd08c8bacbb6f1553e36b0d1f4c2caacf45e
Git archive exit: 0; size 9195520 bytes; SHA-256 f0024a807312d95ad433593327a6581a7028a8b5f1d0ec97770ceb584e01a38d
Extraction command: py -3 -c "import sys, tarfile; tarfile.open(sys.argv[1], 'r:').extractall(sys.argv[2], filter='data')" "K:\ActorwrightExchange\projects\CHIM-PrivateConversation-dev\tasks\release-0.1.20\publication-evidence\clean-build\source-85e9dd0.tar" "K:\ActorwrightExchange\projects\CHIM-PrivateConversation-dev\tasks\release-0.1.20\publication-evidence\clean-build\source-85e9dd0"
Extraction exit: 0; Python 3.12.4 tarfile data filter; no .git directory extracted.
Clean build command: py -3 scripts/build-package.py --format release --release-dir "K:\ActorwrightExchange\projects\CHIM-PrivateConversation-dev\tasks\release-0.1.20\publication-evidence\clean-build\source-85e9dd0\tasks\release-0.1.20\publication-evidence\clean-build\assets"
Build working directory: K:\ActorwrightExchange\projects\CHIM-PrivateConversation-dev\tasks\release-0.1.20\publication-evidence\clean-build\source-85e9dd0
Build exit: 0; stdout/stderr in build-success.stdout.txt / build-success.stderr.txt
Candidate assets: K:\ActorwrightExchange\projects\CHIM-PrivateConversation-dev\tasks\release-0.1.20\package-evidence\candidate
Clean assets: K:\ActorwrightExchange\projects\CHIM-PrivateConversation-dev\tasks\release-0.1.20\publication-evidence\clean-build\source-85e9dd0\tasks\release-0.1.20\publication-evidence\clean-build\assets

Prior harness attempts preserved: archive.stdout.txt/stderr.txt records an empty-argument Git invocation (exit 1); build.stdout.txt/stderr.txt records the builder refusal for an output path outside the project (exit 1). Neither attempt produced an archive or package assets.

| Asset | Exact byte compare | Sizes | SHA-256 identical | Candidate SHA-256 | Clean SHA-256 |
|---|---:|---:|---:|---|---|
| SHA256SUMS.txt | True (exit 0) | 381 / 381 | True | e51d7d230b586ad3f544084b60ad40e4a7fe4fc830d0e35b12061977ad056fee | e51d7d230b586ad3f544084b60ad40e4a7fe4fc830d0e35b12061977ad056fee |
| chim-plugin.tar.gz | True (exit 0) | 3053486 / 3053486 | True | 50405eca2d8a7b6e7dc9a307740b6ab0518366e21cfcbc743b88e88d94cbcd7b | 50405eca2d8a7b6e7dc9a307740b6ab0518366e21cfcbc743b88e88d94cbcd7b |
| private_conversation-0.1.20-mo2.zip | True (exit 0) | 3056680 / 3056680 | True | 7bd8c4e67e970416c116965a0470328a48e7ca4921750fafabf759a2826290db | 7bd8c4e67e970416c116965a0470328a48e7ca4921750fafabf759a2826290db |
| private_conversation-0.1.20.dwpkg | True (exit 0) | 3475090 / 3475090 | True | d5da301dd332d45a97e962da96c8f0af9269d4d402e2083fe7a221e921090e8c | d5da301dd332d45a97e962da96c8f0af9269d4d402e2083fe7a221e921090e8c |
| private_conversation.tar.gz | True (exit 0) | 3053559 / 3053559 | True | e949fe37d1e7fb2f8091e28f0c4af106ca1039a15809401082ea460572798bb8 | e949fe37d1e7fb2f8091e28f0c4af106ca1039a15809401082ea460572798bb8 |

All five assets byte-identical to the frozen working-tree candidate: True
Generated archive, clean source export, and rebuilt assets are task-owned untracked evidence; none was staged.
