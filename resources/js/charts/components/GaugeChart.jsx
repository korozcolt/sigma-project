import { Cell, Pie, PieChart as RPieChart, ResponsiveContainer } from 'recharts';
import { formatNumber } from '../lib/formatters.js';

export default function GaugeChart({ data, theme = 'light' }) {
    const value = data?.datasets?.[0]?.data?.[0] ?? 0;
    const min = data?.min ?? 0;
    const max = data?.max ?? 5;
    const pct = max > min ? Math.max(0, Math.min(1, (value - min) / (max - min))) : 0;
    const rows = [
        { name: 'filled', value: pct * 100 },
        { name: 'track', value: 100 - pct * 100 },
    ];
    const trackColor = theme === 'dark' ? 'rgba(255,255,255,0.12)' : 'rgba(9,9,11,0.08)';

    return (
        <div className="relative" style={{ width: '100%', height: 180 }}>
            <ResponsiveContainer>
                <RPieChart>
                    <Pie data={rows} dataKey="value" startAngle={210} endAngle={-30} innerRadius={70} outerRadius={95} cornerRadius={8}>
                        <Cell fill="#f97316" />
                        <Cell fill={trackColor} />
                    </Pie>
                </RPieChart>
            </ResponsiveContainer>
            <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-end pb-2 text-center">
                <span className="text-2xl font-semibold tabular-nums">{formatNumber(value)}</span>
                <span className="text-xs opacity-60">{min}–{max}</span>
            </div>
        </div>
    );
}
