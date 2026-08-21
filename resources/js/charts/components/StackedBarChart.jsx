import { Bar, BarChart as RBarChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { toSeriesRows } from '../lib/chartjs-adapter.js';
import { rankedMonochromeFill } from '../lib/palette.js';
import ChartTooltip from './ChartTooltip.jsx';

export default function StackedBarChart({ data, theme = 'light' }) {
    const rows = toSeriesRows(data ?? {});
    const seriesKeys = (data?.datasets ?? []).map((ds) => ds.label);
    const minWidth = Math.max(rows.length * 56, 100);

    return (
        <div className="w-full overflow-x-auto">
            <div style={{ minWidth: `${minWidth}px` }}>
                <ResponsiveContainer width="100%" height={280}>
                    <RBarChart data={rows} margin={{ top: 12, right: 12, left: -22, bottom: 0 }}>
                        <XAxis dataKey="label" tickLine={false} axisLine={false} tick={{ fontSize: 12 }} interval={0} angle={-20} textAnchor="end" height={60} />
                        <YAxis tickLine={false} axisLine={false} tick={{ fontSize: 12 }} allowDecimals={false} />
                        <Tooltip content={<ChartTooltip theme={theme} />} cursor={{ fill: 'rgba(0,0,0,0.03)' }} />
                        {seriesKeys.map((key, i) => (
                            <Bar
                                key={key}
                                dataKey={key}
                                stackId="apoyos"
                                fill={rankedMonochromeFill(i, seriesKeys.length, { isDark: theme === 'dark' })}
                                radius={i === seriesKeys.length - 1 ? [8, 8, 0, 0] : [0, 0, 0, 0]}
                            />
                        ))}
                    </RBarChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}
