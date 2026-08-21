import { Cell, Funnel, FunnelChart as RFunnelChart, LabelList, ResponsiveContainer, Tooltip } from 'recharts';
import { toOrderedRows } from '../lib/chartjs-adapter.js';
import { rankedMonochromeFill } from '../lib/palette.js';
import ChartTooltip from './ChartTooltip.jsx';

export default function FunnelChart({ data, theme = 'light' }) {
    const rows = toOrderedRows(data ?? {});

    return (
        <ResponsiveContainer width="100%" height={280}>
            <RFunnelChart>
                <Tooltip content={<ChartTooltip theme={theme} />} />
                <Funnel dataKey="value" nameKey="name" data={rows} isAnimationActive>
                    <LabelList position="right" dataKey="name" fill={theme === 'dark' ? '#fff' : '#111'} stroke="none" />
                    {rows.map((row, i) => (
                        <Cell key={row.name} fill={rankedMonochromeFill(i, rows.length, { isDark: theme === 'dark' })} />
                    ))}
                </Funnel>
            </RFunnelChart>
        </ResponsiveContainer>
    );
}
