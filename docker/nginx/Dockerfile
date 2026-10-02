FROM node:22-alpine AS frontend-build

WORKDIR /frontend
ENV SCARF_ANALYTICS=false
COPY frontend/package*.json ./
RUN npm ci
COPY frontend/ ./
RUN npm run build

FROM frontend-build AS frontend-test
COPY openapi.yaml /openapi.yaml
COPY redocly.yaml /redocly.yaml
RUN npm run test
RUN npm run format:check
RUN REDOCLY_TELEMETRY=off REDOCLY_SUPPRESS_UPDATE_NOTICE=true npm run openapi:lint

FROM nginx:1.27-alpine

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY web/index.php /app/web/index.php
COPY web/docs/index.html /app/web/docs/index.html
COPY openapi.yaml /app/web/openapi.yaml
COPY --from=frontend-build /frontend/node_modules/swagger-ui-dist/swagger-ui.css /app/web/docs/swagger-ui.css
COPY --from=frontend-build /frontend/node_modules/swagger-ui-dist/swagger-ui-bundle.js /app/web/docs/swagger-ui-bundle.js
COPY --from=frontend-build /frontend/dist/ /app/web/app/
