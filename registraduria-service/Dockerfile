FROM python:3.11-slim

# System deps for Playwright Chromium + Xvfb (virtual display)
RUN apt-get update && apt-get install -y \
    xvfb \
    x11-utils \
    wget \
    curl \
    gnupg \
    ca-certificates \
    fonts-liberation \
    libatk-bridge2.0-0 \
    libatk1.0-0 \
    libcups2 \
    libdbus-1-3 \
    libgdk-pixbuf2.0-0 \
    libnspr4 \
    libnss3 \
    libxcomposite1 \
    libxdamage1 \
    libxrandr2 \
    libgbm1 \
    libxss1 \
    libasound2 \
    libpango-1.0-0 \
    libpangocairo-1.0-0 \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt
RUN playwright install chromium --with-deps

COPY app.py .

EXPOSE 5757

# Xvfb creates a virtual display so headless=False works on headless server
# Gov site (Registraduria) blocks pure headless Chromium at TCP level
CMD Xvfb :99 -screen 0 1280x800x24 -ac +extension GLX +render -noreset & \
    sleep 1 && \
    DISPLAY=:99 python3 app.py
