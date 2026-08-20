import { formatNumber } from '../lib/formatters.js';

export default function ChartTooltip({ active, payload, label, theme = 'light' }) {
    if (!active || !payload || payload.length === 0) {
        return null;
    }
    const isDark = theme === 'dark';
    return (
        <div
            className={`rounded-[8px] px-3 py-2 text-sm shadow-lg backdrop-blur-md border ${
                isDark ? 'bg-[#181818]/90 border-white/10 text-white' : 'bg-white/95 border-gray-200 text-gray-900'
            }`}
        >
            {label ? <div className="mb-1.5 border-b pb-1 font-medium border-inherit">{label}</div> : null}
            {payload.map((item, idx) => (
                <div key={idx} className="flex items-center justify-between gap-3">
                    <span
                        style={{ backgroundColor: item.color ?? item.fill }}
                        className="h-2 w-2 rounded-full ring-1 ring-white/20"
                    />
                    <span>{item.name ?? item.dataKey}:</span>
                    <span className="font-semibold tabular-nums">{formatNumber(item.value)}</span>
                </div>
            ))}
        </div>
    );
}
