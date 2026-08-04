FROM mcr.microsoft.com/playwright/python:v1.50.0-noble

# Noble = Ubuntu 24.04 with Playwright + Chromium preinstalled
# No Xvfb needed — headless=True works for browser fetch() calls

WORKDIR /app
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# tini as PID 1 reaps orphaned Chromium subprocesses (headless_shell zombies) —
# without an init process, python3 as PID 1 never collects them (root cause of
# ~14,485 accumulated defunct processes found in production 2026-08-04).
RUN apt-get update && apt-get install -y --no-install-recommends tini && rm -rf /var/lib/apt/lists/*

COPY app.py .

EXPOSE 5757

ENTRYPOINT ["/usr/bin/tini", "--"]
CMD ["python3", "app.py"]
