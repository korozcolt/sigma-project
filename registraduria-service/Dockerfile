FROM mcr.microsoft.com/playwright/python:v1.50.0-noble

# Noble = Ubuntu 24.04 with Playwright + Chromium preinstalled
# No Xvfb needed — headless=True works for browser fetch() calls

WORKDIR /app
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY app.py .

EXPOSE 5757

CMD ["python3", "app.py"]
