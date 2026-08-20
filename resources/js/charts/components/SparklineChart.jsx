import { Line, LineChart as RLineChart, ResponsiveContainer } from 'recharts';
import { formatNumber } from '../lib/formatters.js';

export default function SparklineChart({ data, theme = 'light' }) {
    const points = Array.isArray(data?.points) ? data.points : [];
    const latest = points.length > 0 ? points[points.length - 1].value : 0;
    return (
        <div>
            <p className="text-2xl font-semibold tabular-nums" data-testid="chart-sparkline-value">
                {formatNumber(latest)}
            </p>
            <div style={{ width: '100%', height: 48 }}>
                <ResponsiveContainer>
                    <RLineChart data={points}>
                        <Line
                            type="monotone"
                            dataKey="value"
                            stroke="#f97316"
                            strokeWidth={2}
                            strokeLinecap="round"
                            dot={false}
                        />
                    </RLineChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}
