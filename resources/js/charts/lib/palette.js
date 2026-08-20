const ACCENT = '#f97316';
const ZINC_MAX_OPACITY = 1.0;
const ZINC_MIN_OPACITY = 0.15;

export function rankedMonochromeFill(index, total, { isDark = false } = {}) {
    if (index === 0 || total <= 1) {
        return ACCENT;
    }
    const t = (index - 1) / Math.max(total - 2, 1);
    const opacity = ZINC_MAX_OPACITY - t * (ZINC_MAX_OPACITY - ZINC_MIN_OPACITY);
    const base = isDark ? '255,255,255' : '9,9,11';
    return `rgba(${base},${opacity.toFixed(2)})`;
}
