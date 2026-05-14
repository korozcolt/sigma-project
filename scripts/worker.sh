#!/bin/bash
set -e

echo "[worker] Iniciando queue worker..."
php artisan queue:work redis \
    --tries=3 \
    --sleep=3 \
    --timeout=90 \
    --max-jobs=500 &
WORKER_PID=$!

echo "[scheduler] Iniciando scheduler loop..."
while true; do
    php artisan schedule:run --no-interaction >> /dev/null 2>&1
    sleep 60
done &
SCHEDULER_PID=$!

echo "[worker] PID=$WORKER_PID | [scheduler] PID=$SCHEDULER_PID"

# Si cualquiera de los dos muere, el contenedor termina (Dokploy lo reinicia)
wait -n $WORKER_PID $SCHEDULER_PID
EXIT_CODE=$?
echo "[worker.sh] Proceso terminado con código $EXIT_CODE — reiniciando contenedor"
exit $EXIT_CODE
