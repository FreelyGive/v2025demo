#
# This file is copied from https://github.com/splitsh/lite/pull/75, with
# two changes to the runtime image: adding Git, and basing it on `alpine`
# so that it has a basic shell and can be used in GitLab CI.
#
FROM golang:1.22-bookworm AS builder

WORKDIR /build

RUN apt update && apt install -y pkg-config cmake

# Cache modules and git2go build
COPY go.mod go.sum ./
RUN go mod download

# Build git2go
RUN git clone https://github.com/libgit2/git2go vendor/github.com/libgit2/git2go/v34
RUN cd vendor/github.com/libgit2/git2go/v34 && git checkout v34.0.0 && git submodule update --init && make install-static
RUN mv vendor/github.com/libgit2/git2go/v34 git2go

# Copy the code
COPY .git main.go ./
COPY splitter splitter/
RUN go mod vendor
RUN rm -rf vendor/github.com/libgit2/git2go/v34
RUN mv git2go vendor/github.com/libgit2/git2go/v34

# Build
RUN go build -tags static -ldflags="-s -w -X 'main.version=$(git describe --tags)'" -o splitsh-lite ./main.go

# Prepare files for the final image
WORKDIR /dist
RUN cp /build/splitsh-lite ./splitsh-lite

# Add dependent libraries
RUN ldd splitsh-lite | tr -s '[:blank:]' '\n' | grep '^/' | xargs -I % sh -c 'mkdir -p $(dirname ./%); cp % ./%;'

# Create the runtime image
FROM alpine:latest

RUN apk add git openssh
COPY --from=builder /dist /
WORKDIR /data
ENTRYPOINT ["/splitsh-lite"]
