import { Cell, Funnel, FunnelChart as RFunnelChart, LabelList, ResponsiveContainer, Tooltip } from 'recharts';
import { toOrderedRows } from '../lib/chartjs-adapter.js';
import { rankedMonochromeFill } from '../lib/palette.js';
import ChartTooltip from './ChartTooltip.jsx';

// Recharts clamps LabelList position="right" text to the remaining horizontal space between
// the funnel trapezoid's own right edge and the chart's right margin edge (getCartesianPosition's
// "right" case, node_modules/recharts/es6/cartesian/getCartesianPosition.js) - with FunnelChart's
// default 5px margin, long Spanish stage names (e.g. "Pendiente de Revisión") have almost no room
// and wrap across multiple lines or get clipped mid-word. Reserve a real label column via
// margin.right instead of the 5px default, and set an explicit fontSize (every other Recharts
// text element in this codebase already sets fontSize={12} - BarChart/LineChart/HistogramChart/
// StackedAreaChart/StackedBarChart/TreemapChart - this was the one component that silently
// inherited the browser/SVG ~16px default instead).
const FUNNEL_MARGIN = { top: 8, right: 168, bottom: 8, left: 8 };
const LABEL_FONT_SIZE = 12;

export default function FunnelChart({ data, theme = 'light' }) {
    const rows = toOrderedRows(data ?? {});

    return (
        <ResponsiveContainer width="100%" height={280}>
            <RFunnelChart margin={FUNNEL_MARGIN}>
                <Tooltip content={<ChartTooltip theme={theme} />} />
                <Funnel dataKey="value" nameKey="name" data={rows} isAnimationActive>
                    <LabelList
                        position="right"
                        dataKey="name"
                        fill={theme === 'dark' ? '#fff' : '#111'}
                        stroke="none"
                        fontSize={LABEL_FONT_SIZE}
                    />
                    {rows.map((row, i) => (
                        <Cell key={row.name} fill={rankedMonochromeFill(i, rows.length, { isDark: theme === 'dark' })} />
                    ))}
                </Funnel>
            </RFunnelChart>
        </ResponsiveContainer>
    );
}
