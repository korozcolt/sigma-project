import { Cell, Funnel, FunnelChart as RFunnelChart, LabelList, ResponsiveContainer, Tooltip } from 'recharts';
import { toOrderedRows } from '../lib/chartjs-adapter.js';
import { rankedMonochromeFill } from '../lib/palette.js';
import ChartTooltip from './ChartTooltip.jsx';

// Recharts' LabelList position="right" clamps its label to a `width` derived purely from the
// funnel trapezoid's own geometry (getCartesianPosition's "right" case, in
// node_modules/recharts/es6/cartesian/getCartesianPosition.js: `width = parentViewBox.right - x`,
// where `x` is itself proportional to the *same* realWidth the trapezoid is drawn at - realWidth
// = plot width - margin.left - margin.right, per node_modules/recharts/es6/cartesian/Funnel.js's
// `computeFunnelTrapezoids()`). That means the label's clamp room is a fixed *proportion* of
// realWidth set only by the data's own value ratios between adjacent stages - increasing
// margin.right shrinks realWidth and so shrinks that room too, it never reserves a real fixed-px
// label column. For the funnel's first (widest) row specifically, that proportion approaches zero
// whenever the next stage is anywhere close in count to the first (the common, realistic case for
// a healthy campaign funnel) - confirmed by rendering this exact widget in a real browser: even
// with margin.right=168, "Pendiente de Revisión" still wrapped across 3 <tspan> lines with no
// space between the words, which is why assertSee('Pendiente de Revisión') failed (the wrapped
// text reads "PendientedeRevisión" with no space, not a visual-only clipping issue). Recharts'
// clamp-driven word-wrap (node_modules/recharts/es6/component/Text.js's `getWordsByLines()`) is
// only applied by the *default* Label/Text render path - a custom `content` render function on
// LabelList never receives that clamp `width` at all (see node_modules/recharts/es6/component/
// Label.js: only `x`/`y` are forwarded to a function `content`, `width`/`height` are computed but
// never spread into a custom content's props), so rendering our own plain `<text>` there bypasses
// the wrap logic entirely and always renders the label as a single line with its real spacing
// intact, at the exact same x/y Recharts would have used for its own unclamped position. Margin is
// still reserved (though modestly, not to "fit" any specific label - clamp width can't be
// controlled that way) purely to keep the funnel's own trapezoids from touching the widget edge.
const FUNNEL_MARGIN = { top: 8, right: 24, bottom: 8, left: 8 };
const LABEL_FONT_SIZE = 12;

function FunnelRightLabel({ x, y, value, fill }) {
    if (x == null || y == null) {
        return null;
    }
    return (
        <text x={x} y={y} dy={4} fontSize={LABEL_FONT_SIZE} fill={fill} stroke="none" textAnchor="start">
            {value}
        </text>
    );
}

export default function FunnelChart({ data, theme = 'light' }) {
    const rows = toOrderedRows(data ?? {});
    const labelColor = theme === 'dark' ? '#fff' : '#111';

    return (
        <ResponsiveContainer width="100%" height={280}>
            <RFunnelChart margin={FUNNEL_MARGIN}>
                <Tooltip content={<ChartTooltip theme={theme} />} />
                <Funnel dataKey="value" nameKey="name" data={rows} isAnimationActive>
                    <LabelList
                        position="right"
                        dataKey="name"
                        content={(labelProps) => <FunnelRightLabel {...labelProps} fill={labelColor} />}
                    />
                    {rows.map((row, i) => (
                        <Cell key={row.name} fill={rankedMonochromeFill(i, rows.length, { isDark: theme === 'dark' })} />
                    ))}
                </Funnel>
            </RFunnelChart>
        </ResponsiveContainer>
    );
}
