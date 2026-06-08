# syntax=docker/dockerfile:1
FROM node:20-alpine AS build

WORKDIR /app
ENV NODE_ENV=production \
    PORT=3000

COPY package*.json ./
RUN npm ci --omit=dev

COPY . .
RUN npm run build

FROM nginx:alpine
LABEL maintainer="ada@example.com"
COPY --from=build /app/dist /usr/share/nginx/html
EXPOSE 80
HEALTHCHECK --interval=30s CMD wget -qO- http://localhost/ || exit 1
ENTRYPOINT ["nginx", "-g", "daemon off;"]
