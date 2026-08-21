import { Treemap as RTreemap, ResponsiveContainer } from 'recharts';
import { rankedMonochromeFill } from '../lib/palette.js';

// Recharts spreads the original node object into `nodeProps` before adding computed fields
// (see Treemap.js `computeNode()`/`renderNode()`), so custom keys attached here survive through
// to the `content` render-prop below, giving us per-level sibling rank without any Recharts API
// for it directly.
function decorateSiblings(nodes) {
    return nodes.map((node, index) => ({
        ...node,
        __siblingIndex: index,
        __siblingTotal: nodes.length,
        children: node.children ? decorateSiblings(node.children) : undefined,
    }));
}

function TreemapNode({ x, y, width, height, name, depth, __siblingIndex = 0, __siblingTotal = 1, theme }) {
    if (width <= 0 || height <= 0) {
        return null;
    }
    const isDark = theme === 'dark';
    const fill = rankedMonochromeFill(__siblingIndex, __siblingTotal, { isDark });
    const showText = width > 40 && height > 20;
    return (
        <g>
            <rect x={x} y={y} width={width} height={height} fill={fill} stroke={isDark ? '#0f0f10' : '#f4f4f6'} strokeWidth={2} />
            {showText && (
                <text
                    x={x + 8}
                    y={y + height / 2 + 5}
                    fontSize={12}
                    fill={depth <= 2 ? (isDark ? '#fff' : '#111') : (isDark ? 'rgba(255,255,255,0.85)' : 'rgba(9,9,11,0.85)')}
                >
                    {name}
                </text>
            )}
        </g>
    );
}

export default function TreemapChart({ data, theme = 'light' }) {
    const tree = decorateSiblings(data?.tree ?? []);
    return (
        <div className="w-full [&_.recharts-treemap-nest-index-box]:!m-0 [&_.recharts-treemap-nest-index-box]:!bg-transparent [&_.recharts-treemap-nest-index-box]:!p-0 [&_.recharts-treemap-nest-index-box]:!text-current [&_.recharts-treemap-nest-index-wrapper]:!mt-2 [&_.recharts-treemap-nest-index-wrapper]:!text-left [&_.recharts-treemap-nest-index-wrapper]:!text-xs">
            <ResponsiveContainer width="100%" height={360}>
                <RTreemap
                    data={tree}
                    dataKey="value"
                    nameKey="name"
                    type="nest"
                    aspectRatio={4 / 3}
                    nodeGap={2}
                    content={(nodeProps) => <TreemapNode {...nodeProps} theme={theme} />}
                    nestIndexContent={(item, i) => (
                        <span className="inline-flex cursor-pointer items-center gap-2 opacity-80 hover:opacity-100">
                            {i > 0 && <span aria-hidden="true">{'>'}</span>}
                            {i === 0 ? 'Todos' : item.name}
                        </span>
                    )}
                />
            </ResponsiveContainer>
        </div>
    );
}
