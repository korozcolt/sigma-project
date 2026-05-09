FROM mcr.microsoft.com/playwright/python:v1.50.0-noble

# Noble = Ubuntu 24.04 — Playwright image includes all Chromium deps + Xvfb
# headless=False works via Xvfb virtual display (gov site blocks headless Chromium)

WORKDIR /app
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY app.py .

EXPOSE 5757

CMD Xvfb :99 -screen 0 1280x800x24 -ac +extension GLX +render -noreset & \
    sleep 1 && \
    DISPLAY=:99 python3 app.py
