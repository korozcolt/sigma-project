import { Area, AreaChart as RAreaChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { toSeriesRows } from '../lib/chartjs-adapter.js';
import { rankedMonochromeFill } from '../lib/palette.js';
import ChartTooltip from './ChartTooltip.jsx';

export default function StackedAreaChart({ data, theme = 'light' }) {
    const rows = toSeriesRows(data ?? {});
    const seriesKeys = (data?.datasets ?? []).map((ds) => ds.label);

    return (
        <ResponsiveContainer width="100%" height={280}>
            <RAreaChart data={rows} margin={{ top: 12, right: 12, left: -22, bottom: 0 }}>
                <XAxis dataKey="label" tickLine={false} axisLine={false} tick={{ fontSize: 12 }} />
                <YAxis tickLine={false} axisLine={false} tick={{ fontSize: 12 }} allowDecimals={false} />
                <Tooltip content={<ChartTooltip theme={theme} />} />
                {seriesKeys.map((key, i) => (
                    <Area
                        key={key}
                        type="monotone"
                        dataKey={key}
                        stackId="rejections"
                        stroke="none"
                        fill={rankedMonochromeFill(i, seriesKeys.length, { isDark: theme === 'dark' })}
                    />
                ))}
            </RAreaChart>
        </ResponsiveContainer>
    );
}
