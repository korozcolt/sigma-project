import { createRoot } from 'react-dom/client';
import ChartCard from './components/ChartCard.jsx';

// Module-level registry of every live React root, keyed by its mount DOM
// node. Belt-and-suspenders cleanup (PITFALLS.md Pitfall 2): the per-element
// Alpine destroy() hook below is the primary teardown path, but a
// livewire:navigate full-shell swap is not guaranteed to run every nested
// element's destroy() first in every Livewire/Alpine version combination, so
// this registry lets a single top-level listener force-unmount anything left
// behind.
const liveRoots = new Map();

window.addEventListener('livewire:navigate', () => {
    liveRoots.forEach((root) => root.unmount());
    liveRoots.clear();
});

document.addEventListener('alpine:init', () => {
    window.Alpine.data('reactChartBridge', ({ initialData, chartKind, theme }) => ({
        _root: null,

        init() {
            try {
                this._root = createRoot(this.$el);
                liveRoots.set(this.$el, this._root);
                this._render(initialData);
                // Source of truth for "did the bundle actually mount," read by
                // the Blade-level fallback-timeout script (20-02) — deliberately
                // NOT inferred from this.$el.hasChildNodes(), since a Livewire
                // re-render of an ancestor can mutate DOM content underneath
                // this node independent of whether React ever mounted
                // (PITFALLS.md Pitfall 5).
                this.$el.dataset.reactMounted = 'true';
            } catch (error) {
                console.error('[reactChartBridge] initial mount failed', error);
                this._renderError();
            }

            this.$wire.$on('updateChartData', ({ data }) => this._render(data));
        },

        _render(data) {
            if (!this._root) {
                return;
            }

            try {
                this._root.render(
                    <ChartCard kind={chartKind} data={data} theme={theme ?? 'light'} hasError={false} />
                );
            } catch (error) {
                console.error('[reactChartBridge] render failed', error);
                this._renderError();
            }
        },

        _renderError() {
            if (!this._root) {
                return;
            }

            this._root.render(<ChartCard kind={chartKind} data={null} theme={theme ?? 'light'} hasError={true} />);
        },

        destroy() {
            if (this._root) {
                this._root.unmount();
                liveRoots.delete(this.$el);
                this._root = null;
            }
        },
    }));
});
