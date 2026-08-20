import { motion } from 'motion/react';
import { Bar, BarChart, ResponsiveContainer, XAxis, YAxis } from 'recharts';

const THEME_STYLES = {
    light: {
        card: 'border border-gray-200 bg-white text-gray-900',
        bar: '#f97316',
        errorCard: 'border border-red-300 bg-red-50 text-red-700',
    },
    dark: {
        card: 'border border-gray-700 bg-gray-900 text-gray-50',
        bar: '#fb923c',
        errorCard: 'border border-red-800 bg-red-950 text-red-300',
    },
};

export default function ChartCard({ data, theme = 'light', hasError = false }) {
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

    const points = Array.isArray(data?.points) ? data.points : [];
    const latestValue = points.length > 0 ? points[points.length - 1].value : 0;

    return (
        <motion.div
            className={`rounded-lg p-4 ${styles.card}`}
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ duration: 0.2 }}
            data-testid="react-chart-poc"
        >
            <p className="text-xs font-medium uppercase tracking-wide opacity-70">
                React Island PoC
            </p>
            <p className="mt-1 text-2xl font-semibold" data-testid="react-chart-poc-value">
                {latestValue}
            </p>
            <div style={{ width: '100%', height: 80 }}>
                <ResponsiveContainer>
                    <BarChart data={points}>
                        <XAxis dataKey="label" hide />
                        <YAxis hide />
                        <Bar dataKey="value" fill={styles.bar} radius={[4, 4, 0, 0]} />
                    </BarChart>
                </ResponsiveContainer>
            </div>
        </motion.div>
    );
}
