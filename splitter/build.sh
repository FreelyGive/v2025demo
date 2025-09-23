#!/usr/bin/env sh

set -e
test -d source || git clone --depth=1 https://github.com/splitsh/lite.git source
cp -f Dockerfile source
docker buildx build --platform=linux/arm64/v8,linux/amd64 source
