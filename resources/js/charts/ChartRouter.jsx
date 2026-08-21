import LineChartKind from './components/LineChart.jsx';
import BarChartKind from './components/BarChart.jsx';
import PieChartKind from './components/PieChart.jsx';
import SparklineChartKind from './components/SparklineChart.jsx';

const KIND_COMPONENTS = {
    line: LineChartKind,
    bar: BarChartKind,
    pie: PieChartKind,
    sparkline: SparklineChartKind,
};

export default function ChartRouter({ kind, data, theme = 'light' }) {
    const Component = KIND_COMPONENTS[kind] ?? BarChartKind;
    return <Component data={data} theme={theme} />;
}
