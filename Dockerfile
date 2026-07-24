# ---- 1-bosqich: frontend build ----
FROM node:22-alpine AS frontend
WORKDIR /fe
COPY frontend/package*.json ./
RUN npm ci
COPY frontend/ ./
# Taklif havolasi uchun bot username (deploy'da bering)
ARG VITE_BOT_USERNAME=YourBot
ENV VITE_BOT_USERNAME=$VITE_BOT_USERNAME
RUN npm run build

# ---- 2-bosqich: backend runtime (frontend'ni ham beradi) ----
FROM python:3.13-slim AS runtime
WORKDIR /app
ENV PYTHONUNBUFFERED=1 PYTHONUTF8=1
COPY backend/requirements.txt ./
RUN pip install --no-cache-dir -r requirements.txt
COPY backend/ ./
COPY --from=frontend /fe/dist ./frontend_dist
ENV FRONTEND_DIST=/app/frontend_dist
EXPOSE 8000
# Railway/Render $PORT beradi
CMD ["sh", "-c", "python -m uvicorn app.main:app --host 0.0.0.0 --port ${PORT:-8000}"]
