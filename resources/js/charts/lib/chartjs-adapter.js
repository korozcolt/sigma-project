export function toSeriesRows({ labels = [], datasets = [] }) {
    return labels.map((label, i) => ({
        label,
        ...Object.fromEntries(datasets.map((ds) => [ds.label ?? `series_${datasets.indexOf(ds)}`, ds.data?.[i] ?? null])),
    }));
}

export function toNameValueRows({ labels = [], datasets = [] }) {
    const data = datasets[0]?.data ?? [];
    return labels
        .map((name, i) => ({ name, value: data[i] ?? 0 }))
        .sort((a, b) => b.value - a.value);
}

export function toOrderedRows({ labels = [], datasets = [] }) {
    const data = datasets[0]?.data ?? [];
    return labels.map((name, i) => ({ name, value: data[i] ?? 0 }));
}

export function isChartDataEmpty(kind, data) {
    if (kind === 'sparkline' || kind === 'poc') {
        return !Array.isArray(data?.points) || data.points.length === 0;
    }
    const labels = data?.labels ?? [];
    const datasets = data?.datasets ?? [];
    if (labels.length === 0 || datasets.length === 0) {
        return true;
    }
    return datasets.every((ds) => !Array.isArray(ds.data) || ds.data.every((v) => v === null || v === undefined));
}
