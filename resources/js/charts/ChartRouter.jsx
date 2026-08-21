import LineChartKind from './components/LineChart.jsx';
import BarChartKind from './components/BarChart.jsx';
import PieChartKind from './components/PieChart.jsx';
import SparklineChartKind from './components/SparklineChart.jsx';
import StackedBarChartKind from './components/StackedBarChart.jsx';
import FunnelChartKind from './components/FunnelChart.jsx';
import GaugeChartKind from './components/GaugeChart.jsx';
import HistogramChartKind from './components/HistogramChart.jsx';
import SankeyChartKind from './components/SankeyChart.jsx';
import TreemapChartKind from './components/TreemapChart.jsx';
import HeatmapChartKind from './components/HeatmapChart.jsx';
import StackedAreaChartKind from './components/StackedAreaChart.jsx';

const KIND_COMPONENTS = {
    line: LineChartKind,
    bar: BarChartKind,
    pie: PieChartKind,
    sparkline: SparklineChartKind,
    'stacked-bar': StackedBarChartKind,
    funnel: FunnelChartKind,
    gauge: GaugeChartKind,
    histogram: HistogramChartKind,
    sankey: SankeyChartKind,
    treemap: TreemapChartKind,
    heatmap: HeatmapChartKind,
    'stacked-area': StackedAreaChartKind,
};

export default function ChartRouter({ kind, data, theme = 'light' }) {
    const Component = KIND_COMPONENTS[kind] ?? BarChartKind;
    return <Component data={data} theme={theme} />;
}
