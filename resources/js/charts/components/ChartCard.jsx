import { motion } from 'motion/react';
import ChartRouter from '../ChartRouter.jsx';
import { isChartDataEmpty } from '../lib/chartjs-adapter.js';

const THEME_STYLES = {
    light: {
        chartArea: 'bg-[#f4f4f6] text-gray-900',
        errorCard: 'border border-red-300 bg-red-50 text-red-700',
    },
    dark: {
        chartArea: 'bg-[#0f0f10] text-gray-50',
        errorCard: 'border border-red-800 bg-red-950 text-red-300',
    },
};

const EMPTY_STATE_COPY = {
    no_campaign: 'No hay campaña seleccionada',
    default: 'No hay datos para el período seleccionado.',
};

export default function ChartCard({ kind, data, theme = 'light', hasError = false }) {
    const styles = THEME_STYLES[theme] ?? THEME_STYLES.light;

    if (hasError) {
        return (
            <div
                className={`rounded-lg p-4 text-sm font-medium ${styles.errorCard}`}
                role="alert"
                data-testid="react-chart-error"
            >
                No se pudo cargar la gráfica.
            </div>
        );
    }

    const empty = isChartDataEmpty(kind, data);
    const emptyBody = EMPTY_STATE_COPY[data?.emptyReason] ?? EMPTY_STATE_COPY.default;

    return (
        <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ duration: 0.35, ease: [0, 0, 0.2, 1] }}
        >
            <div className={`rounded-[14px] p-4 lg:p-6 ${styles.chartArea}`}>
                {empty ? (
                    <div className="flex min-h-[8rem] flex-col items-center justify-center text-center">
                        <p className="text-sm font-semibold">Sin datos</p>
                        <p className="mt-1 text-xs opacity-60">{emptyBody}</p>
                    </div>
                ) : (
                    <ChartRouter kind={kind} data={data} theme={theme} />
                )}
            </div>
        </motion.div>
    );
}
