import { CartesianGrid, Line, LineChart as RLineChart, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { toSeriesRows } from '../lib/chartjs-adapter.js';
import { rankedMonochromeFill } from '../lib/palette.js';
import ChartTooltip from './ChartTooltip.jsx';

export default function LineChart({ data, theme = 'light' }) {
    const datasets = data?.datasets ?? [];
    const rows = toSeriesRows(data ?? {});
    const gridStroke = theme === 'dark' ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';
    const accentKey = datasets[datasets.length - 1]?.label;

    return (
        <ResponsiveContainer width="100%" height={240}>
            <RLineChart data={rows} margin={{ top: 12, right: 12, left: -22, bottom: 0 }}>
                <CartesianGrid vertical={false} stroke={gridStroke} />
                <XAxis dataKey="label" tickLine={false} axisLine={false} tick={{ fontSize: 12 }} />
                <YAxis tickLine={false} axisLine={false} tick={{ fontSize: 12 }} allowDecimals={false} />
                <Tooltip content={<ChartTooltip theme={theme} />} />
                <Legend wrapperStyle={{ fontSize: 14 }} />
                {datasets.map((ds, i) => (
                    <Line
                        key={ds.label ?? i}
                        type="monotone"
                        dataKey={ds.label ?? `series_${i}`}
                        stroke={ds.label === accentKey ? '#f97316' : rankedMonochromeFill(1, 2, { isDark: theme === 'dark' })}
                        strokeWidth={2}
                        strokeLinecap="round"
                        dot={false}
                        activeDot={{ r: 4 }}
                    />
                ))}
            </RLineChart>
        </ResponsiveContainer>
    );
}
