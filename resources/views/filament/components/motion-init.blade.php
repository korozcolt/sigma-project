{{-- Motion & UX Initializer — injected via PanelsRenderHook::BODY_END on all panels --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    // ─── 1. Topbar scroll shadow ───────────────────────────────────────────────
    const topbar = document.querySelector('.fi-topbar');
    if (topbar) {
        const onScroll = () => {
            topbar.classList.toggle('scrolled', window.scrollY > 8);
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll(); // apply immediately on load
    }

    // ─── 2. Sidebar nav active indicator ─────────────────────────────────────
    // Filament adds aria-current="page" on active links — the CSS ::before
    // pseudo-element handles the visual. This just ensures the class is toggled
    // on Livewire navigations too.
    document.addEventListener('livewire:navigated', () => {
        const currentPath = window.location.pathname;
        document.querySelectorAll('.fi-sidebar-item-button').forEach(btn => {
            const href = btn.getAttribute('href') || '';
            const isActive = href && currentPath.startsWith(href) && href !== '/';
            btn.classList.toggle('fi-active', isActive);
        });
    });

    // ─── 3. Stats counter animation ──────────────────────────────────────────
    const animateCounter = (el, end, duration = 700) => {
        const start = 0;
        const startTime = performance.now();
        const isFloat = String(end).includes('.');
        const decimals = isFloat ? (String(end).split('.')[1] || '').length : 0;

        const step = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            // Quadratic ease-out
            const eased = 1 - Math.pow(1 - progress, 2);
            const value = start + (end - start) * eased;

            el.textContent = isFloat
                ? value.toFixed(decimals)
                : Math.round(value).toLocaleString('es-CO');

            if (progress < 1) {
                requestAnimationFrame(step);
            } else {
                el.classList.add('counting');
                setTimeout(() => el.classList.remove('counting'), 350);
            }
        };

        requestAnimationFrame(step);
    };

    const initCounters = () => {
        document.querySelectorAll('.fi-wi-stats-overview-stat-value').forEach(el => {
            if (el.dataset.animated) { return; }
            el.dataset.animated = '1';

            // Parse the numeric value out of the display text
            const raw = el.textContent.trim().replace(/[^\d.,]/g, '').replace(',', '.');
            const end = parseFloat(raw);

            if (!isNaN(end) && end > 0) {
                el.textContent = '0';
                animateCounter(el, end);
            }
        });
    };

    // Run on first load AND after Livewire updates (widget refreshes)
    initCounters();
    document.addEventListener('livewire:navigated', initCounters);
    Livewire.hook('morph.updated', () => {
        setTimeout(initCounters, 50);
    });

    // ─── 4. Intersection-based section reveal ────────────────────────────────
    // For sections that are below the fold — add subtle reveal when scrolled to
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animationPlayState = 'running';
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.05 });

        document.querySelectorAll('.fi-wi, .fi-section').forEach(el => {
            // Pause the CSS animation for off-screen elements until visible
            if (el.getBoundingClientRect().top > window.innerHeight) {
                el.style.animationPlayState = 'paused';
                observer.observe(el);
            }
        });
    }

    // ─── 5. Table row hover — add subtle translateX only when not on mobile ──
    const prefersMouse = window.matchMedia('(hover: hover) and (pointer: fine)');
    if (prefersMouse.matches) {
        document.addEventListener('mouseover', (e) => {
            const row = e.target.closest('.fi-ta-row');
            if (row) { row.style.transform = 'translateX(1px)'; }
        });
        document.addEventListener('mouseout', (e) => {
            const row = e.target.closest('.fi-ta-row');
            if (row) { row.style.transform = ''; }
        });
    }
});
</script>
