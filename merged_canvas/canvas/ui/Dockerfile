FROM node:20-alpine AS builder
WORKDIR /app

COPY . .
RUN npm ci
RUN npm run build

FROM scratch
COPY --from=builder /app/dist /
