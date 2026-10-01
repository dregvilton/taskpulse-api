FROM node:22-alpine AS frontend-build

WORKDIR /frontend
COPY frontend/package*.json ./
RUN npm ci
COPY frontend/ ./
RUN npm run build

FROM frontend-build AS frontend-test
RUN npm run test
RUN npm run format:check

FROM nginx:1.27-alpine

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY web/index.php /app/web/index.php
COPY --from=frontend-build /frontend/dist/ /app/web/app/
