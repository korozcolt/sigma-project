import { Bar, BarChart as RBarChart, Cell, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { toNameValueRows } from '../lib/chartjs-adapter.js';
import { rankedMonochromeFill } from '../lib/palette.js';
import ChartTooltip from './ChartTooltip.jsx';

export default function BarChart({ data, theme = 'light' }) {
    const rows = toNameValueRows(data ?? {});
    return (
        <ResponsiveContainer width="100%" height={240}>
            <RBarChart data={rows} margin={{ top: 12, right: 12, left: -22, bottom: 0 }}>
                <XAxis dataKey="name" tickLine={false} axisLine={false} tick={{ fontSize: 12 }} interval={0} angle={-20} textAnchor="end" height={50} />
                <YAxis tickLine={false} axisLine={false} tick={{ fontSize: 12 }} allowDecimals={false} />
                <Tooltip content={<ChartTooltip theme={theme} />} cursor={{ fill: 'rgba(0,0,0,0.03)' }} />
                <Bar dataKey="value" radius={[8, 8, 0, 0]}>
                    {rows.map((row, i) => (
                        <Cell key={row.name} fill={rankedMonochromeFill(i, rows.length, { isDark: theme === 'dark' })} />
                    ))}
                </Bar>
            </RBarChart>
        </ResponsiveContainer>
    );
}
