FROM node:20-alpine

RUN apk add --no-cache \
    bash git \
    chromium \
    nss freetype freetype-dev harfbuzz ca-certificates ttf-freefont \
    udev ttf-liberation

ENV PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1 \
    PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH=/usr/bin/chromium-browser

WORKDIR /app

COPY frontend/package*.json ./
RUN if [ -f package-lock.json ]; then npm ci; else npm install; fi

EXPOSE 5173
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]
