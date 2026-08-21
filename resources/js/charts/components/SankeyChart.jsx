import { Sankey as RSankey, ResponsiveContainer, Tooltip, Rectangle } from 'recharts';
import ChartTooltip from './ChartTooltip.jsx';

export default function SankeyChart({ data, theme = 'light' }) {
    const isDark = theme === 'dark';
    return (
        <ResponsiveContainer width="100%" height={360}>
            <RSankey
                data={data}
                nodePadding={16}
                nodeWidth={12}
                link={{ stroke: isDark ? 'rgba(255,255,255,0.20)' : 'rgba(9,9,11,0.15)' }}
                node={<Rectangle radius={4} fill={isDark ? '#fff' : '#111'} />}
            >
                <Tooltip content={<ChartTooltip theme={theme} />} />
            </RSankey>
        </ResponsiveContainer>
    );
}
