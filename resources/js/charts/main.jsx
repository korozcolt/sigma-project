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
    window.Alpine.data('reactChartBridge', ({ initialData, chartKind, theme }) => {
        // `root` is deliberately a plain closure variable, NOT a property on the
        // object returned below (e.g. `this._root`). Alpine wraps every returned
        // data-object property in its own reactivity Proxy, recursively — and
        // ReactDOMClient's root instance carries internal, non-configurable
        // properties that violate JS Proxy invariants once Alpine's reactive
        // get-trap tries to deep-wrap it (`TypeError: 'get' on proxy: property
        // ... is a read-only and non-configurable data property ...`), crashing
        // before any content ever renders. Keeping the root out of Alpine's
        // reactive object entirely avoids this class of bug.
        let root = null;

        function render(data) {
            if (!root) {
                return;
            }

            try {
                root.render(<ChartCard kind={chartKind} data={data} theme={theme ?? 'light'} hasError={false} />);
            } catch (error) {
                console.error('[reactChartBridge] render failed', error);
                renderError();
            }
        }

        function renderError() {
            if (!root) {
                return;
            }

            root.render(<ChartCard kind={chartKind} data={null} theme={theme ?? 'light'} hasError={true} />);
        }

        return {
            init() {
                try {
                    root = createRoot(this.$el);
                    liveRoots.set(this.$el, root);
                    render(initialData);
                    // Source of truth for "did the bundle actually mount," read by
                    // the Blade-level fallback-timeout script (20-02) — deliberately
                    // NOT inferred from this.$el.hasChildNodes(), since a Livewire
                    // re-render of an ancestor can mutate DOM content underneath
                    // this node independent of whether React ever mounted
                    // (PITFALLS.md Pitfall 5).
                    this.$el.dataset.reactMounted = 'true';
                } catch (error) {
                    console.error('[reactChartBridge] initial mount failed', error);
                    renderError();
                }

                this.$wire.$on('updateChartData', ({ data }) => render(data));
            },

            destroy() {
                if (root) {
                    root.unmount();
                    liveRoots.delete(this.$el);
                    root = null;
                }
            },
        };
    });
});
