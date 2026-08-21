import { Fragment, useState } from 'react';
import ChartTooltip from './ChartTooltip.jsx';

function cellColor(rate, isDark) {
    if (rate === null || rate === undefined) {
        // D-16: no-data shade — visually distinct from a real 0%-effectiveness cell.
        return isDark ? 'rgba(255,255,255,0.04)' : 'rgba(9,9,11,0.03)';
    }
    const t = Math.max(0, Math.min(1, rate / 100));
    return `rgba(249,115,22,${(0.12 + t * 0.75).toFixed(2)})`; // accent orange ramp, D-13
}

export default function HeatmapChart({ data, theme = 'light' }) {
    const { cells = [], callers = {}, hours = [] } = data ?? {};
    const isDark = theme === 'dark';
    const [hover, setHover] = useState(null); // { x, y, payload, label }
    const byCallerHour = new Map(cells.map((c) => [`${c.caller_id}-${c.hour}`, c]));

    return (
        <div className="relative max-h-[420px] overflow-y-auto overflow-x-auto">
            <div className="grid" style={{ gridTemplateColumns: `140px repeat(${hours.length}, 32px)` }}>
                <div className="sticky left-0 top-0 z-10 bg-inherit" />
                {hours.map((h) => (
                    <div key={h} className="sticky top-0 z-10 bg-inherit text-center text-[10px] opacity-60">{h}h</div>
                ))}
                {Object.entries(callers).map(([callerId, name]) => (
                    <Fragment key={callerId}>
                        <div className="sticky left-0 z-10 truncate bg-inherit pr-2 text-xs">{name}</div>
                        {hours.map((h) => {
                            const cell = byCallerHour.get(`${callerId}-${h}`) ?? null;
                            return (
                                <div
                                    key={`${callerId}-${h}`}
                                    data-caller-id={callerId}
                                    data-hour={h}
                                    className="h-8 w-8 cursor-default"
                                    style={{ backgroundColor: cellColor(cell?.rate ?? null, isDark) }}
                                    onMouseMove={(e) => setHover({
                                        x: e.clientX,
                                        y: e.clientY,
                                        payload: [{ name: 'Efectividad', value: cell ? `${cell.rate}%` : 'Sin datos', color: '#f97316' }],
                                        label: `${name} · ${h}h`,
                                    })}
                                    onMouseLeave={() => setHover(null)}
                                />
                            );
                        })}
                    </Fragment>
                ))}
            </div>
            {hover && (
                <div className="pointer-events-none fixed z-50" style={{ left: hover.x + 12, top: hover.y + 12 }}>
                    <ChartTooltip active payload={hover.payload} label={hover.label} theme={theme} />
                </div>
            )}
        </div>
    );
}
