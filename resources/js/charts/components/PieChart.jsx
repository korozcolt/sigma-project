import { Cell, Pie, PieChart as RPieChart, ResponsiveContainer, Tooltip } from 'recharts';
import { toNameValueRows } from '../lib/chartjs-adapter.js';
import { rankedMonochromeFill } from '../lib/palette.js';
import { formatNumber } from '../lib/formatters.js';
import ChartTooltip from './ChartTooltip.jsx';

export default function PieChart({ data, theme = 'light' }) {
    const rows = toNameValueRows(data ?? {});
    const total = rows.reduce((sum, r) => sum + r.value, 0);
    return (
        <div className="relative" style={{ width: '100%', height: 240 }}>
            <ResponsiveContainer>
                <RPieChart>
                    <Pie data={rows} dataKey="value" nameKey="name" innerRadius={60} outerRadius={90} paddingAngle={4} cornerRadius={8}>
                        {rows.map((row, i) => (
                            <Cell
                                key={row.name}
                                fill={rankedMonochromeFill(i, rows.length, { isDark: theme === 'dark' })}
                                stroke={theme === 'dark' ? '#0b0b0c' : '#ffffff'}
                                strokeWidth={2}
                            />
                        ))}
                    </Pie>
                    <Tooltip content={<ChartTooltip theme={theme} />} />
                </RPieChart>
            </ResponsiveContainer>
            <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                <span className="text-2xl font-semibold tabular-nums">{formatNumber(total)}</span>
                <span className="text-xs opacity-60">Total</span>
            </div>
        </div>
    );
}
