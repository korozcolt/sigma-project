export function formatNumber(value) {
    const n = Number(value);
    return Number.isFinite(n) ? n.toLocaleString('es-CO') : String(value ?? '');
}
