import { Bar, BarChart as RBarChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { toOrderedRows } from '../lib/chartjs-adapter.js';
import ChartTooltip from './ChartTooltip.jsx';

const BAR_FILL = { light: 'rgba(9,9,11,0.75)', dark: 'rgba(255,255,255,0.75)' };

export default function HistogramChart({ data, theme = 'light' }) {
    const rows = toOrderedRows(data ?? {});

    return (
        <ResponsiveContainer width="100%" height={240}>
            <RBarChart data={rows} margin={{ top: 12, right: 12, left: -22, bottom: 0 }}>
                <XAxis dataKey="name" tickLine={false} axisLine={false} tick={{ fontSize: 12 }} />
                <YAxis tickLine={false} axisLine={false} tick={{ fontSize: 12 }} allowDecimals={false} />
                <Tooltip content={<ChartTooltip theme={theme} />} cursor={{ fill: 'rgba(0,0,0,0.03)' }} />
                <Bar dataKey="value" radius={[8, 8, 0, 0]} fill={theme === 'dark' ? BAR_FILL.dark : BAR_FILL.light} />
            </RBarChart>
        </ResponsiveContainer>
    );
}
